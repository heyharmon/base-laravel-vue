<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Services\PromptRunnerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromptRunController extends Controller
{
    protected PromptRunnerService $promptRunnerService;

    public function __construct(PromptRunnerService $promptRunnerService)
    {
        $this->promptRunnerService = $promptRunnerService;
    }

    public function store(Request $request, Prompt $prompt): JsonResponse
    {
        // $validated = $request->validate([
        //     'providers' => 'nullable|array',
        //     'providers.*' => 'string|in:openai,anthropic,gemini,xai,deepseek',
        // ]);

        // $providers = $validated['providers'] ?? null;
        
        // $run = $this->promptRunnerService->runPrompt($prompt, ['openai', 'anthropic']);
        $run = $this->promptRunnerService->runPrompt($prompt, ['openai']);
        
        return response()->json($run, 201);
    }
}
