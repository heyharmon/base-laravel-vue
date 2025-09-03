<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Team;
use App\Models\Campaign;
use App\Models\Prompt;
use App\Jobs\RunAllPromptsJob;

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
        ]);

        $providers = $validated['providers'] ?? ['openai'];
        $count = $validated['count'] ?? 1;

        // Get all prompts
        $prompts = Prompt::where('team_id', $team->id)->get();

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

        // Dispatch the job to run all prompts
        $job = new RunAllPromptsJob($prompts->first(), $team->id, $campaign->id, $providers, $count);
        $this->jobDispatcher->dispatch($prompts->first(), $job);

        return response()->json([
            'message' => 'All prompts queued for processing',
            'prompts_count' => $prompts->count(),
            'expected_jobs' => $prompts->count() * $count
        ]);
    }
}
