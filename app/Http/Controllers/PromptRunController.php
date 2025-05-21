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
        ]);

        $providers = $validated['providers'] ?? ['openai'];
        $teamId = Auth::user()->current_team_id;
        
        // Create the job
        $job = new RunPromptJob($prompt, $providers, $teamId);

        // Dispatch the job with tracking
        $jobStatus = $this->jobDispatcher->dispatch($prompt, $job);
        
        // Return the prompt and job status
        return response()->json([
            'prompt' => $prompt,
            'job_status' => $jobStatus
        ]);
    }
}
