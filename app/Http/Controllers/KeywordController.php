<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class KeywordController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;
        $organizationId = $request->input('organization_id');
        
        if (!$organizationId) {
            return response()->json(['message' => 'Organization ID is required'], 400);
        }
        
        // Verify the organization belongs to the user's team
        $organization = Organization::find($organizationId);
        if (!$organization || $organization->team_id !== $teamId) {
            return response()->json(['message' => 'Organization not found'], 404);
        }
        
        $keywords = Keyword::where('organization_id', $organizationId)
            ->withCount('prompts')
            ->latest()
            ->get();
        
        return response()->json($keywords);
    }

    public function show(Keyword $keyword): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;
        
        // Check if keyword belongs to an organization owned by user's current team
        if (!$keyword->organization || $keyword->organization->team_id !== $teamId) {
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
            'organization_id' => 'required|exists:organizations,id',
        ]);
        
        $teamId = Auth::user()->current_team_id;
        
        // Verify the organization belongs to the user's team
        $organization = Organization::find($validated['organization_id']);
        if (!$organization || $organization->team_id !== $teamId) {
            return response()->json(['message' => 'Organization not found'], 404);
        }
        
        // Add the team_id to the validated data
        $validated['team_id'] = $teamId;

        $keyword = Keyword::create($validated);
        
        return response()->json($keyword, 201);
    }

    public function update(Request $request, Keyword $keyword): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;
        
        // Check if keyword belongs to an organization owned by user's current team
        if (!$keyword->organization || $keyword->organization->team_id !== $teamId) {
            return response()->json(['message' => 'Not found'], 404);
        }
        
        $validated = $request->validate([
            'name' => 'required|string',
            'organization_id' => 'sometimes|exists:organizations,id',
        ]);
        
        // If organization_id is provided, verify it belongs to the user's team
        if (isset($validated['organization_id'])) {
            $organization = Organization::find($validated['organization_id']);
            if (!$organization || $organization->team_id !== $teamId) {
                return response()->json(['message' => 'Organization not found'], 404);
            }
        }

        $keyword->update($validated);
        
        return response()->json($keyword);
    }

    public function destroy(Keyword $keyword): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;
        
        // Check if keyword belongs to an organization owned by user's current team
        if (!$keyword->organization || $keyword->organization->team_id !== $teamId) {
            return response()->json(['message' => 'Not found'], 404);
        }
        
        $keyword->delete();
        
        return response()->json(null, 204);
    }
}
