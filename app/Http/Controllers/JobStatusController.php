<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\JobStatus;
use App\Models\Prompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;

class JobStatusController extends Controller
{
    /**
     * Get all job statuses for the current team.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTeamJobs(Request $request)
    {
        $teamId = Auth::user()->current_team_id;

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
    public function cancelTeamJobs(Request $request)
    {
        $teamId = Auth::user()->current_team_id;

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
