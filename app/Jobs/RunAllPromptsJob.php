<?php

namespace App\Jobs;

use App\Models\Prompt;
use App\Jobs\RunPromptJob;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;
use App\Services\JobDispatcherService;
use Throwable;

class RunAllPromptsJob extends TrackableJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The model to use for job tracking.
     *
     * @var \Illuminate\Database\Eloquent\Model
     */
    public $model;

    /**
     * The team ID.
     *
     * @var int
     */
    protected $teamId;

    /**
     * The campaign ID.
     *
     * @var int
     */
    protected $campaignId;

    /**
     * The providers to use for running the prompts.
     *
     * @var array
     */
    protected $providers;

    /**
     * The number of times to run each prompt.
     *
     * @var int
     */
    protected $count;

    /**
     * Create a new job instance.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  int  $teamId
     * @param  array  $providers
     * @param  int  $count
     * @return void
     */
    public function __construct($model, int $teamId, int $campaignId, array $providers = ['openai'], int $count = 1)
    {
        $this->model = $model;
        $this->teamId = $teamId;
        $this->campaignId = $campaignId;
        $this->providers = $providers;
        $this->count = $count;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(JobDispatcherService $jobDispatcher)
    {
        try {
            if ($this->isCancelled()) {
                return;
            }
            // Mark the job as started
            $this->markJobAsStarted('Running all prompts');

            // Update progress
            $this->updateJobProgress(10, 'Fetching prompts');

            // Get prompts for the specified team and campaign
            $prompts = Prompt::where('team_id', $this->teamId)
                ->where('campaign_id', $this->campaignId)
                ->get();

            if ($prompts->isEmpty()) {
                $this->markJobAsCompleted('No prompts found');
                return;
            }

            $this->updateJobProgress(30, 'Creating prompt run jobs');

            // Create jobs for all prompts
            $jobs = [];
            foreach ($prompts as $prompt) {
                // For each prompt, create the specified number of jobs
                for ($i = 0; $i < $this->count; $i++) {
                    $jobs[] = new RunPromptJob($prompt, $this->providers, $this->teamId, $this->campaignId);
                }
            }

            $this->updateJobProgress(50, 'Dispatching batch of prompt jobs');

            // Dispatch as a single batch with tracking using all prompts as models
            $batch = $jobDispatcher->dispatchBatch($prompts, $jobs, [
                'name' => "All Prompts Batch ({$this->count}x each)",
                'allowFailures' => true
            ]);

            $this->updateJobProgress(90, 'Batch dispatched successfully');

            // Mark the job as completed
            $this->markJobAsCompleted('Successfully queued ' . count($jobs) . ' prompt jobs for processing');
        } catch (Throwable $exception) {
            Log::error('All prompts batch job failed: ' . $exception->getMessage());
            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }
}
