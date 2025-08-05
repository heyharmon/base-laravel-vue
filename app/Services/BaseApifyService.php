<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BaseApifyService
{
    protected string $apiToken;
    protected string $baseUrl = 'https://api.apify.com/v2';

    public function __construct()
    {
        $this->apiToken = config('services.apify.token');
        if (!$this->apiToken) {
            throw new \Exception('Apify API token not configured');
        }
    }

    protected function makeRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->baseUrl . $endpoint;
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiToken,
            'Content-Type' => 'application/json',
        ])->timeout(30)->$method($url, $data);
        if (!$response->successful()) {
            Log::error('Apify API request failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            throw new \Exception('Apify API request failed: ' . $response->body());
        }
        return $response;
    }

    public function runActor(string $actorId, array $input): array
    {
        $response = $this->makeRequest('POST', "/acts/{$actorId}/runs", $input);
        return $response->json()['data'];
    }

    public function getRunStatus(string $runId): array
    {
        $response = $this->makeRequest('GET', "/actor-runs/{$runId}");
        return $response->json()['data'];
    }

    public function getRunDataset(string $runId): array
    {
        $run = $this->getRunStatus($runId);
        if (!isset($run['defaultDatasetId'])) {
            return [];
        }
        $response = $this->makeRequest('GET', "/datasets/{$run['defaultDatasetId']}/items");
        return $response->json();
    }
}
