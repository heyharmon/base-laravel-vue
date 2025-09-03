<?php

namespace App\Jobs;

use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Support\Facades\Log;
use App\Services\JobDispatcherService;
use App\Services\OpenAIBatchService;
use App\Jobs\ProcessOpenAIBatchJob;
use Throwable;

class RunAllPromptsJob extends TrackableJob
{
    use \Illuminate\Bus\Batchable;

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
    public function handle(JobDispatcherService $jobDispatcher, OpenAIBatchService $batchService)
    {
        try {
            if ($this->isCancelled()) {
                return;
            }

            $this->markJobAsStarted('Running all prompts');

            $this->updateJobProgress(10, 'Fetching prompts');

            $prompts = Prompt::where('team_id', $this->teamId)
                ->where('campaign_id', $this->campaignId)
                ->get();

            if ($prompts->isEmpty()) {
                $this->markJobAsCompleted('No prompts found');
                return;
            }

            $team = Team::find($this->teamId);
            $totalRuns = $prompts->count() * $this->count;
            if ($team && ($remaining = $team->responsesRemaining()) !== null && $remaining < $totalRuns) {
                $this->markJobAsCompleted('Responses limit reached');
                return;
            }

            $this->updateJobProgress(30, 'Creating OpenAI batch');

            $batchId = $batchService->createBatch($prompts, $this->count);

            $this->updateJobProgress(60, 'Dispatching batch processor');

            $jobDispatcher->dispatch(
                $this->model,
                new ProcessOpenAIBatchJob($batchId, $this->teamId, $this->campaignId, $totalRuns)
            );

            $this->updateJobProgress(90, 'Batch dispatched successfully');

            $this->markJobAsCompleted('OpenAI batch ' . $batchId . ' created');
        } catch (Throwable $exception) {
            Log::error('All prompts batch job failed: ' . $exception->getMessage());
            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }
}
