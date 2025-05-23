<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrganizationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $organizations = request()->user()->currentTeam->organizations;
        
        return response()->json($organizations);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'website' => 'nullable|string|max:255',
            'founded' => 'nullable|string|max:255',
            'employee_count' => 'nullable|string|max:255',
            'is_competitor' => 'boolean',
        ]);
        
        $organization = request()->user()->currentTeam->organizations()->create($validated);
        
        return response()->json($organization, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Organization $organization): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json($organization);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'name' => 'sometimes|nullable|string|max:255',
            'website' => 'sometimes|nullable|string|max:255',
            'founded' => 'sometimes|nullable|string|max:255',
            'employee_count' => 'sometimes|nullable|string|max:255',
            'is_competitor' => 'sometimes|boolean',
        ]);
        
        $organization->update($validated);
        
        return response()->json($organization);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Organization $organization): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Prevent deleting the default organization (non-competitor)
        if (!$organization->is_competitor) {
            return response()->json(['message' => 'Cannot delete the default organization'], 422);
        }
        
        $organization->delete();
        
        return response()->json(null, 204);
    }
}
