<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\JsonResponse;

class PromptExportController extends Controller
{
    /**
     * Return a prompt record by id along with its responses.
     * Only returns content, mentions_percentage, and filtered responses properties.
     */
    public function show(Prompt $prompt): JsonResponse
    {
        $prompt->load('responses');
        
        $filteredResponses = $prompt->responses->map(function ($response) {
            return [
                'provider' => $response->provider,
                'model' => $response->model,
                'content' => $response->content,
                'search' => $response->search
            ];
        });
        
        return response()->json([
            'content' => $prompt->content,
            'mentions_percentage' => $prompt->mentions_percentage,
            'responses' => $filteredResponses
        ]);
    }
}
