<?php

namespace App\Services;

use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Tools\SearchTool;
use App\Tools\SearchApiTool;
use App\Models\Run;
use App\Models\Response;
use App\Models\Prompt;
use App\Models\Keyword;
use Illuminate\Support\Facades\Auth;

class PromptRunnerService
{
    private array $providers = [
        'openai' => ['gpt-4o', Provider::OpenAI],
        'anthropic' => ['claude-3-5-haiku-latest', Provider::Anthropic],
        'gemini' => ['gemini-pro', Provider::Gemini],
        'xai' => ['grok-1', Provider::XAI],
        'deepseek' => ['deepseek-chat', Provider::DeepSeek],
    ];

    public function runPrompt(Prompt $prompt, array $selectedProviders = [])
    {
        // Create a new run record
        $run = Run::create([
            'prompt_id' => $prompt->id,
            'run_date' => now(),
        ]);

        // Get keywords scoped to the user's current team
        $teamId = Auth::user()->current_team_id;
        $keywords = Keyword::where('team_id', $teamId)->get();

        // If no providers specified, use all
        if (!$selectedProviders) {
            $selectedProviders = array_keys($this->providers);
        }

        // Run the prompt with each provider
        foreach ($selectedProviders as $providerName) {
            
            // Setup the LLM provider
            if (!isset($this->providers[$providerName])) { continue; }
            [$model, $provider] = $this->providers[$providerName];
            
            try {
                // Get response from the LLM
                $llm = $this->getLlmResponse($prompt->content, $model, $provider);
                
                // dd($llm->steps);
                
                // Store the LLM's response
                $response = $run->responses()->create([
                    'provider' => $providerName,
                    'model' => $model,
                    'content' => $llm->responseMessages->last()->content,
                    'metadata' => [
                        'usage' => $llm->usage ?? null,
                    ],
                ]);

                // Save search tool results
                $this->saveSearchToolResults($llm->steps, $response);

                // Check for keywords in the response
                $this->checkForKeywords($keywords, $response, $run, $prompt);

            } catch (\Exception $e) {
                // Log the error but continue with other providers
                Log::error('Error running prompt: ' . $e);
            }
        }

        return $run->fresh(['responses', 'keywords']);
    }

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

    private function checkForKeywords(iterable $keywords, Response $response, Run $run, Prompt $prompt): void
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
        
        // Attach found keywords to the run
        if (!empty($foundKeywords)) {
            $run->keywords()->syncWithoutDetaching($foundKeywords);
        }
        
        // Update the response with the mentioned flag
        $response->update(['mentioned' => $mentioned]);
    }
}
