<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Batchable;
use App\Models\Response;
use App\Models\Term;

class CheckTermInPastResponsesJob extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 1;

	/**
	 * The term instance.
	 *
	 * @var \App\Models\Term
	 */
	protected $term;

	/**
	 * Create a new job instance.
	 *
	 * @param  \App\Models\Term  $term
	 * @return void
	 */
       public function __construct(Term $term)
       {
                $this->term = $term;
                $this->campaignId = $term->organization->campaign_id ?? null;
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
			$this->markJobAsStarted('Checking term in past responses');

			// Get all responses for prompts in the same team and campaign (if campaign_id is provided)
			$responses = Response::whereHas("prompt", function ($query) {
				$query->where("team_id", $this->term->team_id);
				// Only filter by campaign if the term's organization belongs to a specific campaign
				if ($this->term->organization->campaign_id !== null) {
					$query->where("campaign_id", $this->term->organization->campaign_id);
				}
			})->get();

			$termName = strtolower($this->term->name);
			$foundInResponses = 0;
			$foundInPrompts = [];

			// Update progress
			$this->updateJobProgress(10, 'Checking for term "' . $this->term->name . '" in past responses');

			foreach ($responses as $response) {
				$responseText = strtolower($response->content);

				// Check if the term exists in the response
				if (str_contains($responseText, $termName)) {
					$foundInResponses++;

					// Attach term to response
					$response->terms()->syncWithoutDetaching([$this->term->id]);

					// Get the prompt for this response
					$prompt = $response->prompt;

					// Update the pivot table for term-prompt relationship
					$pivot = $prompt->terms()->syncWithoutDetaching([$this->term->id]);

					// If this is a new relationship, initialize the count
					if (isset($pivot[$this->term->id]) && $pivot[$this->term->id]['created']) {
						$prompt->terms()->updateExistingPivot($this->term->id, [
							'count' => 1, // TODO: Remove the count column, I don't think we use it anymore
							'last_found_at' => now(),
						]);
						$foundInPrompts[$prompt->id] = 1;
					} else {
						// Increment the count and update last_found_at
						$prompt->terms()->updateExistingPivot($this->term->id, [
							'count' => DB::raw('count + 1'),
							'last_found_at' => now(),
						]);
						$foundInPrompts[$prompt->id] = ($foundInPrompts[$prompt->id] ?? 0) + 1;
					}
				}
			}

			// Mark the job as completed
			$this->markJobAsCompleted('Found term "' . $this->term->name . '" in ' . $foundInResponses . ' responses');
		} catch (Throwable $exception) {
			Log::error('Error in CheckTermInPastResponsesJob: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
