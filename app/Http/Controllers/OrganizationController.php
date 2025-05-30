<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Organization;
use App\Models\Keyword;
use App\Jobs\CheckKeywordInPastResponsesJob;

class OrganizationController extends Controller
{
	protected $jobDispatcher;

	public function __construct(JobDispatcherService $jobDispatcher)
	{
		$this->jobDispatcher = $jobDispatcher;
	}

	/**
	 * Display a listing of the resource.
	 */
	public function index(): JsonResponse
	{
		$teamId = Auth::user()->current_team_id;

		$organizations = Organization::where('team_id', $teamId)
			->withCount('keywords')
			->get();

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
			'logo' => 'nullable|string|max:1000',
			'color' => 'nullable|string|max:255',
			'description' => 'nullable|string|max:255',
			'long_description' => 'nullable|string|max:65535',
			'industry' => 'nullable|string|max:255',
			'city' => 'nullable|string|max:255',
			'state' => 'nullable|string|max:255',
			'country' => 'nullable|string|max:255',
			'founded' => 'nullable|string|max:255',
			'employee_count' => 'nullable|string|max:255',
			'is_competitor' => 'boolean',
		]);

		// TODO: Move this keyword creation logic into the organization model boot method
		$organization = request()->user()->currentTeam->organizations()->create($validated);

		// Create a keyword for the competitor name
		$nameKeyword = Keyword::create([
			'team_id' => $organization->team_id,
			'organization_id' => $organization->id,
			'name' => $organization->name,
		]);

		// Create a keyword for the competitor website
		$websiteKeyword = Keyword::create([
			'team_id' => $organization->team_id,
			'organization_id' => $organization->id,
			'name' => $organization->website,
		]);

		foreach ([$nameKeyword, $websiteKeyword] as $keyword) {
			$this->jobDispatcher->dispatch($keyword, new CheckKeywordInPastResponsesJob($keyword, request()->user()->currentTeam->id));
		}

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
			'logo' => 'sometimes|nullable|string|max:1000',
			'color' => 'sometimes|nullable|string|max:255',
			'description' => 'sometimes|nullable|string|max:255',
			'long_description' => 'sometimes|nullable|string|max:65535',
			'industry' => 'sometimes|nullable|string|max:255',
			'city' => 'sometimes|nullable|string|max:255',
			'state' => 'sometimes|nullable|string|max:255',
			'country' => 'sometimes|nullable|string|max:255',
			'founded' => 'sometimes|nullable|string|max:255',
			'employee_count' => 'sometimes|nullable|string|max:255',
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

		// Delete the organization
		$organization->delete();

		return response()->json(null, 204);
	}
}
