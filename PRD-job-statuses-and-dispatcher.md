# Product Requirements Document for Job Statuses and Job Dispatcher

## Overview
A system in this app that connects jobs to models and provides frontend visibility.

The Approach
1. Create a Job Status tracking model
2. Connect jobs to models using this tracker
3. Update job status throughout execution
4. Create API endpoints to expose job status
5. Build a frontend component to display job status

Let's implement this step-by-step folowing implementation steps below. 

IMPORTANT: The <code> blocks in the implementation steps are suggestions. You can use them as a starting point.
IMPORTANT: Refer back to this document for implementation details. Follow each step in order. Mark each step as completed when finished, then come back.

## Implementation Steps

### Step 1. Create a Job Status Model
First, let's create a migration to track job status:

<code>
        Schema::create('job_statuses', function (Blueprint $table) {
            $table->id();
            $table->uuid('job_id')->index();
            $table->string('job_class');
            $table->string('job_batch_id')->nullable()->index();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('output')->nullable();
            $table->text('error')->nullable();
            $table->integer('progress')->default(0); // 0-100 percent
            
            // Polymorphic relationship for the model being processed
            $table->morphs('trackable');
            
            $table->timestamps();
        });
</code>

Now let's create the JobStatus model:
<code>
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'job_class',
        'job_batch_id',
        'status',
        'output',
        'error',
        'progress',
    ];

    /**
     * Get the owning trackable model.
     */
    public function trackable()
    {
        return $this->morphTo();
    }

    /**
     * Scope a query to only include pending jobs.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include processing jobs.
     */
    public function scopeProcessing($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope a query to only include completed jobs.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to only include failed jobs.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
</code>

### Step 2. Create a trait to make models trackable
Let's create a trait to make it easy to connect models with jobs:

<code>
<?php

namespace App\Traits;

use App\Models\JobStatus;
use Illuminate\Support\Str;

trait HasJobStatus
{
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
        
        // Create a job status record
        $status = $this->jobStatuses()->create([
            'job_id' => $jobId,
            'job_class' => get_class($job),
            'job_batch_id' => $batchId,
            'status' => 'pending',
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
</code>

### Step 3. Apply the trait to my Prompt model

<code>
...
use App\Traits\HasJobStatus;
...
use HasJobStatus;
...
</code>


### Step 4. Create a Base Job Class to handle tracking
Let's create a base job class that my jobs can extend:

<code>
<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\JobStatus;
use Throwable;

abstract class TrackableJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The job status ID.
     *
     * @var string
     */
    protected $jobStatusId;

    /**
     * Set the job status ID.
     *
     * @param string $jobStatusId
     * @return $this
     */
    public function withJobStatus($jobStatusId)
    {
        $this->jobStatusId = $jobStatusId;
        return $this;
    }

    /**
     * Get the job status.
     *
     * @return \App\Models\JobStatus|null
     */
    protected function getJobStatus()
    {
        if (!$this->jobStatusId) {
            return null;
        }

        return JobStatus::where('job_id', $this->jobStatusId)->first();
    }

    /**
     * Update the job status.
     *
     * @param array $data
     * @return void
     */
    protected function updateJobStatus(array $data)
    {
        $status = $this->getJobStatus();
        
        if ($status) {
            $status->update($data);
        }
    }

    /**
     * Mark the job as started.
     *
     * @return void
     */
    protected function markJobAsStarted()
    {
        $this->updateJobStatus([
            'status' => 'processing',
        ]);
    }

    /**
     * Mark the job as completed.
     *
     * @param mixed $output
     * @return void
     */
    protected function markJobAsCompleted($output = null)
    {
        $this->updateJobStatus([
            'status' => 'completed',
            'output' => is_string($output) ? $output : json_encode($output),
            'progress' => 100,
        ]);
    }

    /**
     * Mark the job as failed.
     *
     * @param \Throwable $exception
     * @return void
     */
    protected function markJobAsFailed(Throwable $exception)
    {
        $this->updateJobStatus([
            'status' => 'failed',
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Update the job progress.
     *
     * @param int $progress
     * @param string|null $message
     * @return void
     */
    protected function updateJobProgress(int $progress, string $message = null)
    {
        $data = ['progress' => $progress];
        
        if ($message !== null) {
            $data['output'] = $message;
        }
        
        $this->updateJobStatus($data);
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(Throwable $exception)
    {
        $this->markJobAsFailed($exception);
    }
}
</code>

### Step 5. Update RunPromptJob to use the tracking
Apply the following code snippets where appropriate in the job class

<code>
…
try {
            // Mark the job as started
            $this->markJobAsStarted();
            
            // Update progress
            $this->updateJobProgress(10, ’Sending prompt to LLM’);

	…

	    // Update progress again
           $this->updateJobProgress(80, ‘Processing LLM repsonses’);

            // Mark the job as completed
            $this->markJobAsCompleted('Successfully generated content for post #' . $this->post->id);

} catch (Throwable $exception) {
            Log::error(‘Prompt run failed: ' . $exception->getMessage());
            $this->markJobAsFailed($exception);
            throw $exception;
        }
…
</code>

### Step 6. Create a service to dispatch jobs with tracking

<code>
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
        
        // Add all jobs to the batch with tracking
        foreach ($jobs as $job) {
            $jobStatus = $model->trackJob($job, 'pending_batch_id');
            $job->withJobStatus($jobStatus->job_id);
            $pendingBatch->add($job);
        }
        
        // Dispatch the batch
        $batch = $pendingBatch->dispatch();
        
        // Update all the job statuses with the batch ID
        $model->jobStatuses()
            ->where('job_batch_id', 'pending_batch_id')
            ->update(['job_batch_id' => $batch->id]);
        
        return $batch;
    }
}
</code>

### Step 7. Create Controller and API Endpoints
Now let's create API endpoints to expose job status:

<code>
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobStatus;
use App\Models\Post;
use Illuminate\Http\Request;
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
        
        // Map the model type to a class (you might want to use a more robust solution)
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
</code>

### Step 8. Define API Routes

<code>
...
    // Job status routes
    Route::get('/job-statuses', [JobStatusController::class, 'getModelJobStatuses']);
    Route::get('/job-status/{jobId}', [JobStatusController::class, 'getJobStatus']);
    Route::get('/active-jobs', [JobStatusController::class, 'getActiveJobs']);
    Route::get('/job-batch/{batchId}', [JobStatusController::class, 'getBatchInfo']);
...
</code>

### Step 9. Create a store in resources/js/stores/jobStatusStore.js
We need a setup style Pinia store to fetch and manage job statuses. IMPORTANT: The goal for this store is to be the source of truth for job statuses where we can get the status of a job for any model or get the status of a job batch for a model.
Below is code example for this store, it is a starting point and not completely compatible with our app. Please modify and adapt it to fit our needs. Look at other stores in this directory for examples.

<code>
import api from '@/services/api';

// Reactive states
const jobs = ref([]);
const batch = ref(null);
const loading = ref(true);
const error = ref(null);
let refreshTimer = null;

// Methods
const fetchJobs = () => {
  loading.value = true;
  
  api.get('/job-statuses', {
    params: {
      model_type: props.modelType,
      model_id: props.modelId
    }
  })
  .then(response => {
    jobs.value = response.data.data;
    loading.value = false;
    error.value = null;
  })
  .catch(err => {
    error.value = 'Failed to load job status: ' + (err.response?.data?.error || err.message);
    loading.value = false;
  });
};


const fetchBatchInfo = () => {
  loading.value = true;
  
  api.get(`/job-batch/${props.batchId}`)
    .then(response => {
      batch.value = response.data.batch;
      jobs.value = response.data.jobs;
      loading.value = false;
      error.value = null;
    })
    .catch(err => {
      error.value = 'Failed to load batch info: ' + (err.response?.data?.error || err.message);
      loading.value = false;
    });
};

const hasActiveJobs = () => {
  return jobs.value.some(job => 
    job.status === 'pending' || job.status === 'processing'
  );
};

const startAutoRefresh = () => {
  refreshTimer = setInterval(() => {
    // Only refresh if there are active jobs
    if (hasActiveJobs()) {
      fetchJobs();
    }
  }, props.refreshInterval);
};

const stopAutoRefresh = () => {
  if (refreshTimer) {
    clearInterval(refreshTimer);
    refreshTimer = null;
  }
};

// Lifecycle hooks
onMounted(() => {
  fetchJobs();
  
  if (props.autoRefresh) {
    startAutoRefresh();
  }
});

onBeforeUnmount(() => {
  stopAutoRefresh();
});
</code>

### Step 10. Create a Frontend Component for Job Status
Let's create a Vue component to display job status. This component should use the store created above.
Below is code example for this component, it is a starting point. Please modify and adapt it to work and fit our needs.

<code>
<template>
    <div class="job-status-container">
        <h3 class="text-lg font-medium" v-if="title">{{ title }}</h3>
        
        <div v-if="loading" class="py-4 text-center">
            <div class="spinner"></div>
            <p>Loading job status...</p>
        </div>
        
        <div v-else-if="error" class="bg-red-100 p-3 rounded text-red-700">
            {{ error }}
        </div>
        
        <div v-else>
            <!-- No jobs -->
            <div v-if="jobs.length === 0" class="py-4 text-center text-gray-500">
                No jobs found.
            </div>
            
            <!-- Job list -->
            <div v-else class="space-y-3">
                <div 
                    v-for="job in jobs" 
                    :key="job.job_id" 
                    class="border rounded-lg overflow-hidden bg-white"
                >
                    <div class="px-4 py-3 flex items-center justify-between border-b">
                        <div>
                            <span class="font-medium">{{ getJobName(job) }}</span>
                            <span 
                                class="ml-2 px-2 py-1 text-xs rounded-full"
                                :class="getStatusClass(job.status)"
                            >
                                {{ job.status }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ formatDate(job.created_at) }}
                        </div>
                    </div>
                    
                    <div class="px-4 py-3">
                        <!-- Progress bar -->
                        <div v-if="job.status === 'pending' || job.status === 'processing'" class="mb-3">
                            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                                <div 
                                    class="h-2 bg-blue-500 transition-all duration-500"
                                    :style="{ width: job.progress + '%' }"
                                ></div>
                            </div>
                            <div class="mt-1 text-xs text-right">{{ job.progress }}%</div>
                        </div>
                        
                        <!-- Output or error message -->
                        <div v-if="job.output" class="text-sm">
                            <div class="font-medium mb-1">Output:</div>
                            <div class="text-gray-600">{{ job.output }}</div>
                        </div>
                        
                        <div v-if="job.error" class="text-sm mt-2">
                            <div class="font-medium mb-1 text-red-700">Error:</div>
                            <div class="text-red-600">{{ job.error }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
// Define props with the defineProps macro
const props = defineProps({
  modelType: {
    type: String,
    required: true
  },
  modelId: {
    type: [String, Number],
    required: true
  },
  title: {
    type: String,
    default: 'Job Status'
  },
});

// Use store to get this job
const jobStatusStore = usejobStatusStore();

const job = computed(() => {
  return jobStatusStore.getJobById(props.modelId);
});

const getJobName = (job) => {
  // Extract class name from full namespace
  const className = job.job_class.split('\\').pop();
  return className.replace(/Job$/, '');
};

const getStatusClass = (status) => {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'processing':
      return 'bg-blue-100 text-blue-800';
    case 'completed':
      return 'bg-green-100 text-green-800';
    case 'failed':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleString();
};
</script>

<style scoped>
.spinner {
    border: 2px solid #f3f3f3;
    border-radius: 50%;
    border-top: 2px solid #3498db;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
</code>

### Step 11. Create a Component for Batch Status
Below is code example for this component, it is a starting point. Please modify and adapt it to work and fit our needs.
<code>
<template>
    <div class="job-batch-container">
        <h3 class="text-lg font-medium" v-if="title">{{ title }}</h3>
        
        <div v-if="loading" class="py-4 text-center">
            <div class="spinner"></div>
            <p>Loading batch status...</p>
        </div>
        
        <div v-else-if="error" class="bg-red-100 p-3 rounded text-red-700">
            {{ error }}
        </div>
        
        <div v-else-if="batch">
            <div class="border rounded-lg overflow-hidden bg-white">
                <!-- Batch header -->
                <div class="px-4 py-3 bg-gray-50 border-b">
                    <div class="flex items-center justify-between">
                        <div>
                            <span class="font-medium">{{ batch.name || 'Batch #' + batch.id }}</span>
                            <span 
                                class="ml-2 px-2 py-1 text-xs rounded-full"
                                :class="getBatchStatusClass()"
                            >
                                {{ getBatchStatus() }}
                            </span>
                        </div>
                        <div class="text-sm text-gray-500">
                            {{ formatDate(batch.created_at) }}
                        </div>
                    </div>
                </div>
                
                <!-- Batch progress -->
                <div class="px-4 py-3 border-b">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-sm">Progress</div>
                        <div class="text-sm font-medium">{{ batch.progress }}%</div>
                    </div>
                    
                    <!-- Progress bar -->
                    <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                        <div 
                            class="h-2 transition-all duration-500"
                            :class="getProgressBarClass()"
                            :style="{ width: batch.progress + '%' }"
                        ></div>
                    </div>
                </div>
                
                <!-- Batch stats -->
                <div class="px-4 py-3 border-b grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <div class="text-gray-500">Total Jobs</div>
                        <div class="font-medium">{{ batch.total_jobs }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Processed</div>
                        <div class="font-medium">{{ batch.processed_jobs }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Pending</div>
                        <div class="font-medium">{{ batch.pending_jobs }}</div>
                    </div>
                    <div>
                        <div class="text-gray-500">Failed</div>
                        <div class="font-medium">{{ batch.failed_jobs }}</div>
                    </div>
                </div>
                
                <!-- Job list -->
                <div class="px-4 py-3">
                    <h4 class="font-medium mb-3">Jobs in this batch</h4>
                    
                    <div class="space-y-3">
                        <div 
                            v-for="job in jobs" 
                            :key="job.job_id" 
                            class="border rounded p-3"
                        >
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <span class="font-medium">{{ getJobName(job) }}</span>
                                    <span 
                                        class="ml-2 px-2 py-1 text-xs rounded-full"
                                        :class="getStatusClass(job.status)"
                                    >
                                        {{ job.status }}
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Progress bar for pending/processing jobs -->
                            <div v-if="job.status === 'pending' || job.status === 'processing'" class="mb-2">
                                <div class="h-1 bg-gray-200 rounded-full overflow-hidden">
                                    <div 
                                        class="h-1 bg-blue-500 transition-all duration-500"
                                        :style="{ width: job.progress + '%' }"
                                    ></div>
                                </div>
                                <div class="mt-1 text-xs text-right">{{ job.progress }}%</div>
                            </div>
                            
                            <!-- Output or error message -->
                            <div v-if="job.output" class="text-xs mt-1 text-gray-600">
                                {{ job.output }}
                            </div>
                            
                            <div v-if="job.error" class="text-xs mt-1 text-red-600">
                                {{ job.error }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div v-else class="py-4 text-center text-gray-500">
            No batch information available.
        </div>
    </div>
</template>

<script setup>
// Define props with the defineProps macro
const props = defineProps({
 modelType: {
    type: String,
    required: true
  },
  modelId: {
    type: [String, Number],
    required: true
  },
  title: {
    type: String,
    default: 'Batch Status'
  },
});

// Use store to get this job
const jobStatusStore = usejobStatusStore();

const batch = computed(() => {
  return jobStatusStore.getBatchById(props.modelId);
});

// Methods
const getBatchStatus = () => {
  if (!batch.value) return '';
  
  if (batch.value.cancelled) {
    return 'Cancelled';
  } else if (batch.value.finished) {
    return batch.value.failed_jobs > 0 ? 'Completed with failures' : 'Completed';
  } else {
    return 'Processing';
  }
};

const getBatchStatusClass = () => {
  const status = getBatchStatus();
  
  switch (status) {
    case 'Processing':
      return 'bg-blue-100 text-blue-800';
    case 'Completed':
      return 'bg-green-100 text-green-800';
    case 'Completed with failures':
      return 'bg-yellow-100 text-yellow-800';
    case 'Cancelled':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

const getProgressBarClass = () => {
  const status = getBatchStatus();
  
  switch (status) {
    case 'Completed':
      return 'bg-green-500';
    case 'Completed with failures':
      return 'bg-yellow-500';
    case 'Cancelled':
      return 'bg-red-500';
    default:
      return 'bg-blue-500';
  }
};

const getJobName = (job) => {
  // Extract class name from full namespace
  const className = job.job_class.split('\\').pop();
  return className.replace(/Job$/, '');
};

const getStatusClass = (status) => {
  switch (status) {
    case 'pending':
      return 'bg-yellow-100 text-yellow-800';
    case 'processing':
      return 'bg-blue-100 text-blue-800';
    case 'completed':
      return 'bg-green-100 text-green-800';
    case 'failed':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

const formatDate = (dateString) => {
  const date = new Date(dateString);
  return date.toLocaleString();
};
</script>

<style scoped>
.spinner {
    border: 2px solid #f3f3f3;
    border-radius: 50%;
    border-top: 2px solid #3498db;
    width: 20px;
    height: 20px;
    animation: spin 1s linear infinite;
    margin: 0 auto 10px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>
</code>

### Step 12. Use the jobDispatcher in the PromptRunController

<code>
use App\Services\JobDispatcherService;

...

protected $jobDispatcher;
    
public function __construct(JobDispatcherService $jobDispatcher)
{
    $this->jobDispatcher = $jobDispatcher;
}

...

// Create the job
$job = new RunPromptJob::dispatch($prompt, $providers, $teamId);

// Dispatch the job with tracking
$jobStatus = $this->jobDispatcher->dispatch($prompt, $job);

return response()->json([
    'message' => 'Content generation job has been queued.',
    'job_id' => $jobStatus->job_id,
    'post_id' => $post->id,
]);

...
</code>

### Step 13. Use the JobStatusComponent in the Dashboard.vue page
Use the JobStatusComponent in the Dashboard.vue page where prompts are listed to show the job status of each prompt.

<code>
...
<job-status-component 
    model-type="Prompt"
    :model-id="promptId"
    title=""
/>
...
</code>