<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TermResponsesController extends Controller
{
    public function index(Term $term, Prompt $prompt): JsonResponse
    {
        // Get responses containing the term for this prompt
        $responses = Response::where('prompt_id', $prompt->id)
            ->whereHas('terms', function($terms) use ($term) {
                $terms->where('terms.id', $term->id);
            })
            ->latest()
            ->get();
        
        return response()->json($responses);
    }
}
