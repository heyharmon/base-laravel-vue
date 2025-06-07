<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\JsonResponse;

class PromptExportController extends Controller
{
    /**
     * Return a prompt record by id along with its responses.
     */
    public function show(Prompt $prompt): JsonResponse
    {
        $prompt->load('responses');
        
        return response()->json($prompt);
    }
}
