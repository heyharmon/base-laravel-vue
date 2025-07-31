<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Jobs\RunPromptJob;
use App\Services\JobDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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
        ]);

        $providers = $validated['providers'] ?? ['openai'];
        $count = $validated['count'] ?? 1;
        $teamId = Auth::user()->current_team_id;
        
        if ($count === 1) {
            // Create a single job
            $job = new RunPromptJob($prompt, $providers, $teamId, $prompt->campaign_id);
            
            // Dispatch the job with tracking
            $jobStatus = $this->jobDispatcher->dispatch($prompt, $job);
            
            return response()->json([
                'prompt' => $prompt,
                'job_status' => $jobStatus
            ]);
        } else {
            // Create multiple jobs for batch processing
            $jobs = [];
            for ($i = 0; $i < $count; $i++) {
                $jobs[] = new RunPromptJob($prompt, $providers, $teamId, $prompt->campaign_id);
            }
            
            // Dispatch as a batch with tracking
            $batch = $this->jobDispatcher->dispatchBatch($prompt, $jobs, [
                'name' => "Prompt Run Batch ({$count}x)",
                'allowFailures' => true
            ]);
            
            return response()->json([
                'prompt' => $prompt,
                'batch' => $batch
            ]);
        }
    }
}
