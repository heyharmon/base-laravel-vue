<?php

namespace App\Http\Controllers;

use App\Jobs\CheckTermInPastResponsesJob;
use App\Models\Term;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Team;
use App\Services\JobDispatcherService;

class TermController extends Controller
{
	protected $jobDispatcher;

	public function __construct(JobDispatcherService $jobDispatcher)
	{
		$this->jobDispatcher = $jobDispatcher;
	}

	public function index(Team $team, Organization $organization, Request $request): JsonResponse
	{
		// Verify the organization belongs to the given team
		if ($organization->team_id !== $team->id) {
			return response()->json(['message' => 'Organization not found'], 404);
		}

		$terms = $organization->terms()
			->withCount('prompts')
			->latest()
			->get();

		return response()->json($terms);
	}

	public function show(Team $team, Organization $organization, Term $term): JsonResponse
	{
		// Verify the organization belongs to the given team
		if ($organization->team_id !== $team->id) {
			return response()->json(['message' => 'Organization not found'], 404);
		}

		// Check if term belongs to organization owned by this team
		if ($term->organization->team_id !== $team->id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		// Load term prompts with pivot data
		$term->load(['prompts' => function ($query) {
			$query->withPivot('count', 'last_found_at');
		}]);

		return response()->json($term);
	}

	public function store(Team $team, Organization $organization, Request $request): JsonResponse
	{
		$validated = $request->validate([
			'name' => 'required|string',
		]);

		// Verify the organization belongs to the given team
		if ($organization->team_id !== $team->id) {
			return response()->json(['message' => 'Organization not found'], 404);
		}

		// Create term with both organization_id and team_id
		$validated['team_id'] = $team->id;
		$term = $organization->terms()->create($validated);

		// Dispatch a job to check past responses for this term
		$this->jobDispatcher->dispatch($term, new CheckTermInPastResponsesJob($term));

		return response()->json($term, 201);
	}

	public function destroy(Team $team, Organization $organization, Term $term): JsonResponse
	{
		// Verify the organization belongs to the given team
		if ($organization->team_id !== $team->id) {
			return response()->json(['message' => 'Organization not found'], 404);
		}

		// Check if term belongs to organization owned by this team
		if ($term->organization->team_id !== $team->id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		$term->delete();

		return response()->json(null, 204);
	}
}
