<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Jobs\RunPromptJob;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;

class PromptRunController extends Controller
{
    public function store(Request $request, Prompt $prompt): JsonResponse
    {
        $validated = $request->validate([
            'count' => 'nullable|integer|min:1|max:5',
            'use_flex_processing' => 'boolean',
            'parameters' => 'array',
            'parameters.temperature' => 'numeric|min:0|max:2',
            'parameters.max_tokens' => 'integer|min:1|max:4000',
        ]);

        $count = $validated['count'] ?? 1;
        $useFlex = $validated['use_flex_processing'] ?? false;
        $parameters = $validated['parameters'] ?? [];
        $teamId = Auth::user()->current_team_id;

        $team = Team::find($teamId);
        if (($remaining = $team->responsesRemaining()) !== null && $remaining < $count) {
            return response()->json(['message' => 'Responses limit reached', 'remaining' => $remaining], 403);
        }

        $queued = 0;
        for ($i = 0; $i < $count; $i++) {
            $response = $prompt->responses()->create([
                'provider' => 'openai',
                'model' => 'gpt-5',
                'use_flex_processing' => $useFlex,
                'parameters' => $parameters,
                'status' => 'pending',
            ]);

            RunPromptJob::dispatch($response);
            $queued++;
        }

        return response()->json([
            'prompt' => $prompt,
            'queued_jobs' => $queued,
        ]);
    }
}
