<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Keyword;

use App\Jobs\GeneratePhrases;
use App\Jobs\GenerateOrganizationState;

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
			'location' => 'nullable|string|max:255',
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
		Keyword::create([
			'team_id' => $organization->team_id,
			'organization_id' => $organization->id,
			'name' => $organization->name,
		]);

		// Create a keyword for the competitor website
		Keyword::create([
			'team_id' => $organization->team_id,
			'organization_id' => $organization->id,
			'name' => $organization->website,
		]);

		// Dispatch a job to generate phrases for this organization
		$this->jobDispatcher->dispatch($organization, new GeneratePhrases($organization, $organization->team_id));

		return response()->json($organization, 201);
	}
}
