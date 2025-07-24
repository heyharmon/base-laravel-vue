<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Term;
use App\Models\Team;
use App\Models\Campaign;
use App\Jobs\GenerateCampaignKeywords;

class OrganizationOnboardController extends Controller
{
	protected $jobDispatcher;

	public function __construct(JobDispatcherService $jobDispatcher)
	{
		$this->jobDispatcher = $jobDispatcher;
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request, Team $team): JsonResponse
	{
		$validated = $request->validate([
			'name' => 'nullable|string|max:255',
			'website' => 'nullable|string|max:255',
			'logo' => 'nullable|string|max:1000',
			'color' => 'nullable|string|max:255',
			'description' => 'nullable|string|max:65535',
			'long_description' => 'nullable|string|max:65535',
			'location' => 'nullable|string|max:255',
			'city' => 'nullable|string|max:255',
			'state' => 'nullable|string|max:255',
			'country' => 'nullable|string|max:255',
			'founded' => 'nullable|string|max:255',
			'employee_count' => 'nullable|string|max:255',
			'is_competitor' => 'boolean',
		]);

		// Extract campaign fields
		$campaignFields = [
			'location' => $validated['location'] ?? null,
			'description' => $validated['description'] ?? null,
		];

		// Remove campaign fields from organization data
		unset($validated['location'], $validated['description']);

		// Create the team's own organization
		$organization = $team->organizations()->create($validated);

		// Create default campaign if it doesn't exist
		$campaign = Campaign::create([
			'team_id' => $team->id,
			'name' => 'Default Campaign',
			'is_default' => true,
			...$campaignFields
		]);

		// Create a term for the organization's name
		Term::create([
			'team_id' => $organization->team_id,
			'organization_id' => $organization->id,
			'name' => $organization->name,
		]);

		// Create a term for the organization's website
		Term::create([
			'team_id' => $organization->team_id,
			'organization_id' => $organization->id,
			'name' => $organization->website,
		]);

		// Dispatch a job to generate keywords for the campaign
		$this->jobDispatcher->dispatch($organization, new GenerateCampaignKeywords($campaign, $organization, $organization->team_id));

		return response()->json($organization, 201);
	}
}
