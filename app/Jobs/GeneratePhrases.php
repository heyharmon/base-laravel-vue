<?php

namespace App\Jobs;

use Throwable;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use App\Tools\SearchApiTool;
use App\Services\JobDispatcherService;
use App\Models\Organization;

class GeneratePhrases extends TrackableJob
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
	 * @param  \App\Models\Prompt  $prompt
	 * @param  array  $providers
	 * @param  int  $teamId
	 * @return void
	 */
	public function __construct(Organization $model, int $teamId)
	{
		$this->model = $model;
		$this->teamId = $teamId;
	}

	/**
	 * Execute the job.
	 *
	 * @param JobDispatcherService $jobDispatcher
	 * @return void
	 */
	public function handle(JobDispatcherService $jobDispatcher)
	{
		try {
			// Mark the job as started
			$this->markJobAsStarted('Generating keyword terms for ' . $this->model->name);

			$searchApiTool = new SearchApiTool();

			$textResponse = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withMaxSteps(10)
				->withMessages([new UserMessage("Here is a company: \"" . $this->model->name . "\" (" . $this->model->website . "). Your job is to come up with a list of keyword terms relevent to this company.
These are keywords people are likely to be searching for when looking for products and services this company offer. For example, if you are given the company, \"ACME bank\", you might come up with keywords like \"auto loan\", \"home loan\", \"checking account\", etc.
Output keywords as a plain text list.")])
				->withTools([$searchApiTool])
				->withToolChoice(ToolChoice::Auto)
				->asText();

			$schema = new ObjectSchema(
				name: 'term_suggestions',
				description: 'Keyword term suggestions.',
				properties: [
					new ArraySchema(
						name: 'terms',
						description: 'List of keyword terms.',
						items: new StringSchema(
							name: 'term',
							description: 'A suggested keyword term'
						)
					)
				],
				requiredFields: ['terms']
			);

			$response = Prism::structured()
				->using(Provider::OpenAI, 'gpt-4o')
				->withSchema($schema)
				->withPrompt('Here is a list of keyword terms, please return them as an array: ' . $textResponse->text)
				->asStructured();

			$result = $response->structured;

			$this->updateJobProgress(90, 'Saving keywordterms for ' . $this->model->name);

			$this->model->update([
				'terms' => $result['terms']
			]);

			// Mark the job as completed
			$this->markJobAsCompleted('Saved keyword terms for ' . $this->model->name);

			// Generate prompts for phrases if this is the owned organization
			if (!$this->model->is_competitor) {
				foreach ($this->model->terms as $term) {
					$jobDispatcher->dispatch($this->model, new GeneratePrompt($this->model, $this->teamId, $term, 'Utah'));
				}
			}
		} catch (Throwable $exception) {
			Log::error('Prompt generation job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
