<?php

namespace App\Jobs;

use App\Jobs\Concerns\HandlesPromptResponses;
use App\Models\Prompt;
use App\Services\JobDispatcherService;
use App\Services\OpenAIBatchService;
use App\Services\OpenAIPromptService;
use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Log;
use Throwable;
use App\Jobs\FindCompetitorsInResponseJob;

class ProcessOpenAIBatchJob extends TrackableJob
{
    use HandlesPromptResponses;

    protected string $batchId;
    protected int $teamId;
    protected int $campaignId;
    protected int $totalRuns;
    protected DateTimeInterface $retryUntilAt;

    /**
     * Allow many attempts so we can poll for up to 24h without failing.
     * The queue worker's --tries setting will be overridden by this value.
     */
    public int $tries = 100000;

    public function __construct(string $batchId, int $teamId, int $campaignId, int $totalRuns)
    {
        $this->batchId = $batchId;
        $this->teamId = $teamId;
        $this->campaignId = $campaignId;
        $this->totalRuns = $totalRuns;
        // Expire this job after ~24 hours to match the batch completion window
        $this->retryUntilAt = Carbon::now()->addHours(24);
    }

    public function retryUntil(): DateTimeInterface
    {
        return $this->retryUntilAt;
    }

    public function handle(OpenAIBatchService $batchService, JobDispatcherService $jobDispatcher)
    {
        try {
            if ($this->isCancelled()) {
                return;
            }

            $this->markJobAsStarted('Processing OpenAI batch');

            $batch = $batchService->retrieveBatch($this->batchId);

            $status = $batch['status'] ?? 'unknown';
            if (!in_array($status, ['completed', 'failed'])) {
                $this->updateJobProgress(50, 'Batch status: ' . $status);
                $this->release(30);
                return;
            }

            if ($status !== 'completed') {
                $this->markJobAsFailed(new \Exception('Batch failed'));
                return;
            }

            $this->updateJobProgress(70, 'Downloading batch output');

            $lines = $batchService->downloadBatchOutput($batch['output_file_id']);

            $promptService = new OpenAIPromptService();
            $processedCount = 0;

            foreach ($lines as $line) {
                $custom = $line['custom_id'] ?? '';
                if (!isset($line['response']) || ($line['response']['status_code'] ?? 0) !== 200) {
                    Log::error('Batch item failed', ['custom_id' => $custom, 'error' => $line['error'] ?? null]);
                    continue;
                }

                if (preg_match('/prompt_(\d+)_run_\d+/', $custom, $matches)) {
                    $promptId = (int) $matches[1];
                    $prompt = Prompt::find($promptId);
                    if (!$prompt) {
                        continue;
                    }

                    $body = (object) ($line['response']['body'] ?? []);
                    $processed = $promptService->processResponse($body);

                    $response = $prompt->responses()->create([
                        'provider' => 'openai',
                        'model' => 'gpt-5',
                        'content' => $processed->responseMessages[0]->content ?? '',
                        'usage' => $processed->usage ?? null,
                    ]);

                    $this->saveSearchToolResults($processed, $response);
                    $this->checkForTerms($response, $prompt);

                    if ($prompt->responses()->count() == 1) {
                        $jobDispatcher->dispatch($prompt, new FindCompetitorsInResponseJob($prompt));
                    }

                    $processedCount++;
                }
            }

            $this->markJobAsCompleted('Processed ' . $processedCount . ' responses');
        } catch (Throwable $e) {
            Log::error('ProcessOpenAIBatchJob failed: ' . $e->getMessage());
            $this->markJobAsFailed($e);
            throw $e;
        }
    }
}
