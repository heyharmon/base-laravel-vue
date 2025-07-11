<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Prompt;
use App\Jobs\FindCompetitorsInResponseJob;

class OrganizationCompetitorController extends Controller
{
	protected $jobDispatcher;

	public function __construct(JobDispatcherService $jobDispatcher)
	{
		$this->jobDispatcher = $jobDispatcher;
	}

	public function find(Request $request): JsonResponse
	{
		$user = Auth::user();
		$teamId = $user->current_team_id;
		$campaignId = $user->current_campaign_id;

		// Get all prompts for the current team
		$query = Prompt::where('team_id', $teamId);

		if ($campaignId) {
			$query->where('campaign_id', $campaignId);
		}

		$prompts = $query->get();

		if ($prompts->isEmpty()) {
			return response()->json([
				'message' => 'No prompts found to analyze'
			], 404);
		}

		// Create jobs for the latest response of each prompt
		$jobs = [];

		foreach ($prompts as $prompt) {
			$jobs[] = new FindCompetitorsInResponseJob($prompt, $teamId, $campaignId);
		}

		if (empty($jobs)) {
			return response()->json([
				'message' => 'No prompt responses found to analyze'
			], 404);
		}

		// Dispatch as a single batch with tracking
		$batch = $this->jobDispatcher->dispatchBatch($prompts, $jobs, [
			'name' => 'Searching for competitors in past responses',
			'allowFailures' => true
		]);

		return response()->json([
			'message' => 'All prompt responses queued for competitor analysis',
			'batch' => $batch,
			'prompts_count' => count($prompts),
			'total_jobs' => count($jobs)
		]);
	}
}
