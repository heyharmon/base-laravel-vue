<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\JobDispatcherService;
use App\Services\OpenAIPromptService;
use App\Models\Response;
use App\Models\Prompt;
use App\Models\Term;

class RunPromptJob extends TrackableJob
{

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
    public $timeout = 300; // 5 minutes for GPT-5 reasoning

    /**
     * The prompt instance.
     *
     * @var \App\Models\Prompt
     */
    protected $prompt;

    /**
     * The model to use for job tracking.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * The providers to use for running the prompt.
     *
     * @var array
     */
    protected $providers;

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
     * Supported providers for this job.
     */
    private const SUPPORTED_PROVIDERS = ['openai'];

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Prompt  $prompt
     * @param  array  $providers
     * @param  int  $teamId
     * @param  int  $campaignId
     * @return void
     */
    public function __construct(Prompt $prompt, array $providers = [], int $teamId, int $campaignId)
    {
        $this->model = $prompt;
        $this->teamId = $teamId;
        $this->campaignId = $campaignId;
        $this->prompt = $prompt;
        $this->providers = $providers;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(JobDispatcherService $jobDispatcher, OpenAIPromptService $openAI)
    {
        try {
            if ($this->isCancelled()) {
                return;
            }

            $team = \App\Models\Team::find($this->teamId);
            if ($team && ($remaining = $team->responsesRemaining()) !== null && $remaining <= 0) {
                $this->markJobAsCompleted('Responses limit reached');
                return;
            }

            // Mark the job as started
            $this->markJobAsStarted('Running a prompt');

            // Determine provider (we currently support only OpenAI in this job)
            $providers = $this->providers ?: ['openai'];
            $providers = array_values(array_intersect($providers, self::SUPPORTED_PROVIDERS));
            if (empty($providers)) {
                $error = 'No supported providers specified';
                Log::error('RunPromptJob failed: ' . $error, ['prompt_id' => $this->prompt->id]);
                throw new \RuntimeException($error);
            }

            $providerName = $providers[0];
            $model = $this->defaultModelFor($providerName);

            $this->updateJobProgress(20, 'Sending prompt to ' . $providerName);

            // Get response from the LLM (single provider for reliability)
            $llm = $openAI->getResponse($this->prompt->content, $model);

            // Persist response
            $response = $this->prompt->responses()->create([
                'provider' => $providerName,
                'model' => $model,
                'content' => $llm->content ?? '',
                'usage' => $llm->usage ?? null,
            ]);

            // Save search annotations/citations when present
            $this->saveSearchData($llm, $response);

            // Check for terms in the response
            $this->updateJobProgress(60, 'Scanning for tracked terms');
            $this->checkForTerms($response, $this->prompt);

            $this->updateJobProgress(90, 'Processing complete');

            // Find competitors in response if this is the first response to this prompt
            try {
                if ($this->prompt->responses()->count() == 1) {
                    // Log::info('Dispatching FindCompetitorsInResponseJob', ['prompt_id' => $this->prompt->id]);
                    $jobDispatcher->dispatch($this->prompt, new FindCompetitorsInResponseJob($this->prompt));
                }
            } catch (\Exception $e) {
                Log::error('Failed to dispatch FindCompetitorsInResponseJob', [
                    'prompt_id' => $this->prompt->id,
                    'error' => $e->getMessage()
                ]);
                // Don't throw - this is not critical to the main job
            }

            // Mark the job as completed
            $this->markJobAsCompleted('Successfully generated 1 response for prompt');
        } catch (Throwable $exception) {
            Log::error('RunPromptJob failed with exception', [
                'prompt_id' => $this->prompt->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'job_id' => $this->job->getJobId() ?? 'unknown'
            ]);

            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }

    /**
     * Get response from the LLM using the appropriate service.
     *
     * @param  string  $promptContent
     * @param  string  $model
     * @param  string  $provider
     * @return mixed
     */
    private function defaultModelFor(string $provider): string
    {
        // Extend this switch if more providers are added later
        switch ($provider) {
            case 'openai':
            default:
                return 'gpt-5';
        }
    }

    /**
     * Save search tool results.
     *
     * @param  object  $llmResponse
     * @param  Response  $response
     * @return void
     */
    private function saveSearchData(object $llmResponse, Response $response): void
    {
        $searchData = [
            'annotations' => $llmResponse->annotations ?? [],
        ];

        $response->update(['search' => $searchData]);
    }

    /**
     * Check for terms in the response.
     *
     * @param  Response  $response
     * @param  Prompt  $prompt
     * @return void
     */
    private function checkForTerms(Response $response, Prompt $prompt): void
    {
        // Get terms for all organizations scoped to the team and campaign
        $terms = Term::whereHas('organization', function ($query) {
            $query->where('team_id', $this->teamId)
                ->where(function ($q) {
                    // Include competitor organizations for this campaign
                    $q->where('campaign_id', $this->campaignId)
                        // OR include the owned organization (campaign_id is NULL and is_competitor is false)
                        ->orWhere(function ($subQ) {
                            $subQ->whereNull('campaign_id')->where('is_competitor', false);
                        });
                });
        })->get();

        $responseText = strtolower($response->content);
        $foundTerms = [];

        foreach ($terms as $term) {
            $termName = strtolower($term->name);

            // Check if the term exists in the response
            if (str_contains($responseText, $termName)) {
                $foundTerms[] = $term->id;

                // Check if the relationship already exists
                $existingRelation = $prompt->terms()->where('term_id', $term->id)->exists();

                if (!$existingRelation) {
                    // Create new relationship
                    $prompt->terms()->attach($term->id, [
                        'count' => 1,
                        'last_found_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Update existing relationship
                    $prompt->terms()->updateExistingPivot($term->id, [
                        'count' => DB::raw('count + 1'),
                        'last_found_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Attach found terms to the response and record a mention
        if (!empty($foundTerms)) {
            $response->terms()->syncWithoutDetaching($foundTerms);
        }
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

        // Mark job as failed in tracking
        $this->markJobAsFailed($exception);
    }

    /**
     * Determine the time at which the job should timeout.
     *
     * @return \DateTime
     */
    public function retryUntil()
    {
        return now()->addMinutes(5); // Give up after 5 minutes total
    }
}
