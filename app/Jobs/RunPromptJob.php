<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use App\Services\JobDispatcherService;
use App\Services\OpenAIPromptService;
use App\Models\Prompt;
use App\Jobs\Concerns\HandlesPromptResponses;

class RunPromptJob extends TrackableJob
{
    use Batchable, HandlesPromptResponses;

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
     * Available LLM providers and their models.
     *
     * @var array
     */
    private array $availableProviders = [
        'openai' => 'gpt-4o',
        'openai' => 'gpt-5',
    ];

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
    public function handle(JobDispatcherService $jobDispatcher)
    {
        // Log::info('RunPromptJob started', [
        //     'prompt_id' => $this->prompt->id,
        //     'providers' => $this->providers,
        //     'team_id' => $this->teamId,
        //     'campaign_id' => $this->campaignId,
        //     'job_id' => $this->job->getJobId() ?? 'unknown'
        // ]);

        try {
            if ($this->isCancelled()) {
                // Log::info('RunPromptJob cancelled', ['prompt_id' => $this->prompt->id]);
                return;
            }

            $team = \App\Models\Team::find($this->teamId);
            if ($team && ($remaining = $team->responsesRemaining()) !== null && $remaining <= 0) {
                $this->markJobAsCompleted('Responses limit reached');
                return;
            }

            // Mark the job as started
            $this->markJobAsStarted('Running a prompt');

            // If no providers specified, use OpenAI as default
            if (!$this->providers) {
                $this->providers = ['openai'];
            }

            // Filter providers to only include supported ones
            $this->providers = array_intersect($this->providers, array_keys($this->availableProviders));

            if (empty($this->providers)) {
                $error = 'No supported providers specified';
                Log::error('RunPromptJob failed: ' . $error, ['prompt_id' => $this->prompt->id]);
                throw new \Exception($error);
            }

            $responses = [];
            $providerErrors = [];

            $this->updateJobProgress(20, 'Preparing to send prompt to LLMs');

            // Run the prompt with each provider
            $totalProviders = count($this->providers);
            $currentProvider = 0;

            foreach ($this->providers as $providerName) {
                $currentProvider++;
                $progress = 20 + (60 * ($currentProvider / $totalProviders));

                // Setup the LLM provider
                if (!isset($this->availableProviders[$providerName])) {
                    continue;
                }

                $model = $this->availableProviders[$providerName];

                $this->updateJobProgress((int)$progress, 'Sending prompt "' . substr($this->prompt->content, 0, 50) . (strlen($this->prompt->content) > 50 ? '...' : '') . '" to ' . $providerName);

                try {
                    // Log::info('Calling LLM provider', [
                    //     'prompt_id' => $this->prompt->id,
                    //     'provider' => $providerName,
                    //     'model' => $model
                    // ]);

                    // Get response from the LLM
                    $llm = $this->getLlmResponse($this->prompt->content, $model, $providerName);

                    // Log::info('LLM response received', [
                    //     'prompt_id' => $this->prompt->id,
                    //     'provider' => $providerName,
                    //     'has_content' => !empty($llm->responseMessages[0]->content ?? '')
                    // ]);

                    // Store the LLM's response
                    $response = $this->prompt->responses()->create([
                        'provider' => $providerName,
                        'model' => $model,
                        'content' => $llm->responseMessages[0]->content ?? '',
                        'usage' => $llm->usage ?? null,
                    ]);

                    $responses[] = $response;

                    // Save search tool results
            $this->saveSearchToolResults($llm, $response);

                    // Check for terms in the response
                    $this->checkForTerms($response, $this->prompt);

                    // Log::info('Successfully processed LLM response', [
                    //     'prompt_id' => $this->prompt->id,
                    //     'response_id' => $response->id,
                    //     'provider' => $providerName
                    // ]);
                } catch (\Exception $e) {
                    $providerErrors[$providerName] = $e->getMessage();

                    // Log the detailed error but continue with other providers
                    Log::error('Error running prompt with provider', [
                        'provider' => $providerName,
                        'prompt_id' => $this->prompt->id,
                        'error' => $e->getMessage(),
                        'error_code' => $e->getCode(),
                        'error_type' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            // Check if all providers failed
            if (empty($responses)) {
                $error = 'All providers failed: ' . json_encode($providerErrors);
                Log::error('RunPromptJob: All providers failed', [
                    'prompt_id' => $this->prompt->id,
                    'provider_errors' => $providerErrors
                ]);
                throw new \Exception($error);
            }

            $this->updateJobProgress(90, 'Processing LLM response');

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
            $this->markJobAsCompleted('Successfully generated ' . count($responses) . ' responses for prompt');

            // Log::info('RunPromptJob completed successfully', [
            //     'prompt_id' => $this->prompt->id,
            //     'responses_count' => count($responses)
            // ]);
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
    private function getLlmResponse(string $promptContent, string $model, string $provider)
    {
        switch ($provider) {
            case 'openai':
                $service = new OpenAIPromptService();
                return $service->getResponse($promptContent, $model);

            default:
                throw new \Exception("Unsupported provider: {$provider}");
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

        // You could also notify administrators or take other cleanup actions here
        // For example: send notification, update external status, etc.
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
