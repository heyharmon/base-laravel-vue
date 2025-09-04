<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Jobs\RunPromptJob;
use App\Services\JobDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Team;

class PromptRunController extends Controller
{
    protected $jobDispatcher;
    
    public function __construct(JobDispatcherService $jobDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
    }

    public function store(Request $request, Prompt $prompt): JsonResponse
    {
        $validated = $request->validate([
            'providers' => 'nullable|array',
            'providers.*' => 'string|in:openai,anthropic,gemini,xai,deepseek',
            'count' => 'nullable|integer|min:1|max:5',
            'flex' => 'nullable|boolean',
            'service_tier' => 'nullable|string|in:flex',
        ]);

        $providers = $validated['providers'] ?? ['openai'];
        $count = $validated['count'] ?? 1;
        $teamId = Auth::user()->current_team_id;

        $team = Team::find($teamId);
        if (($remaining = $team->responsesRemaining()) !== null && $remaining < $count) {
            return response()->json(['message' => 'Responses limit reached', 'remaining' => $remaining], 403);
        }

        // Determine service tier
        $serviceTier = null;
        if (($validated['service_tier'] ?? null) === 'flex' || ($validated['flex'] ?? false)) {
            $serviceTier = 'flex';
        }

        // Always dispatch independent jobs (no batches)
        $jobStatuses = [];
        for ($i = 0; $i < $count; $i++) {
            $job = new RunPromptJob($prompt, $providers, $teamId, $prompt->campaign_id, $serviceTier);
            $jobStatuses[] = $this->jobDispatcher->dispatch($prompt, $job);
        }

        return response()->json([
            'prompt' => $prompt,
            'job_statuses' => $jobStatuses,
        ]);
    }
}
