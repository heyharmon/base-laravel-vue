<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class KeywordController extends Controller
{
    public function index(): JsonResponse
    {
        // TODO: Change this if adding projects model
        $teamId = Auth::user()->current_team_id;
        $keywords = Keyword::where('team_id', $teamId)
            ->withCount('prompts')
            ->latest()
            ->get();
        
        return response()->json($keywords);
    }

    public function show(Keyword $keyword): JsonResponse
    {
        // Check if keyword belongs to user's current team
        // TODO: Change this if adding projects model
        if ($keyword->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Not found'], 404);
        }
        
        $keyword->load(['prompts' => function($query) {
            $query->withPivot('count', 'last_found_at');
        }]);
        
        return response()->json($keyword);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);
        
        // Add the team_id to the validated data
        // TODO: Change this if adding projects model
        $validated['team_id'] = Auth::user()->current_team_id;

        $keyword = Keyword::create($validated);
        
        return response()->json($keyword, 201);
    }

    public function update(Request $request, Keyword $keyword): JsonResponse
    {
        // Check if keyword belongs to user's current team
        // TODO: Change this if adding projects model
        if ($keyword->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Not found'], 404);
        }
        
        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        $keyword->update($validated);
        
        return response()->json($keyword);
    }

    public function destroy(Keyword $keyword): JsonResponse
    {
        // Check if keyword belongs to user's current team
        // TODO: Change this if adding projects model
        if ($keyword->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Not found'], 404);
        }
        
        $keyword->delete();
        
        return response()->json(null, 204);
    }
}
