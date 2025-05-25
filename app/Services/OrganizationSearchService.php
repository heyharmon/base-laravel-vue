<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OrganizationSearchService
{
    /**
     * Search for companies using the People Data Labs Company Search API.
     *
     * @param string $query
     * @param int $size
     * @return array
     */
    public function search(string $query, int $size = 10): array
    {
        $payload = [
            'size' => $size,
            'query' => [
                'bool' => [
                    'should' => [
                        ['match' => ['name' => $query]],
                        ['term'  => ['website' => $query]],
                    ],
                ],
            ],
        ];

        $response = Http::withHeaders([
            'X-Api-Key' => config('services.peopledatalabs.api_key'),
        ])->withBody(json_encode($payload), 'application/json')
            ->get('https://api.peopledatalabs.com/v5/company/search');

        if (! $response->successful()) {
            return [];
        }

        return $response->json('data') ?? [];
    }
}

