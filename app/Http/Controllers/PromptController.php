<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PromptController extends Controller
{
    public function index(): JsonResponse
    {
        $prompts = Prompt::withCount('keywords')->get();
        
        return response()->json($prompts);
    }

    public function show(Prompt $prompt): JsonResponse
    {
        $prompt->load(['keywords' => function($query) {
            $query->withPivot('count', 'last_found_at');
        }]);
        
        return response()->json($prompt);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $prompt = Prompt::create($validated);
        
        return response()->json($prompt, 201);
    }

    public function update(Request $request, Prompt $prompt): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $prompt->update($validated);
        
        return response()->json($prompt);
    }

    public function destroy(Prompt $prompt): JsonResponse
    {
        $prompt->delete();
        
        return response()->json(null, 204);
    }
}
