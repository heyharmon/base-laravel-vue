<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Models\Campaign;
use App\Jobs\GenerateCampaignKeywords;
use App\Services\JobDispatcherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
        protected $jobDispatcher;

        public function __construct(JobDispatcherService $jobDispatcher)
        {
                $this->jobDispatcher = $jobDispatcher;
        }
	/**
	 * Display a listing of campaigns for a team.
	 */
	public function index(Team $team): JsonResponse
	{
		$campaigns = $team->campaigns()->latest()->get();
		return response()->json($campaigns);
	}

	/**
	 * Store a newly created campaign.
	 */
        public function store(Request $request, Team $team): JsonResponse
        {
                $validated = $request->validate([
                        'name' => 'required|string|max:255',
                        'description' => 'nullable|string',
                        'location' => 'nullable|string|max:255',
                        'keywords' => 'nullable|array',
                        'keywords.*' => 'string|max:255',
                        'is_default' => 'nullable|boolean',
                ]);

                $campaign = $team->campaigns()->create($validated);

                if ($campaign->is_default && empty($validated['keywords'])) {
                        $this->jobDispatcher->dispatch($campaign, new GenerateCampaignKeywords($campaign));
                }

                return response()->json($campaign, 201);
        }

        /**
         * Create a default campaign for a newly created team.
         */
        public function createDefault(Request $request, Team $team): JsonResponse
        {
                $validated = $request->validate([
                        'description' => 'nullable|string',
                        'location' => 'nullable|string|max:255',
                ]);

                if ($team->campaigns()->where('is_default', true)->exists()) {
                        return response()->json(['message' => 'Default campaign already exists'], 422);
                }

                $campaign = $team->campaigns()->create([
                        'name' => 'Default Campaign',
                        'is_default' => true,
                        'description' => $validated['description'] ?? null,
                        'location' => $validated['location'] ?? null,
                ]);

                $this->jobDispatcher->dispatch($campaign, new GenerateCampaignKeywords($campaign));

                return response()->json($campaign, 201);
        }

	/**
	 * Display the specified campaign.
	 */
	public function show(Team $team, Campaign $campaign): JsonResponse
	{
		if ($campaign->team_id !== $team->id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		return response()->json($campaign);
	}

	/**
	 * Update the specified campaign.
	 */
	public function update(Request $request, Team $team, Campaign $campaign): JsonResponse
	{
		if ($campaign->team_id !== $team->id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		if ($campaign->is_default && $request->has('is_default') && !$request->is_default) {
			return response()->json(['message' => 'Cannot remove default status from default campaign'], 422);
		}

		$validated = $request->validate([
			'name' => 'sometimes|required|string|max:255',
			'description' => 'sometimes|nullable|string',
			'location' => 'sometimes|nullable|string|max:255',
			'keywords' => 'sometimes|nullable|array',
			'keywords.*' => 'string|max:255',
		]);

		$campaign->update($validated);

		return response()->json($campaign);
	}

	/**
	 * Remove the specified campaign.
	 */
	public function destroy(Team $team, Campaign $campaign): JsonResponse
	{
		if ($campaign->team_id !== $team->id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		if ($campaign->is_default) {
			return response()->json(['message' => 'Cannot delete the default campaign'], 422);
		}

		DB::transaction(function () use ($campaign) {
			// Delete related models via Eloquent so model events fire
			$campaign->prompts()->get()->each->delete();
			$campaign->competitors()->get()->each->delete();
			$campaign->articles()->get()->each->delete();

			$campaign->delete();
		});

		return response()->json(null, 204);
	}

	/**
	 * Switch the user's current campaign.
	 */
	public function switch(Request $request, Team $team, Campaign $campaign): JsonResponse
	{
		if ($campaign->team_id !== $team->id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		session(['current_campaign_id' => $campaign->id]);

		return response()->json([
			'message' => 'Campaign switched successfully',
			'campaign' => $campaign,
		]);
	}
}
