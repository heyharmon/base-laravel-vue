<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobStatus;
use App\Models\Prompt;
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
    public function getTeamJobs(Request $request, Team $team)
    {
        $teamId = $team->id;

        // Get job statuses for this team
        $jobStatuses = JobStatus::where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();

        return response()->json($jobStatuses);
    }

    /**
     * Cancel all pending jobs for the current team.
     */
    public function cancelTeamJobs(Request $request, Team $team)
    {
        $teamId = $team->id;

        $pendingJobs = JobStatus::where('team_id', $teamId)
            ->where('status', 'pending')
            ->get();

        $batchIds = $pendingJobs->pluck('job_batch_id')->filter()->unique();

        foreach ($batchIds as $batchId) {
            Bus::findBatch($batchId)?->cancel();
        }

        JobStatus::where('team_id', $teamId)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Jobs cancelled']);
    }
}
