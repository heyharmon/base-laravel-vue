<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Jobs\RunPromptJob;
use App\Services\JobDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PromptRunBatchController extends Controller
{
    protected $jobDispatcher;
    
    public function __construct(JobDispatcherService $jobDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'providers' => 'nullable|array',
            'providers.*' => 'string|in:openai,anthropic,gemini,xai,deepseek',
            'count' => 'nullable|integer|min:1|max:3',
        ]);

        $providers = $validated['providers'] ?? ['openai'];
        $count = $validated['count'] ?? 1;
        $teamId = Auth::user()->current_team_id;
        
        // Get all prompts
        $prompts = Prompt::where('team_id', $teamId)->get();
        
        if ($prompts->isEmpty()) {
            return response()->json([
                'message' => 'No prompts found to run'
            ], 404);
        }
        
        // Create jobs for all prompts
        $jobs = [];
        foreach ($prompts as $prompt) {
            // For each prompt, create the specified number of jobs
            for ($i = 0; $i < $count; $i++) {
                $jobs[] = new RunPromptJob($prompt, $providers, $teamId);
            }
        }
        
        // Dispatch as a single batch with tracking using all prompts as models
        $batch = $this->jobDispatcher->dispatchBatch($prompts, $jobs, [
            'name' => "All Prompts Batch ({$count}x each)",
            'allowFailures' => true
        ]);
        
        return response()->json([
            'message' => 'All prompts queued for processing',
            'batch' => $batch,
            'prompts_count' => $prompts->count(),
            'total_jobs' => count($jobs)
        ]);
    }
}
