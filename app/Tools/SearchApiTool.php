<?php

namespace App\Tools;

use Prism\Prism\Tool;
use Illuminate\Support\Facades\Http;

class SearchApiTool extends Tool
{
    public function __construct()
    {
        $this
            ->as('search_api')
            ->for('useful when you need to search for current information, companies, or facts on Google. If you need to search for information for a given year, use the current year (' . date('Y') . ') in the query.')
            ->withStringParameter('query', 'Detailed search query. Best to search one topic at a time.')
            ->using($this);
    }

    public function __invoke(string $query): string
    {
        $response = Http::get('https://www.searchapi.io/api/v1/search', [
            'engine' => 'google',
            'q' => $query,
            'google_domain' => 'google.com',
            'gl' => 'us',
            'hl' => 'en',
            'api_key' => config('services.searchapi.api_key'),
        ]);

        $results = collect($response->json('organic_results'));

        $results->map(function ($result) {
            return [
                'title' => $result['title'] ?? '',
                'link' => $result['url'] ?? '',
                'snippet' => $result['snippet'] ?? '',
            ];
        })->take(10);

        return view('prompts.search-tool-results', [
            'results' => $results,
        ])->render();
    }
}
