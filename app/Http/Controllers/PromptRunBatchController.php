<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Team;
use App\Models\Campaign;
use App\Models\Prompt;
use App\Jobs\RunPromptJob;

class PromptRunBatchController extends Controller
{
    public function store(Request $request, Team $team, Campaign $campaign): JsonResponse
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

        $prompts = Prompt::where('team_id', $team->id)
            ->where('campaign_id', $campaign->id)
            ->get();

        if ($prompts->isEmpty()) {
            return response()->json([
                'message' => 'No prompts found to run'
            ], 404);
        }

        $total = $prompts->count() * $count;
        if (($remaining = $team->responsesRemaining()) !== null && $remaining < $total) {
            return response()->json([
                'message' => 'Responses limit reached',
                'remaining' => $remaining
            ], 403);
        }

        $queued = 0;
        foreach ($prompts as $prompt) {
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
        }

        return response()->json([
            'message' => 'All prompts queued for processing',
            'prompts_count' => $prompts->count(),
            'queued_jobs' => $queued,
        ]);
    }
}
