<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobStatus;
use App\Models\Prompt;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use App\Models\Team;

class JobStatusController extends Controller
{
    /**
     * Get all job statuses for the current team.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTeamJobs(Request $request, Team $team, ?Campaign $campaign = null)
    {
        $query = JobStatus::where('team_id', $team->id);

        if ($campaign) {
            $query->where('campaign_id', $campaign->id);
        }

        $jobStatuses = $query->orderBy('created_at', 'desc')
            ->limit(150)
            ->get();

        return response()->json($jobStatuses);
    }

    /**
     * Cancel all pending jobs for the current team.
     */
    public function cancelTeamJobs(Request $request, Team $team, ?Campaign $campaign = null)
    {
        $query = JobStatus::where('team_id', $team->id)
            ->where('status', 'pending');

        if ($campaign) {
            $query->where('campaign_id', $campaign->id);
        }

        $pendingJobs = $query->get();

        $batchIds = $pendingJobs->pluck('job_batch_id')->filter()->unique();

        foreach ($batchIds as $batchId) {
            Bus::findBatch($batchId)?->cancel();
        }

        if ($campaign) {
            JobStatus::where('team_id', $team->id)
                ->where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        } else {
            JobStatus::where('team_id', $team->id)
                ->where('status', 'pending')
                ->update(['status' => 'cancelled']);
        }

        return response()->json(['message' => 'Jobs cancelled']);
    }
}
