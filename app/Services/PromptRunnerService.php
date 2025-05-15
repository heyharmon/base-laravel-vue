<?php

namespace App\Services;

use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Run;
use Illuminate\Support\Facades\DB;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\UserMessage;

class PromptRunnerService
{
    private array $providers = [
        'openai' => ['gpt-4o', Provider::OpenAI],
        'anthropic' => ['claude-3-opus', Provider::Anthropic],
        'gemini' => ['gemini-pro', Provider::Gemini],
        'xai' => ['grok-1', Provider::XAI],
        'deepseek' => ['deepseek-chat', Provider::DeepSeek],
    ];

    public function runPrompt(Prompt $prompt, array $selectedProviders): Run
    {
        // Create a new run record
        $run = Run::create([
            'prompt_id' => $prompt->id,
            'run_date' => now(),
        ]);

        // If no providers specified, use all
        if (!$selectedProviders) {
            $selectedProviders = array_keys($this->providers);
        }

        // Get all keywords to check for
        $keywords = Keyword::all();

        // Run the prompt with each provider
        foreach ($selectedProviders as $providerName) {
            if (!isset($this->providers[$providerName])) {
                continue;
            }

            [$model, $provider] = $this->providers[$providerName];
            
            try {
                // Get response from the LLM
                $llmResponse = $this->getLlmResponse($prompt->content, $model, $provider);
                
                // Store the response
                $response = $run->responses()->create([
                    'provider' => $providerName,
                    'model' => $model,
                    'content' => $llmResponse->text,
                    'metadata' => [
                        'usage' => $llmResponse->usage ?? null,
                    ],
                ]);
                
                // Check for keywords in the response
                $this->checkForKeywords($keywords, $llmResponse->text, $run, $prompt);
            } catch (\Exception $e) {
                // Log the error but continue with other providers
                $run->responses()->create([
                    'provider' => $providerName,
                    'model' => $model,
                    'content' => 'Error: ' . $e->getMessage(),
                    'metadata' => [
                        'error' => true,
                        'message' => $e->getMessage(),
                    ],
                ]);
            }
        }

        return $run->fresh(['responses', 'keywords']);
    }

    private function getLlmResponse(string $promptContent, string $model, Provider $provider)
    {
        return Prism::text()
            ->using($provider, $model)
            ->withMessages([new UserMessage($promptContent)])
            ->asText();
    }

    private function checkForKeywords(iterable $keywords, string $responseText, Run $run, Prompt $prompt): void
    {
        $responseText = strtolower($responseText);
        $foundKeywords = [];

        foreach ($keywords as $keyword) {
            $keywordName = strtolower($keyword->name);
            
            // Check if the keyword exists in the response
            if (str_contains($responseText, $keywordName)) {
                $foundKeywords[] = $keyword->id;
                
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
    }
}
