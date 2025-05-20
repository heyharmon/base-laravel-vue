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
        $responses = Response::where('prompt_id', $prompt->id)
            ->whereHas('keywords', function($keywords) use ($keyword) {
                $keywords->where('keywords.id', $keyword->id);
            })
            ->latest()
            ->get();
        
        return response()->json($responses);
    }
}
