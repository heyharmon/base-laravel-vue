<?php

namespace App\Jobs;

use App\Models\Prompt;
use App\Models\Response;
use App\Models\Keyword;
use App\Tools\SearchApiTool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Throwable;

class RunPromptJob extends TrackableJob
{
    use Batchable;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The prompt instance.
     *
     * @var \App\Models\Prompt
     */
    protected $prompt;

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
     * Available LLM providers.
     *
     * @var array
     */
    private array $availableProviders = [
        'openai' => ['gpt-4o', Provider::OpenAI],
        'anthropic' => ['claude-3-5-haiku-latest', Provider::Anthropic],
        'gemini' => ['gemini-pro', Provider::Gemini],
        'xai' => ['grok-1', Provider::XAI],
        'deepseek' => ['deepseek-chat', Provider::DeepSeek],
    ];

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Prompt  $prompt
     * @param  array  $providers
     * @param  int  $teamId
     * @return void
     */
    public function __construct(Prompt $prompt, array $providers = [], ?int $teamId = null)
    {
        $this->prompt = $prompt;
        $this->providers = $providers;
        $this->teamId = $teamId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            // Mark the job as started
            $this->markJobAsStarted();
            
            // Update progress
            $this->updateJobProgress(10, 'Fetching keywords');
            
            // Get keywords scoped to the team
            $keywords = Keyword::where('team_id', $this->teamId)->get();

            // If no providers specified, use all
            if (!$this->providers) {
                $this->providers = array_keys($this->availableProviders);
            }

            $responses = [];
            
            $this->updateJobProgress(20, 'Preparing to send prompts to LLMs');

        // Run the prompt with each provider
        $totalProviders = count($this->providers);
        $currentProvider = 0;
        
        foreach ($this->providers as $providerName) {
            $currentProvider++;
            $progress = 20 + (60 * ($currentProvider / $totalProviders));
            
            // Setup the LLM provider
            if (!isset($this->availableProviders[$providerName])) { continue; }
            [$model, $provider] = $this->availableProviders[$providerName];
            
            $this->updateJobProgress((int)$progress, "Sending prompt to {$providerName}");
            
            try {
                // Get response from the LLM
                $llm = $this->getLlmResponse($this->prompt->content, $model, $provider);
                
                // Store the LLM's response
                $response = $this->prompt->responses()->create([
                    'provider' => $providerName,
                    'model' => $model,
                    'content' => $llm->responseMessages->last()->content,
                    'metadata' => [
                        'usage' => $llm->usage ?? null,
                    ],
                ]);

                $responses[] = $response;

                // Save search tool results
                $this->saveSearchToolResults($llm->steps, $response);

                // Check for keywords in the response
                $this->checkForKeywords($keywords, $response, $this->prompt);

            } catch (\Exception $e) {
                // Log the error but continue with other providers
                Log::error('Error running prompt: ' . $e);
            }
        }
        
        $this->updateJobProgress(90, 'Processing LLM responses');
        
        // Mark the job as completed
        $this->markJobAsCompleted('Successfully generated ' . count($responses) . ' responses for prompt #' . $this->prompt->id);
        
        } catch (Throwable $exception) {
            Log::error('Prompt run failed: ' . $exception->getMessage());
            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }

    /**
     * Get response from the LLM.
     *
     * @param  string  $promptContent
     * @param  string  $model
     * @param  Provider  $provider
     * @return mixed
     */
    private function getLlmResponse(string $promptContent, string $model, Provider $provider)
    {
        $searchApiTool = new SearchApiTool();

        $response = Prism::text()
            ->using($provider, $model)
            ->withMaxSteps(10)
            ->withMessages([new UserMessage($promptContent)])
            ->withTools([$searchApiTool])
            ->withToolChoice(ToolChoice::Auto)
            ->asText();
        
        return $response;
    }

    /**
     * Save search tool results.
     *
     * @param  iterable  $steps
     * @param  Response  $response
     * @return void
     */
    private function saveSearchToolResults(iterable $steps, Response $response): void
    {
        $searchQueries = [];

        foreach ($steps as $step) {
            foreach ($step->toolResults as $tool) {
                if ($tool->toolName == 'search_api') {
                    $searchQueries[] = $tool->args['query']; 
                }
            }
        }

        $response->update(['search' => [ 'queries' => $searchQueries ]]);
    }

    /**
     * Check for keywords in the response.
     *
     * @param  iterable  $keywords
     * @param  Response  $response
     * @param  Prompt  $prompt
     * @return void
     */
    private function checkForKeywords(iterable $keywords, Response $response, Prompt $prompt): void
    {
        $responseText = strtolower($response->content);
        $foundKeywords = [];
        $mentioned = false;

        foreach ($keywords as $keyword) {
            $keywordName = strtolower($keyword->name);
            
            // Check if the keyword exists in the response
            if (str_contains($responseText, $keywordName)) {
                $foundKeywords[] = $keyword->id;
                $mentioned = true;
                
                // Update the pivot table for keyword-prompt relationship
                $pivot = $prompt->keywords()->syncWithoutDetaching([$keyword->id]);
                
                // If this is a new relationship, initialize the count
                if (isset($pivot[$keyword->id]) && $pivot[$keyword->id]['created']) {
                    $prompt->keywords()->updateExistingPivot($keyword->id, [
                        'count' => 1,
                        'last_found_at' => now(),
                    ]);
                } else {
                    // Increment the count and update last_found_at
                    $prompt->keywords()->updateExistingPivot($keyword->id, [
                        'count' => DB::raw('count + 1'),
                        'last_found_at' => now(),
                    ]);
                }
            }
        }
        
        // Attach found keywords to the response
        if (!empty($foundKeywords)) {
            $response->keywords()->syncWithoutDetaching($foundKeywords);
        }
        
        // Update the response with the mentioned flag
        $response->update(['mentioned' => $mentioned]);
    }
}
