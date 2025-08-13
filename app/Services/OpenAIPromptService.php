<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class OpenAIPromptService
{
    /**
     * The tools available for the OpenAI Chat API.
     */
    protected array $tools = [
        [
            'type' => 'function',
            'function' => [
                'name' => 'web_search',
                'description' => 'Search the web for current information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query to execute'
                        ]
                    ],
                    'required' => ['query']
                ]
            ]
        ]
    ];

    /**
     * Send a prompt to OpenAI and get the response with web search capabilities.
     *
     * @param string $promptContent The prompt content to send
     * @param string $model The OpenAI model to use (default: gpt-4o)
     * @return object Response object with content and search data
     * @throws \Exception
     */
    public function getResponse(string $promptContent, string $model = 'gpt-4o'): object
    {
        try {
            $response = OpenAI::chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $promptContent]
                ],
                'tools' => $this->tools,
                'tool_choice' => 'auto',
            ]);

            return $this->processResponse($response);
        } catch (\Exception $e) {
            Log::error('OpenAI Prompt Service: API request failed', [
                'prompt' => substr($promptContent, 0, 100) . '...',
                'model' => $model,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Process the OpenAI response and extract relevant data.
     *
     * @param mixed $response The raw OpenAI response
     * @return object Processed response with content and search data
     */
    protected function processResponse($response): object
    {
        $content = '';
        $searchData = [];
        $annotations = [];

        // Extract content from the first choice
        if (isset($response->choices[0]->message->content)) {
            $content = $response->choices[0]->message->content;
        }

        // Process tool calls if present
        if (isset($response->choices[0]->message->tool_calls)) {
            foreach ($response->choices[0]->message->tool_calls as $toolCall) {
                if ($toolCall->type === 'function' && $toolCall->function->name === 'web_search') {
                    $searchData[] = [
                        'query' => json_decode($toolCall->function->arguments, true)['query'] ?? '',
                        'results' => []
                    ];
                }
            }
        }

        // Create a response object that matches what RunPromptJob expects
        return (object) [
            'responseMessages' => [
                (object) ['content' => $content]
            ],
            'searchData' => $searchData,
            'annotations' => $annotations,
            'rawResponse' => $response, // Keep the raw response for debugging
        ];
    }

    /**
     * Process annotations from the message content.
     *
     * @param array $rawAnnotations Raw annotations from OpenAI response
     * @return array Processed annotations array
     */
    protected function processAnnotations(array $rawAnnotations): array
    {
        $annotations = [];

        foreach ($rawAnnotations as $annotation) {
            if ($annotation->type === 'url_citation') {
                $annotations[] = [
                    'type' => $annotation->type,
                    'start_index' => $annotation->start_index ?? null,
                    'end_index' => $annotation->end_index ?? null,
                    'url' => $annotation->url ?? null,
                    'title' => $annotation->title ?? null,
                ];
            }
        }

        return $annotations;
    }
}
