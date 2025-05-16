<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KeywordController extends Controller
{
    public function index(): JsonResponse
    {
        $keywords = Keyword::withCount('prompts')->latest()->get();
        
        return response()->json($keywords);
    }

    public function show(Keyword $keyword): JsonResponse
    {
        $keyword->load(['prompts' => function($query) {
            $query->withPivot('count', 'last_found_at');
        }]);
        
        return response()->json($keyword);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:keywords,name',
        ]);

        $keyword = Keyword::create($validated);
        
        return response()->json($keyword, 201);
    }

    public function update(Request $request, Keyword $keyword): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:keywords,name,' . $keyword->id,
        ]);

        $keyword->update($validated);
        
        return response()->json($keyword);
    }

    public function destroy(Keyword $keyword): JsonResponse
    {
        $keyword->delete();
        
        return response()->json(null, 204);
    }
}
