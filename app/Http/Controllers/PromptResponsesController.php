<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\JsonResponse;

class PromptResponsesController extends Controller
{
    public function index(Prompt $prompt): JsonResponse
    {
        // Get all responses for this prompt
        $responses = Response::whereHas('run', function($run) use ($prompt) {
            $run->where('prompt_id', $prompt->id);
        })->with('run')->latest()->get();
        
        return response()->json($responses);
    }
}
