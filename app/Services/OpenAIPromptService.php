<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class OpenAIPromptService
{
    /**
     * The tools available for the OpenAI Response API.
     */
    protected array $tools = [
        ['type' => 'web_search']
    ];

    /**
     * Send a prompt to OpenAI and get the response with web search capabilities.
     *
     * @param string $promptContent The prompt content to send
     * @param string $model The OpenAI model to use (default: gpt-4o)
     * @return object Response object with content, usage, and search data
     * @throws \Exception
     */
    public function getResponse(string $promptContent, string $model = 'gpt-4o'): object
    {
        try {
            $response = OpenAI::responses()->create([
                'model' => $model,
                'input' => $promptContent,
                'tools' => $this->tools,
                'tool_choice' => 'auto',
                'store' => false, // We don't need to store the conversation
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
     * @return object Processed response with content, usage, and search data
     */
    protected function processResponse($response): object
    {
        $content = '';
        $usage = null;
        $searchData = [];
        $annotations = [];

        // Process all output items
        foreach ($response->output as $item) {
            switch ($item->type) {
                case 'message':
                    // Extract the assistant's message content
                    if (isset($item->content[0]->text)) {
                        $content = $item->content[0]->text;
                    }

                    // Extract annotations if present
                    if (isset($item->content[0]->annotations)) {
                        $annotations = $this->processAnnotations($item->content[0]->annotations);
                    }
                    break;

                case 'function_call':
                    // Handle any other function calls if needed
                    break;
            }
        }

        // Extract usage information if available
        if (isset($response->usage)) {
            $usage = [
                'input_tokens' => $response->usage->input_tokens ?? 0,
                'output_tokens' => $response->usage->output_tokens ?? 0,
                'total_tokens' => $response->usage->total_tokens ?? 0,
            ];
        }

        // Create a response object that matches what RunPromptJob expects
        return (object) [
            'responseMessages' => [
                (object) ['content' => $content]
            ],
            'usage' => $usage,
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
