<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class CampaignController extends Controller
{
	public function index(): JsonResponse
	{
		$teamId = Auth::user()->current_team_id;
		$campaigns = Campaign::where('team_id', $teamId)
			->withCount(['prompts', 'organizations', 'articles'])
			->latest()
			->get();

		return response()->json($campaigns);
	}

	public function show(Campaign $campaign): JsonResponse
	{
		if ($campaign->team_id !== Auth::user()->current_team_id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		$campaign->load(['prompts', 'organizations', 'articles']);

		return response()->json($campaign);
	}

	public function store(Request $request): JsonResponse
	{
		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
		]);

		$validated['team_id'] = Auth::user()->current_team_id;
		$validated['is_default'] = false;

		$campaign = Campaign::create($validated);

		// Replicate the team's owned organization (where is_competitor is false)
		$ownedOrganization = Organization::where('team_id', $validated['team_id'])
			->where('is_competitor', false)
			->with('terms')
			->first();

		if ($ownedOrganization) {
			// Create a copy of the organization for this campaign
			$organizationData = $ownedOrganization->toArray();
			unset($organizationData['id'], $organizationData['created_at'], $organizationData['updated_at'], $organizationData['terms']);
			$organizationData['campaign_id'] = $campaign->id;

			$newOrganization = Organization::create($organizationData);

			// Replicate the terms that belong to the organization
			foreach ($ownedOrganization->terms as $term) {
				$termData = $term->toArray();
				unset($termData['id'], $termData['created_at'], $termData['updated_at']);
				$termData['organization_id'] = $newOrganization->id;

				$newOrganization->terms()->create($termData);
			}
		}

		return response()->json($campaign, 201);
	}

	public function update(Request $request, Campaign $campaign): JsonResponse
	{
		if ($campaign->team_id !== Auth::user()->current_team_id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		$validated = $request->validate([
			'name' => 'required|string|max:255',
			'description' => 'nullable|string',
		]);

		$campaign->update($validated);

		return response()->json($campaign);
	}

	public function destroy(Campaign $campaign): JsonResponse
	{
		if ($campaign->team_id !== Auth::user()->current_team_id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		if ($campaign->is_default) {
			return response()->json(['message' => 'Cannot delete the default campaign'], 422);
		}

		$campaign->delete();

		return response()->json(null, 204);
	}
}
