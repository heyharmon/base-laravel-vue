<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Team;
use App\Models\Campaign;
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

    public function find(Request $request, Team $team, Campaign $campaign): JsonResponse
    {
        $teamId = $team->id;
        $campaignId = $campaign->id;

        // Get all prompts for the current team and campaign
        $prompts = Prompt::where('team_id', $teamId)
            ->where('campaign_id', $campaignId)
            ->get();

        if ($prompts->isEmpty()) {
            return response()->json([
                'message' => 'No prompts found to analyze'
            ], 404);
        }

        // Create jobs for the latest response of each prompt
        $jobs = [];

        foreach ($prompts as $prompt) {
            $jobs[] = new FindCompetitorsInResponseJob($prompt, $teamId);
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
