<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class OrganizationSearchService
{
    public function search(string $term, int $size = 10): array
    {
        $size = min($size, 10);

        $response = Http::withHeaders([
            'X-Api-Key' => config('services.pdl.api_key'),
        ])->post('https://api.peopledatalabs.com/v5/company/search', [
            'size' => $size,
            'query' => [
                'bool' => [
                    'should' => [
                        ['match_phrase_prefix' => ['name' => $term]],
                        ['match_phrase_prefix' => ['website' => $term]],
                    ],
                ],
            ],
        ]);

        if ($response->failed()) {
            return [];
        }

        return $response->json('data') ?? [];
    }
}

