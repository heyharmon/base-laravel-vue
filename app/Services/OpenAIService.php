<?php

namespace App\Services;

use App\Exceptions\OpenAIException;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class OpenAIService
{
    private int $timeout;
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->timeout = config('openai.timeout', 900);
        $this->apiKey = config('openai.api_key');
        $this->baseUrl = config('openai.base_url', 'https://api.openai.com/v1');
    }

    public function createCompletion(array $payload): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout($this->timeout)
            ->post($this->baseUrl . '/chat/completions', $payload);

            return $this->handleResponse($response);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw $this->handleRequestException($e);
        }
    }

    public function checkRequestStatus(string $requestId): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ])
            ->timeout(30)
            ->get($this->baseUrl . '/requests/' . $requestId);

            return $this->handleResponse($response);
        } catch (\Illuminate\Http\Client\RequestException $e) {
            throw $this->handleRequestException($e);
        }
    }

    private function handleResponse(Response $response): array
    {
        if ($response->successful()) {
            return $response->json();
        }

        $statusCode = $response->status();
        $body = $response->json();
        $errorMessage = $body['error']['message'] ?? 'Unknown error';
        $errorCode = $body['error']['code'] ?? 'unknown';

        switch ($statusCode) {
            case 429:
                if (str_contains($errorMessage, 'Resource Unavailable')) {
                    throw new OpenAIException('Flex processing resources unavailable', 'resource_unavailable', $statusCode);
                }
                throw new OpenAIException('Rate limit exceeded', 'rate_limit', $statusCode);
            case 408:
                throw new OpenAIException('Request timeout', 'timeout', $statusCode);
            case 503:
                throw new OpenAIException('Service temporarily unavailable', 'service_unavailable', $statusCode);
            default:
                throw new OpenAIException($errorMessage, $errorCode, $statusCode);
        }
    }

    private function handleRequestException(\Illuminate\Http\Client\RequestException $e): OpenAIException
    {
        if ($e->response) {
            return $this->handleResponse($e->response);
        }

        Log::error('OpenAI request failed', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        return new OpenAIException('Network error communicating with OpenAI', 'network_error', 0);
    }

    public function buildPayload(string $prompt, array $parameters = [], bool $useFlex = false): array
    {
        $payload = [
            'model' => $parameters['model'] ?? 'gpt-5',
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => $parameters['temperature'] ?? 0.7,
            'max_tokens' => $parameters['max_tokens'] ?? 2000,
        ];

        if ($useFlex) {
            $payload['service_tier'] = 'flex';
        }

        return $payload;
    }
}
