<?php

namespace App\Tools;

use App\Models\Prompt;
use App\Models\Article;
use Illuminate\Support\Facades\Log;

class FetchPromptWithResponsesTool
{
    /**
     * Get the schema definition for the fetch_prompt_with_responses tool.
     */
    public static function getSchema(): array
    {
        return [
            'type' => 'function',
            'name' => 'fetch_prompt_with_responses',
            'description' => 'Fetch a prompt with its associated recent responses',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'prompt_id' => [
                        'type'        => 'integer',
                        'description' => 'The ID of the prompt to fetch. If not provided, uses the current article\'s prompt_id.'
                    ]
                ],
                'required' => ['prompt_id']
            ]
        ];
    }

    /**
     * Execute the fetch_prompt_with_responses tool.
     * Fetches a prompt with its associated recent responses.
     */
    public function execute(array $arguments, ?Article $currentArticle = null): array
    {
        $promptId = $arguments['prompt_id'] ?? ($currentArticle ? $currentArticle->prompt_id : null);

        if (!$promptId) {
            Log::error('Cannot fetch prompt: No prompt ID provided.');
            return [
                'success' => false,
                'message' => 'No prompt ID provided to fetch.'
            ];
        }

        try {
            $prompt = Prompt::with(['responses' => function ($query) {
                $query->latest()->limit(5);
            }])->find($promptId);

            if (!$prompt) {
                return [
                    'success' => false,
                    'message' => 'Prompt not found.'
                ];
            }

            return [
                'success'    => true,
                'prompt'     => [
                    'id'          => $prompt->id,
                    'name'        => $prompt->name,
                    'content'     => $prompt->content,
                    'description' => $prompt->description,
                    'responses'   => $prompt->responses->map(function ($response) {
                        return [
                            'id'        => $response->id,
                            'content'   => $response->content,
                            'created_at' => $response->created_at->toDateTimeString()
                        ];
                    })->toArray()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching prompt with responses: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch prompt: ' . $e->getMessage()
            ];
        }
    }
}
