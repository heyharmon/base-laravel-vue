<?php

namespace App\Traits;

use App\Models\JobStatus;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait HasJobStatus
{
    /**
     * Boot the trait.
     */
    public static function bootHasJobStatus()
    {
        static::deleting(function ($model) {
            $model->jobStatuses()->delete();
        });
    }
    /**
     * Get all job statuses for this model.
     */
    public function jobStatuses()
    {
        return $this->morphMany(JobStatus::class, 'trackable');
    }

    /**
     * Get active job statuses for this model.
     */
    public function activeJobs()
    {
        return $this->jobStatuses()
            ->whereIn('status', ['pending', 'processing']);
    }

    /**
     * Create a job status for a new job.
     */
    public function trackJob($job, $batchId = null)
    {
        // Generate a UUID for this job
        $jobId = (string) Str::uuid();

        // Get the current team ID
        // $teamId = Auth::user()->current_team_id;

        // Create a job status record
        $status = $this->jobStatuses()->create([
            'job_id' => $jobId,
            'job_class' => get_class($job),
            'job_batch_id' => $batchId,
            'status' => 'pending',
            'team_id' => $this->team_id,
        ]);

        return $status;
    }

    /**
     * Get the latest job statuses for this model.
     */
    public function latestJobStatuses($limit = 5)
    {
        return $this->jobStatuses()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
