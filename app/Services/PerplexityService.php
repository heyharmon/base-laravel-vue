<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class PerplexityService
{
    /**
     * The Perplexity API key.
     *
     * @var string
     */
    protected $apiKey;

    /**
     * The base URL for the Perplexity API.
     *
     * @var string
     */
    protected $baseUrl = 'https://api.perplexity.ai';

    /**
     * Create a new PerplexityService instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->apiKey = Config::get('services.perplexity.key');

        if (!$this->apiKey) {
            throw new \Exception('Perplexity API key not configured');
        }
    }

    /**
     * Get the HTTP client with authorization headers.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    protected function getHttpClient()
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Create an async chat completion request.
     *
     * @param  array  $messages  The messages to send to the API
     * @param  string  $model  The model to use (default: sonar-deep-research)
     * @param  float  $temperature  The temperature parameter (default: 0.7)
     * @param  int  $maxTokens  The maximum number of tokens to generate (default: 10000)
     * @param  string  $reasoningEffort  The reasoning effort (default: medium)
     * @return array  The API response as an array
     * @throws \Exception  If the API request fails
     */
    public function createAsyncChatCompletion(
        array $messages,
        string $model = 'sonar-deep-research',
        float $temperature = 0.7,
        int $maxTokens = 10000,
        string $reasoningEffort = 'medium'
    ) {
        $response = $this->getHttpClient()->post($this->baseUrl . '/async/chat/completions', [
            'request' => [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'reasoning_effort' => $reasoningEffort
            ]
        ]);

        if ($response->successful()) {
            return $response->json();
        } else {
            $errorResponse = $response->json()['error'] ?? 'Unknown API error';
            $errorMessage = is_array($errorResponse) ? json_encode($errorResponse) : $errorResponse;
            throw new \Exception('Failed to create async chat completion: ' . $errorMessage);
        }
    }

    /**
     * Get the status of an async chat completion.
     *
     * @param  string  $requestId  The request ID to check
     * @return array  The API response as an array
     * @throws \Exception  If the API request fails
     */
    public function getAsyncChatCompletionStatus(string $requestId)
    {
        $response = $this->getHttpClient()->get($this->baseUrl . '/async/chat/completions/' . $requestId);

        if ($response->successful()) {
            return $response->json();
        } else {
            $errorResponse = $response->json()['error'] ?? 'Unknown API error';
            $errorMessage = is_array($errorResponse) ? json_encode($errorResponse) : $errorResponse;
            throw new \Exception('Failed to check async chat completion status: ' . $errorMessage);
        }
    }
}
