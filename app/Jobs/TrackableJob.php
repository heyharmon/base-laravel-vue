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
	 * Determine if the job has been cancelled.
	 *
	 * @return bool
	 */
	protected function isCancelled()
	{
		$status = $this->getJobStatus();

		return $status && $status->status === 'cancelled';
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
	protected function markJobAsStarted($output = null)
	{
		$this->updateJobStatus([
			'status' => 'processing',
			'output' => is_string($output) ? $output : json_encode($output),
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
		$this->updateJobStatus([
			'status' => 'failed',
			'error' => $exception->getMessage(),
		]);
	}
}
