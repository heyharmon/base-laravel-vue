<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\JobDispatcherService;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeneratePromptsForTermsJob extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 3;

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
	 * Create a new job instance.
	 *
	 * @param  \App\Models\Organization  $organization
	 * @param  int  $teamId
	 * @param  string  $location
	 * @return void
	 */
	public function __construct(Organization $organization, int $teamId)
	{
		$this->model = $organization;
		$this->teamId = $teamId;
	}

	/**
	 * Execute the job.
	 *
	 * @param  \App\Services\JobDispatcherService  $jobDispatcher
	 * @return void
	 */
	public function handle(JobDispatcherService $jobDispatcher)
	{
		try {
			// Mark the job as started
			$this->markJobAsStarted();

			// Update progress
			$this->updateJobProgress(10, 'Preparing to generate prompts for terms');

			// Get organization
			$organization = $this->model;

			if (empty($organization->terms)) {
				$this->markJobAsCompleted('No terms found to generate prompts for');
				return;
			}

			$this->updateJobProgress(30, 'Creating prompt generation jobs');

			// Create jobs for all terms
			$jobs = [];
			foreach ($organization->terms as $term) {
				$jobs[] = new GeneratePromptJob($organization, $this->teamId, $term, 'Utah');
			}

			$this->updateJobProgress(50, 'Dispatching batch of prompt generation jobs');

			$jobs[] = new RunAllPromptsJob($organization, $organization->team_id, ['openai'], 1, true);

			// Dispatch as a single batch with tracking
			$batch = $jobDispatcher->dispatchBatch($organization, $jobs, [
				'name' => "Generate Prompts for {$organization->name} Terms",
				'allowFailures' => true
			]);

			$this->updateJobProgress(90, 'Batch dispatched successfully');

			// Mark the job as completed
			$this->markJobAsCompleted('Successfully queued ' . count($jobs) . ' prompt generation jobs');
		} catch (Throwable $exception) {
			Log::error('Generate prompts for terms job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
