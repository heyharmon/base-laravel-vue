<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\OpenAIPromptService;
use App\Services\JobDispatcherService;
use App\Models\Response as ResponseModel;
use App\Models\Prompt;
use App\Models\Term;
use App\Jobs\FindCompetitorsInResponseJob;

class PollOpenAIResponseJob extends TrackableJob
{
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     * Keep this short since we only poll a single endpoint.
     *
     * @var int
     */
    public $timeout = 30;

    /**
     * The response ID (DB primary key) to poll.
     *
     * @var int
     */
    protected $responseDbId;

    /**
     * The model used for job tracking (we track against the prompt).
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    public $model;

    public function __construct(int $responseDbId)
    {
        $this->responseDbId = $responseDbId;
    }

    public function handle(JobDispatcherService $jobDispatcher, OpenAIPromptService $openAI)
    {
        try {
            $response = ResponseModel::with('prompt')->find($this->responseDbId);
            if (!$response) {
                // Nothing to do
                return;
            }

            // Track against the prompt to keep UX consistent
            $this->model = $response->prompt;

            if ($this->isCancelled()) {
                return;
            }

            $this->markJobAsStarted('Polling OpenAI response status');

            // If already completed, nothing to do
            if ($response->status === 'completed') {
                $this->markJobAsCompleted('Already completed');
                return;
            }

            if (!$response->provider_id) {
                // No OpenAI response id to poll; treat as failed so we re-run
                $this->reissueIfFailed($response, $jobDispatcher, $openAI);
                return;
            }

            $fresh = $openAI->retrieveResponse($response->provider_id);
            $status = $fresh->status ?? 'in_progress';

            if ($status === 'completed') {
                // Update with final content and usage
                $response->content = $fresh->content ?? '';
                $response->usage = $fresh->usage ?? null;
                $response->status = 'completed';
                $response->save();

                // Save citations/annotations
                $this->saveSearchData($fresh, $response);

                // Check for terms in the response
                $this->checkForTerms($response, $response->prompt);

                // If this is the first COMPLETED response for the prompt, run competitor finder
                try {
                    if ($response->prompt->responses()->where('status', 'completed')->count() == 1) {
                        $jobDispatcher->dispatch($response->prompt, new FindCompetitorsInResponseJob($response->prompt));
                    }
                } catch (\Exception $e) {
                    Log::error('Failed to dispatch FindCompetitorsInResponseJob (poll)', [
                        'prompt_id' => $response->prompt->id,
                        'error' => $e->getMessage(),
                    ]);
                }

                $this->markJobAsCompleted('Response completed');
                return;
            }

            if ($status === 'failed') {
                // Try again per requirements
                $this->reissueIfFailed($response, $jobDispatcher, $openAI);
                return;
            }

            // Still in progress; schedule another poll in 15 seconds
            $response->status = $status;
            $response->save();

            $next = new self($response->id);
            $next->delay(now()->addSeconds(15));
            $jobDispatcher->dispatch($response->prompt, $next);

            $this->markJobAsCompleted('Re-queued polling');
        } catch (Throwable $exception) {
            Log::error('PollOpenAIResponseJob failed with exception', [
                'response_db_id' => $this->responseDbId,
                'error' => $exception->getMessage(),
            ]);
            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }

    protected function reissueIfFailed(ResponseModel $response, JobDispatcherService $jobDispatcher, OpenAIPromptService $openAI): void
    {
        try {
            $prompt = $response->prompt;
            $tier = $response->flex ? 'flex' : 'auto';
            // Defer model selection to the service default
            $fresh = $openAI->getResponse($prompt->content, $tier);

            // Update response with new id/status and clear content until completion
            $response->provider_id = $fresh->id ?? null;
            $response->status = $fresh->status ?? 'in_progress';
            $response->content = '';
            $response->usage = null;
            $response->save();

            // Schedule the next poll
            $next = new self($response->id);
            $next->delay(now()->addSeconds(15));
            $jobDispatcher->dispatch($prompt, $next);

            $this->markJobAsCompleted('Reissued and re-queued polling');
        } catch (\Exception $e) {
            Log::error('Failed to reissue OpenAI response after failure', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    protected function saveSearchData(object $llmResponse, ResponseModel $response): void
    {
        $searchData = [
            'annotations' => $llmResponse->annotations ?? [],
        ];

        $response->update(['search' => $searchData]);
    }

    protected function checkForTerms(ResponseModel $response, Prompt $prompt): void
    {
        $teamId = $prompt->team_id;
        $campaignId = $prompt->campaign_id;

        // Get terms for all organizations scoped to the team and campaign
        $terms = Term::whereHas('organization', function ($query) use ($teamId, $campaignId) {
            $query->where('team_id', $teamId)
                ->where(function ($q) use ($campaignId) {
                    $q->where('campaign_id', $campaignId)
                        ->orWhere(function ($subQ) {
                            $subQ->whereNull('campaign_id')->where('is_competitor', false);
                        });
                });
        })->get();

        $responseText = strtolower($response->content ?? '');
        $foundTerms = [];

        foreach ($terms as $term) {
            $termName = strtolower($term->name);

            if ($termName !== '' && str_contains($responseText, $termName)) {
                $foundTerms[] = $term->id;
                $existingRelation = $prompt->terms()->where('term_id', $term->id)->exists();

                if (!$existingRelation) {
                    $prompt->terms()->attach($term->id, [
                        'count' => 1,
                        'last_found_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $prompt->terms()->updateExistingPivot($term->id, [
                        'count' => DB::raw('count + 1'),
                        'last_found_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (!empty($foundTerms)) {
            $response->terms()->syncWithoutDetaching($foundTerms);
        }
    }
}
