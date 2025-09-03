<?php

namespace App\Jobs\Concerns;

use App\Models\Prompt;
use App\Models\Response;
use App\Models\Term;
use Illuminate\Support\Facades\DB;

trait HandlesPromptResponses
{
    /**
     * Save search tool results.
     */
    private function saveSearchToolResults(object $llmResponse, Response $response): void
    {
        $searchData = [
            'annotations' => $llmResponse->annotations ?? [],
        ];

        $response->update(['search' => $searchData]);
    }

    /**
     * Check for terms in the response.
     */
    private function checkForTerms(Response $response, Prompt $prompt): void
    {
        $terms = Term::whereHas('organization', function ($query) {
            $query->where('team_id', $this->teamId)
                ->where(function ($q) {
                    $q->where('campaign_id', $this->campaignId)
                        ->orWhere(function ($subQ) {
                            $subQ->whereNull('campaign_id')->where('is_competitor', false);
                        });
                });
        })->get();

        $responseText = strtolower($response->content);
        $foundTerms = [];

        foreach ($terms as $term) {
            $termName = strtolower($term->name);

            if (str_contains($responseText, $termName)) {
                $foundTerms[] = $term->id;

                $existingRelation = $prompt->terms()->where('term_id', $term->id)->exists();

                if (!$existingRelation) {
                    $prompt->terms()->attach($term->id, [
                        'count' => 1,
                        'last_found_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $prompt->terms()->updateExistingPivot($term->id, [
                        'count' => DB::raw('count + 1'),
                        'last_found_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (!empty($foundTerms)) {
            $response->terms()->syncWithoutDetaching($foundTerms);
        }
    }
}
