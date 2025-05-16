<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KeywordResponsesController extends Controller
{
    public function index(Keyword $keyword, Prompt $prompt): JsonResponse
    {
        // Get responses containing the keyword for this prompt
        $responses = Response::whereHas('run', function($query) use ($prompt, $keyword) {
            $query->where('prompt_id', $prompt->id)
                  ->whereHas('keywords', function($q) use ($keyword) {
                      $q->where('keywords.id', $keyword->id);
                  });
        })->with('run')->get();
        
        return response()->json($responses);
    }
}
