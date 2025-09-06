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
            'providers' => 'nullable|array',
            'providers.*' => 'string|in:openai,anthropic,gemini,xai,deepseek',
            'count' => 'nullable|integer|min:1|max:5',
        ]);

        $providers = $validated['providers'] ?? ['openai'];
        $count = $validated['count'] ?? 1;
        $teamId = Auth::user()->current_team_id;

        $team = Team::find($teamId);
        if (($remaining = $team->responsesRemaining()) !== null && $remaining < $count) {
            return response()->json(['message' => 'Responses limit reached', 'remaining' => $remaining], 403);
        }

        // Always dispatch independent jobs (no batches)
        $queued = 0;
        for ($i = 0; $i < $count; $i++) {
            dispatch(new RunPromptJob($prompt, $providers, $teamId, $prompt->campaign_id, null));
            $queued++;
        }

        return response()->json([
            'prompt' => $prompt,
            'queued_jobs' => $queued,
        ]);
    }
}
