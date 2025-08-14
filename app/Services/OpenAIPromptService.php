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
        ['type' => 'web_search_preview']
    ];

    /**
     * Send a prompt to OpenAI and get the response with web search capabilities.
     *
     * @param string $promptContent The prompt content to send
     * @param string $model The OpenAI model to use (default: gpt-5)
     * @return object Response object with content and annotations
     * @throws \Exception
     */
    public function getResponse(string $promptContent, string $model = 'gpt-5'): object
    {
        try {
            $response = OpenAI::responses()->create([
                'model' => $model,
                'input' => $promptContent,
                'reasoning' => ['effort' => 'low'], // Options: minimal, low, medium (default), high
                'text' => ['verbosity' => 'medium'], // Options: low, medium (default), high
                'tools' => $this->tools,
                'tool_choice' => 'auto',
                'store' => true,
            ]);

            // Log::info('OpenAI response:', [
            //     'response' => $response
            // ]);

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
     * @return object Processed response with content and annotations
     */
    protected function processResponse($response): object
    {
        $content = '';
        $annotations = [];

        // Process all output items
        if (isset($response->output) && is_array($response->output)) {
            foreach ($response->output as $item) {
                switch ($item->type) {
                    case 'message':
                        // Extract the assistant's message content
                        if (isset($item->content) && is_array($item->content) && count($item->content) > 0) {
                            $firstContent = $item->content[0];
                            if (isset($firstContent->text)) {
                                $content = $firstContent->text;
                            }

                            // Extract annotations if present
                            if (isset($firstContent->annotations) && is_array($firstContent->annotations)) {
                                $annotations = $this->processAnnotations($firstContent->annotations);
                            }
                        }
                        break;

                    case 'reasoning':
                        // GPT-5 reasoning - we don't need to process this for our use case
                        break;

                    case 'function_call':
                        // Handle any function calls if needed
                        break;
                }
            }
        }


        // Extract usage information
        $usage = null;
        if (isset($response->usage)) {
            $usage = [
                'input_tokens' => $response->usage->inputTokens ?? null,
                'input_tokens_details' => [
                    'cached_tokens' => $response->usage->inputTokensDetails->cachedTokens ?? null,
                ],
                'output_tokens' => $response->usage->outputTokens ?? null,
                'output_tokens_details' => [
                    'reasoning_tokens' => $response->usage->outputTokensDetails->reasoningTokens ?? null,
                ],
                'total_tokens' => $response->usage->totalTokens ?? null,
            ];
        }

        // Log::info('rawResponse', [
        //     'response' => $response,
        // ]);

        // Create a response object that matches what RunPromptJob expects
        return (object) [
            'responseMessages' => [
                (object) ['content' => $content]
            ],
            'annotations' => $annotations,
            'usage' => $usage,
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
