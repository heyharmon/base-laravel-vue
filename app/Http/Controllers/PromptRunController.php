<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Jobs\RunPromptJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PromptRunController extends Controller
{

    public function store(Request $request, Prompt $prompt): JsonResponse
    {
        $validated = $request->validate([
            'providers' => 'nullable|array',
            'providers.*' => 'string|in:openai,anthropic,gemini,xai,deepseek',
        ]);

        $providers = $validated['providers'] ?? ['openai'];
        $teamId = Auth::user()->current_team_id;
        
        // Dispatch the job to run the prompt
        RunPromptJob::dispatch($prompt, $providers, $teamId);
        
        // Return the existing responses immediately
        return response()->json($prompt);
    }
}
