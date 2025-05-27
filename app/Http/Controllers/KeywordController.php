<?php

namespace App\Http\Controllers;

use App\Jobs\CheckKeywordInPastResponsesJob;
use App\Models\Keyword;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Services\JobDispatcherService;

class KeywordController extends Controller
{
	protected $jobDispatcher;

    public function __construct(JobDispatcherService $jobDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
    }

    public function index(Organization $organization, Request $request): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;

        // Verify the organization belongs to the user's team
        if ($organization->team_id !== $teamId) {
          return response()->json(['message' => 'Organization not found'], 404);
        }

        $keywords = $organization->keywords()
            ->withCount('prompts')
            ->latest()
            ->get();

        return response()->json($keywords);
    }

    public function show(Organization $organization, Keyword $keyword): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;

        // Verify the organization belongs to the user's team
        if ($organization->team_id !== $teamId) {
          return response()->json(['message' => 'Organization not found'], 404);
        }

        // Check if keyword belongs to organization owned by user's current team
        if ($keyword->organization->team_id !== $teamId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Load keyword prompts with pivot data
        $keyword->load(['prompts' => function($query) {
            $query->withPivot('count', 'last_found_at');
        }]);

        return response()->json($keyword);
    }

    public function store(Organization $organization, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string',
        ]);

        $teamId = Auth::user()->current_team_id;

        // Verify the organization belongs to the user's team
        if ($organization->team_id !== $teamId) {
          return response()->json(['message' => 'Organization not found'], 404);
        }

        // Create keyword with both organization_id and team_id
        $validated['team_id'] = $teamId;
        $keyword = $organization->keywords()->create($validated);

		// Dispatch a job to check past responses for this keyword
        $job = new CheckKeywordInPastResponsesJob($keyword);
        $jobStatus = $this->jobDispatcher->dispatch($keyword, $job);

        return response()->json($keyword, 201);
    }

    public function destroy(Organization $organization, Keyword $keyword): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;

        // Verify the organization belongs to the user's team
        if ($organization->team_id !== $teamId) {
          return response()->json(['message' => 'Organization not found'], 404);
        }

        // Check if keyword belongs to organization owned by user's current team
        if ($keyword->organization->team_id !== $teamId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $keyword->delete();

        return response()->json(null, 204);
    }
}
