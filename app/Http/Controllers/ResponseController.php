<?php

namespace App\Http\Controllers;

use App\Models\Run;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ResponseController extends Controller
{
    public function index(Run $run): JsonResponse
    {
        $responses = $run->responses()->get();
        
        return response()->json($responses);
    }

    public function show(Run $run, Response $response): JsonResponse
    {
        return response()->json($response);
    }

    public function store(Request $request, Run $run): JsonResponse
    {
        $validated = $request->validate([
            'provider' => 'required|string',
            'model' => 'required|string',
            'content' => 'required|string',
            'metadata' => 'nullable|array',
        ]);

        $response = $run->responses()->create($validated);
        
        return response()->json($response, 201);
    }

    public function destroy(Run $run, Response $response): JsonResponse
    {
        $response->delete();
        
        return response()->json(null, 204);
    }
}
