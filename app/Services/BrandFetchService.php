<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class BrandFetchService
{
    /**
     * The BrandFetch API base URL
     */
    protected string $baseUrl = 'https://api.brandfetch.io/v2';

    /**
     * The BrandFetch API key
     */
    protected string $apiKey;

    /**
     * Create a new BrandFetch service instance.
     */
    public function __construct()
    {
        $this->apiKey = config('services.brandfetch.api_key');
    }

    /**
     * Search for brands by name
     *
     * @param string $query
     * @return array
     * @throws RequestException
     */
    public function searchBrands(string $query): array
    {
        try {
            $response = Http::get($this->baseUrl . '/search/' . $query, [
                'c' => $this->apiKey,
            ]);

            return $response->json();

        } catch (\Exception $e) {
			Log::error('BrandFetch API error', [
                'message' => $e->getMessage(),
                'api_key_length' => strlen($this->apiKey),
            ]);
            throw $e;
        }
    }
}
