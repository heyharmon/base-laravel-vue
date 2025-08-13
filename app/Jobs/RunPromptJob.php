<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Batchable;
use App\Services\JobDispatcherService;
use App\Services\OpenAIPromptService;
use App\Models\Response;
use App\Models\Prompt;
use App\Models\Term;

class RunPromptJob extends TrackableJob
{
    use Batchable;

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
        try {
            if ($this->isCancelled()) {
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
                throw new \Exception('No supported providers specified');
            }

            $responses = [];

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
                    // Get response from the LLM
                    $llm = $this->getLlmResponse($this->prompt->content, $model, $providerName);

                    // Store the LLM's response
                    $response = $this->prompt->responses()->create([
                        'provider' => $providerName,
                        'model' => $model,
                        'content' => $llm->responseMessages[0]->content ?? '',
                    ]);

                    $responses[] = $response;

                    // Save search tool results
                    $this->saveSearchToolResults($llm, $response);

                    // Check for terms in the response
                    $this->checkForTerms($response, $this->prompt);
                } catch (\Exception $e) {
                    // Log the error but continue with other providers
                    Log::error('Error running prompt: ' . $e->getMessage(), [
                        'provider' => $providerName,
                        'prompt_id' => $this->prompt->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $this->updateJobProgress(90, 'Processing LLM response');

            // Find competitors in response if this is the first response to this prompt
            if ($this->prompt->responses()->count() == 1) {
                $jobDispatcher->dispatch($this->prompt, new FindCompetitorsInResponseJob($this->prompt));
            }

            // Mark the job as completed
            $this->markJobAsCompleted('Successfully generated ' . count($responses) . ' responses for prompt');
        } catch (Throwable $exception) {
            Log::error('Prompt run failed: ' . $exception->getMessage());
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
     * Save search tool results.
     *
     * @param  object  $llmResponse
     * @param  Response  $response
     * @return void
     */
    private function saveSearchToolResults(object $llmResponse, Response $response): void
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
}
