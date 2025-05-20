<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResponseController extends Controller
{
    public function index(Prompt $prompt): JsonResponse
    {
        $responses = $prompt->responses()->get();
        
        return response()->json($responses);
    }

    public function show(Prompt $prompt, Response $response): JsonResponse
    {
        return response()->json($response);
    }

    public function store(Request $request, Prompt $prompt): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string',
            'model' => 'required|string',
            'content' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $response = $prompt->responses()->create($validated);
        
        return response()->json($response, 201);
    }

    public function destroy(Prompt $prompt, Response $response): JsonResponse
    {
        $response->delete();
        
        return response()->json(null, 204);
    }
}
