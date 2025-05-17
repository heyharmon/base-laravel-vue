<?php

namespace App\Tools;

use Prism\Prism\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SearchToolFirecrawl extends Tool
{
    public function __construct()
    {
        $this
            ->as('search')
            ->for('useful when you need to search for current information, news, or facts on the internet')
            ->withStringParameter('query', 'Detailed search query. Best to search one topic at a time.')
            ->using($this);
    }

    public function __invoke(string $query): string
    {
        $response = Http::withToken(config('services.firecrawl.api_key'))
            ->post('https://api.firecrawl.dev/v1/search', [
                'query' => $query,
                'limit' => 1,
                'lang' => 'en',
                'country' => 'us',
        ]);

        // Log::info($response->json());

        $results = collect($response->json('results'));

        $formattedResults = $results->map(function ($result) {
            return [
                'title' => $result['title'] ?? '',
                'link' => $result['url'] ?? '',
                'snippet' => $result['snippet'] ?? '',
            ];
        })->take(4);

        return view('prompts.search-tool-results', [
            'results' => $formattedResults,
        ])->render();
    }
}
