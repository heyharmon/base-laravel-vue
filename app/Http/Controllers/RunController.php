<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RunController extends Controller
{
    public function index(): JsonResponse
    {
        $runs = Run::with('prompt')->latest('run_date')->get();
        
        return response()->json($runs);
    }

    public function show(Run $run): JsonResponse
    {
        $run->load(['prompt', 'responses', 'keywords']);
        
        return response()->json($run);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt_id' => 'required|exists:prompts,id',
            'run_date' => 'nullable|date',
        ]);

        if (!isset($validated['run_date'])) {
            $validated['run_date'] = now();
        }

        $run = Run::create($validated);
        
        return response()->json($run, 201);
    }

    public function destroy(Run $run): JsonResponse
    {
        $run->delete();
        
        return response()->json(null, 204);
    }
}
