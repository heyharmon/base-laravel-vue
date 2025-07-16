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

class GenerateOrganizationKeywords extends TrackableJob
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
			if ($this->isCancelled()) {
				return;
			}
			// Mark the job as started
			$this->markJobAsStarted('Finding keywords for ' . $this->model->name);

			$searchApiTool = new SearchApiTool();

			// Build organization context with available properties
			$organizationContext = "Here is a company: \"" . $this->model->name . "\" (" . $this->model->website . ")";

			// Add location if available
			if (!empty($this->model->location)) {
				$organizationContext .= " located in " . $this->model->location;
			}

			// Add description if available
			if (!empty($this->model->description)) {
				$organizationContext .= ". Description: " . $this->model->description;
			}

			$prompt = $organizationContext . ". Your job is to come up with a list of keywords relevent to this company.
These are keywords people are likely to be searching for when looking for products and services this company offer. For example, if you are given the company, \"ACME bank\", you might come up with keywords like \"auto loan\", \"home loan\", \"checking account\", etc.
Output keywords as a plain text list.";

			$textResponse = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withMaxSteps(10)
				->withMessages([new UserMessage($prompt)])
				->withTools([$searchApiTool])
				->withToolChoice(ToolChoice::Auto)
				->asText();

			$schema = new ObjectSchema(
				name: 'keyword_suggestions',
				description: 'Company keyword suggestions.',
				properties: [
					new ArraySchema(
						name: 'keywords',
						description: 'List of keywords.',
						items: new StringSchema(
							name: 'keyword',
							description: 'A suggested keyword'
						)
					)
				],
				requiredFields: ['keywords']
			);

			$response = Prism::structured()
				->using(Provider::OpenAI, 'gpt-4o')
				->withSchema($schema)
				->withPrompt('Here is a list of keywords, please return them as an array: ' . $textResponse->text)
				->asStructured();

			$result = $response->structured;

			$this->updateJobProgress(90, 'Saving keywords for ' . $this->model->name);

			$this->model->update([
				'keywords' => $result['keywords']
			]);

			// Mark the job as completed
			$this->markJobAsCompleted('Saved keywords for ' . $this->model->name);

			// Generate prompts for keywords if this is the owned organization
			if (!$this->model->is_competitor) {
				foreach ($this->model->keywords as $keyword) {
					$jobDispatcher->dispatch($this->model, new GeneratePrompt($this->model, $this->teamId, $keyword));
				}
			}
		} catch (Throwable $exception) {
			Log::error('Keyword generation job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
