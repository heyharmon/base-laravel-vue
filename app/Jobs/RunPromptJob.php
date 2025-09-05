<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\OpenAIPromptService;
use App\Models\Prompt;
use App\Jobs\PollOpenAIResponseJob;

class RunPromptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The prompt instance.
     *
     * @var \App\Models\Prompt
     */
    protected $prompt;

    /**
     * Optional OpenAI service tier (e.g., 'flex').
     *
     * @var string|null
     */
    protected $serviceTier;

    /**
     * The team ID.
     *
     * @var int
     */
    protected $teamId;

    /**
     * The campaign ID.
     *
     * @var int
     */
    protected $campaignId;

    /**
     * The response DB id once created.
     */
    protected ?int $responseId = null;

    /**
     * Max attempts when creating the OpenAI response.
     */
    protected int $maxInitialAttempts = 5;

    protected int $baseDelay = 15; // seconds

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Prompt  $prompt
     * @param  array  $providers
     * @param  int  $teamId
     * @param  int  $campaignId
     * @return void
     */
    public function __construct(Prompt $prompt, array $providers, int $teamId, int $campaignId, ?string $serviceTier = null, ?int $responseId = null)
    {
        $this->teamId = $teamId;
        $this->campaignId = $campaignId;
        $this->prompt = $prompt;
        $this->serviceTier = $serviceTier;
        $this->responseId = $responseId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OpenAIPromptService $openAI)
    {
        try {
            $team = \App\Models\Team::find($this->teamId);
            if ($team && ($remaining = $team->responsesRemaining()) !== null && $remaining <= 0) {
                return;
            }

            $providerName = 'openai';
            $model = 'gpt-4';

            if (!$this->responseId) {
                $response = $this->prompt->responses()->create([
                    'provider' => $providerName,
                    'model' => $model,
                    'flex' => $this->serviceTier === 'flex',
                    'status' => 'queued',
                    'content' => '',
                ]);
                $this->responseId = $response->id;
            } else {
                $response = \App\Models\Response::find($this->responseId);
            }

            if (!$response) {
                return;
            }

            $attempt = $response->initial_attempts ?? 0;
            $options = [];
            if ($this->serviceTier === 'flex') {
                $options['service_tier'] = 'flex';
            }

            try {
                $llm = $openAI->getResponse($this->prompt->content, $model, $options);
            } catch (Throwable $e) {
                $attempt++;
                $delay = (int) min($this->baseDelay * (2 ** ($attempt - 1)), 900);
                $delay = (int) ($delay * random_int(80, 120) / 100);
                $response->update([
                    'initial_attempts' => $attempt,
                    'status' => 'queued',
                    'error_code' => $e->getCode() ?: null,
                ]);
                if ($attempt >= $this->maxInitialAttempts) {
                    $response->update(['status' => 'failed']);
                    Log::error('RunPromptJob exceeded attempts', ['response_id' => $response->id]);
                    return;
                }
                $this->release($delay);
                return;
            }

            $response->update([
                'status' => $llm->status ?? 'in_progress',
                'provider_id' => $llm->id ?? null,
                'usage' => $llm->usage ?? null,
            ]);

            $pollJob = new PollOpenAIResponseJob($response->id);
            $pollJob->delay(now()->addSeconds(15));
            dispatch($pollJob);
            Log::info('RunPromptJob dispatched PollOpenAIResponseJob', ['response_id' => $response->id, 'job_id' => $this->job->getJobId() ?? 'unknown']);
        } catch (Throwable $exception) {
            Log::error('RunPromptJob failed with exception', [
                'prompt_id' => $this->prompt->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'job_id' => $this->job->getJobId() ?? 'unknown'
            ]);
            throw $exception;
        }
    }

    public function tags(): array
    {
        return ['prompt:' . $this->prompt->id];
    }

    /**
     * Handle a job failure.
     *
     * @param  Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        Log::error('RunPromptJob definitively failed', [
            'prompt_id' => $this->prompt->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job->getJobId() ?? 'unknown',
            'attempts' => $this->attempts(),
            'max_tries' => $this->tries
        ]);
    }
}
