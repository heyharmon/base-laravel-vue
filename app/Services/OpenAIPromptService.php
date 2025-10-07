<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;

class OpenAIPromptService
{
    /**
     * Default tools for the Responses API.
     */
    protected array $tools = [
        ['type' => 'web_search_preview'],
    ];

    /**
     * Send a prompt to OpenAI using background create so we can return quickly
     * with a response id for polling, or the completed response if it finishes immediately.
     *
     * @param string $promptContent The prompt to send
     * @param string $serviceTier Service tier: 'auto' (default) or 'flex'
     * @return object Object with content, annotations, usage, status, and id
     */
    public function getResponse(string $promptContent, string $serviceTier = 'auto'): object
    {
        $modelToUse = 'gpt-5';

        try {
            $request = [
                'model' => $modelToUse,
                'instructions' => $this->systemInstructions(),
                'input' => $promptContent,
                'tools' => $this->tools,
                'tool_choice' => 'auto',
                'store' => true,
                // Ensure the response continues generating and is retrievable even if we close the stream early
                'background' => true,
                'reasoning' => ['effort' => 'low'], // 'low', 'medium', or 'high'
                'text' => ['verbosity' => 'low'], // 'low', 'medium', or 'high'
            ];

            if ($serviceTier === 'flex') {
                $request['service_tier'] = 'flex';
            }

            // Use non-streamed background create to immediately receive an id/status
            // without relying on SSE event types that may change (e.g., response.queued).
            $response = OpenAI::responses()->create($request);

            return $this->parseResponse($response);
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            $this->logError('OpenAI API ErrorException', $e, $promptContent, $modelToUse);
            throw $e;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->logError('OpenAI HTTP RequestException', $e, $promptContent, $modelToUse);
            throw $e;
        } catch (\Exception $e) {
            $this->logError('OpenAI Prompt Service: Unexpected error', $e, $promptContent, $modelToUse);
            throw $e;
        }
    }

    protected function defaultModel(): string
    {
        // Kept for future configurability; currently unused since we enforce gpt-5
        return 'gpt-5';
    }

    /**
     * Normalize the OpenAI Responses API output into a simple object.
     */
    protected function parseResponse($response): object
    {
        $texts = [];
        $annotations = [];

        if (isset($response->output) && is_array($response->output)) {
            foreach ($response->output as $item) {
                if (($item->type ?? null) === 'message' && isset($item->content) && is_array($item->content)) {
                    foreach ($item->content as $part) {
                        if (isset($part->text) && is_string($part->text)) {
                            $texts[] = $part->text;
                        }

                        if (isset($part->annotations) && is_array($part->annotations)) {
                            $annotations = array_merge($annotations, $this->normalizeAnnotations($part->annotations));
                        }
                    }
                }
            }
        }

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

        $error = null;
        if (isset($response->error)) {
            $error = json_decode(json_encode($response->error), true);
        }

        return (object) [
            'content' => trim(implode("\n\n", $texts)),
            'annotations' => $annotations,
            'usage' => $usage,
            'raw' => $response,
            'status' => $response->status ?? 'completed',
            'id' => $response->id ?? null,
            'error' => $error,
        ];
    }

    /**
     * Retrieve a previously created response by ID.
     */
    public function retrieveResponse(string $responseId): object
    {
        try {
            $response = OpenAI::responses()->retrieve($responseId);
            return $this->parseResponse($response);
        } catch (\OpenAI\Exceptions\ErrorException $e) {
            $this->logError('OpenAI API ErrorException (retrieve)', $e, 'N/A', 'gpt-5');
            throw $e;
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->logError('OpenAI HTTP RequestException (retrieve)', $e, 'N/A', 'gpt-5');
            throw $e;
        } catch (\Exception $e) {
            $this->logError('OpenAI Prompt Service: Unexpected error (retrieve)', $e, 'N/A', 'gpt-5');
            throw $e;
        }
    }

    /**
     * System instructions for prompt runs.
     */
    protected function systemInstructions(): string
    {
        return join("\n", [
            'Never ask the user follow-up questions.',
            'Always answer in a single response without requesting clarification.',
        ]);
    }

    /**
     * Convert raw annotation structures to a simple array.
     */
    protected function normalizeAnnotations(array $rawAnnotations): array
    {
        $result = [];
        foreach ($rawAnnotations as $annotation) {
            $type = $annotation->type ?? null;
            if ($type === 'url_citation') {
                $result[] = [
                    'type' => $type,
                    'start_index' => $annotation->start_index ?? null,
                    'end_index' => $annotation->end_index ?? null,
                    'url' => $annotation->url ?? null,
                    'title' => $annotation->title ?? null,
                ];
            }
        }
        return $result;
    }

    protected function logError(string $label, \Exception $e, string $promptContent, string $model): void
    {
        $context = [
            'prompt_preview' => substr($promptContent, 0, 100) . '...',
            'model' => $model,
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'error_type' => get_class($e),
        ];

        Log::error($label, $context);
    }
}
