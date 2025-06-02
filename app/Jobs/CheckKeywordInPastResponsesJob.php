<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Batchable;
use App\Models\Response;
use App\Models\Keyword;

class CheckKeywordInPastResponsesJob extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 3;

	/**
	 * The keyword instance.
	 *
	 * @var \App\Models\Keyword
	 */
	protected $keyword;

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
	 * @param  \App\Models\Keyword  $keyword
	 * @return void
	 */
	public function __construct(Keyword $keyword, int $teamId)
	{
		$this->model = $keyword;
		$this->teamId = $teamId;
		$this->keyword = $keyword;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
        public function handle()
        {
                try {
                        if ($this->isCancelled()) {
                                return;
                        }
                        // Mark the job as started
                        $this->markJobAsStarted('Checking keyword in past responses');

			// Get all responses for prompts in the same team
			$responses = Response::whereHas('prompt', function ($query) {
				$query->whereHas('team', function ($teamQuery) {
					$teamQuery->where('id', $this->keyword->team_id);
				});
			})->get();

			$keywordName = strtolower($this->keyword->name);
			$foundInResponses = 0;
			$foundInPrompts = [];

			// Update progress
			$this->updateJobProgress(10, 'Checking for keyword "' . $this->keyword->name . '" in past responses');

			foreach ($responses as $response) {
				$responseText = strtolower($response->content);

				// Check if the keyword exists in the response
				if (str_contains($responseText, $keywordName)) {
					$foundInResponses++;

					// Attach keyword to response
					$response->keywords()->syncWithoutDetaching([$this->keyword->id]);

					// Get the prompt for this response
					$prompt = $response->prompt;

					// Update the pivot table for keyword-prompt relationship
					$pivot = $prompt->keywords()->syncWithoutDetaching([$this->keyword->id]);

					// If this is a new relationship, initialize the count
					if (isset($pivot[$this->keyword->id]) && $pivot[$this->keyword->id]['created']) {
						$prompt->keywords()->updateExistingPivot($this->keyword->id, [
							'count' => 1, // TODO: Remove the count column, I don't think we use it anymore
							'last_found_at' => now(),
						]);
						$foundInPrompts[$prompt->id] = 1;
					} else {
						// Increment the count and update last_found_at
						$prompt->keywords()->updateExistingPivot($this->keyword->id, [
							'count' => DB::raw('count + 1'),
							'last_found_at' => now(),
						]);
						$foundInPrompts[$prompt->id] = ($foundInPrompts[$prompt->id] ?? 0) + 1;
					}
				}
			}

			// Mark the job as completed
			$this->markJobAsCompleted('Found keyword "' . $this->keyword->name . '" in ' . $foundInResponses . ' responses');
		} catch (Throwable $exception) {
			Log::error('Error in CheckKeywordInPastResponsesJob: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
