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
     * Create a batch of jobs and track them with the given model.
     * 
     * If the job has a getModel() method, it will use that model for tracking instead of the provided model.
     * This allows for tracking multiple models in a single batch.
     *
     * @param Model $model
     * @param array $jobs
     * @param array $options
     * @return \Illuminate\Bus\Batch
     */
    public function dispatchBatch(Model $model, array $jobs, array $options = [])
    {
        // Start a new batch
        $pendingBatch = Bus::batch([]);
        
        // Set batch options
        if (isset($options['name'])) {
            $pendingBatch->name($options['name']);
        }
        
        if (isset($options['allowFailures']) && $options['allowFailures']) {
            $pendingBatch->allowFailures();
        }
        
        // Track which models have jobs in this batch
        $modelsWithJobs = [$model->getKey() => $model];
        
        // Add all jobs to the batch with tracking
        foreach ($jobs as $job) {
            // Check if the job has a getModel method to get its own model
            $trackingModel = method_exists($job, 'getModel') ? $job->getModel() : $model;
            
            // Store the model for later batch ID updates
            $modelsWithJobs[$trackingModel->getKey()] = $trackingModel;
            
            $jobStatus = $trackingModel->trackJob($job, 'pending_batch_id');
            $job->withJobStatus($jobStatus->job_id);
            $pendingBatch->add($job);
        }
        
        // Dispatch the batch
        $batch = $pendingBatch->dispatch();
        
        // Update all the job statuses with the batch ID for each model
        foreach ($modelsWithJobs as $trackingModel) {
            $trackingModel->jobStatuses()
                ->where('job_batch_id', 'pending_batch_id')
                ->update(['job_batch_id' => $batch->id]);
        }
        
        return $batch;
    }
}
