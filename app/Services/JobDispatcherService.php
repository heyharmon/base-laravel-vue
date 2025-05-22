<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;

class JobDispatcherService
{
    /**
     * Dispatch a job and track it with the given model.
     *
     * @param Model $model
     * @param mixed $job
     * @return void
     */
    public function dispatch(Model $model, $job)
    {
        // Create a job status record and get the ID
        $jobStatus = $model->trackJob($job);
        
        // Attach the job status ID to the job
        $job->withJobStatus($jobStatus->job_id);
        
        // Dispatch the job
        dispatch($job);
        
        return $jobStatus;
    }
    
    /**
     * Create a batch of jobs and track them with the given models.
     *
     * @param \Illuminate\Support\Collection|Model $models Collection of models or a single model
     * @param array $jobs Array of jobs to be processed
     * @param array $options Batch options
     * @return \Illuminate\Bus\Batch
     */
    public function dispatchBatch($models, array $jobs, array $options = [])
    {
        // Convert single model to collection if needed
        if ($models instanceof Model) {
            $models = collect([$models]);
        }
        
        // Start a new batch
        $pendingBatch = Bus::batch([]);
        
        // Set batch options
        if (isset($options['name'])) {
            $pendingBatch->name($options['name']);
        }
        
        if (isset($options['allowFailures']) && $options['allowFailures']) {
            $pendingBatch->allowFailures();
        }
        
        // Add all jobs to the batch with tracking
        foreach ($jobs as $index => $job) {
            // Get the corresponding model (or the first model if no mapping exists)
            $model = isset($job->model) ? $job->model : $models->first();
            
            $jobStatus = $model->trackJob($job, 'pending_batch_id');
            $job->withJobStatus($jobStatus->job_id);
            $pendingBatch->add($job);
        }
        
        // Dispatch the batch
        $batch = $pendingBatch->dispatch();
        
        // Update all the job statuses with the batch ID
        foreach ($models as $model) {
            $model->jobStatuses()
                ->where('job_batch_id', 'pending_batch_id')
                ->update(['job_batch_id' => $batch->id]);
        }
        
        return $batch;
    }
}
