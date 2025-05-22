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
     * Get all job statuses for a specific model.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getModelJobStatuses(Request $request)
    {
        $request->validate([
            'model_type' => 'required|string',
            'model_id' => 'required|integer',
        ]);
        
        $modelType = $request->input('model_type');
        $modelId = $request->input('model_id');
        
        // Map the model type to a class
        $modelClass = "App\\Models\\" . ucfirst($modelType);
        
        if (!class_exists($modelClass)) {
            return response()->json(['error' => 'Invalid model type'], 400);
        }
        
        $model = $modelClass::find($modelId);
        
        if (!$model) {
            return response()->json(['error' => 'Model not found'], 404);
        }
        
        $jobStatuses = $model->jobStatuses()
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return response()->json($jobStatuses);
    }
    
    /**
     * Get job status by ID.
     *
     * @param string $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getJobStatus($jobId)
    {
        $jobStatus = JobStatus::where('job_id', $jobId)->first();
        
        if (!$jobStatus) {
            return response()->json(['error' => 'Job status not found'], 404);
        }
        
        return response()->json($jobStatus);
    }
    
    /**
     * Get all active jobs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActiveJobs()
    {
        $activeJobs = JobStatus::whereIn('status', ['pending', 'processing'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
            
        return response()->json($activeJobs);
    }
    
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
     * Get batch information.
     *
     * @param string $batchId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBatchInfo($batchId)
    {
        try {
            $batch = Bus::findBatch($batchId);
            
            if (!$batch) {
                return response()->json(['error' => 'Batch not found'], 404);
            }
            
            // Get all job statuses for this batch
            $jobStatuses = JobStatus::where('job_batch_id', $batchId)
                ->orderBy('created_at', 'desc')
                ->get();
                
            return response()->json([
                'batch' => [
                    'id' => $batch->id,
                    'name' => $batch->name,
                    'total_jobs' => $batch->totalJobs,
                    'pending_jobs' => $batch->pendingJobs,
                    'failed_jobs' => $batch->failedJobs,
                    'processed_jobs' => $batch->processedJobs(),
                    'progress' => $batch->progress(),
                    'created_at' => $batch->createdAt,
                    'cancelled' => $batch->cancelled(),
                    'finished' => $batch->finished(),
                ],
                'jobs' => $jobStatuses,
            ]);
            
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Error retrieving batch: ' . $e->getMessage()], 500);
        }
    }
}
