<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Team;
use App\Models\Campaign;
use App\Models\Prompt;
use App\Jobs\RunPromptJob;

class PromptRunBatchController extends Controller
{
    protected $jobDispatcher;

    public function __construct(JobDispatcherService $jobDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
    }

    public function store(Request $request, Team $team, Campaign $campaign): JsonResponse
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
        // Always use Flex pricing for batch runs
        // $serviceTier = 'flex';
        $serviceTier = null;

        // Get all prompts for this team and campaign
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

        // Queue independent jobs for each prompt (no batches)
        $queued = 0;
        foreach ($prompts as $prompt) {
            for ($i = 0; $i < $count; $i++) {
                $job = new RunPromptJob($prompt, $providers, $team->id, $campaign->id, $serviceTier);
                $this->jobDispatcher->dispatch($prompt, $job);
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
