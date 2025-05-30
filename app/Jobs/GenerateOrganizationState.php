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

class GenerateOrganizationState extends TrackableJob
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
	 * @param  \App\Models\Organization  $model
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
	 * @return void
	 */
	public function handle(JobDispatcherService $jobDispatcher)
	{
		try {
			// Mark the job as started
			$this->markJobAsStarted('Determining state location for ' . $this->model->name);

			$searchApiTool = new SearchApiTool();

			$textResponse = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withMessages([new UserMessage("Here is a company: \"" . $this->model->name . "\" (" . $this->model->website . "). Please determine which US state this company is headquartered in or primarily located.")])
				->withTools([$searchApiTool])
				->withToolChoice(ToolChoice::Auto)
				->asText();

			// // Log the complete response object for debugging
			// Log::info('Organization state determination job completed', [
			// 	'response' => $textResponse,
			// 	'text' => $textResponse->text ?? 'No text found',
			// 	'organization' => $this->model->name
			// ]);

			$schema = new ObjectSchema(
				name: 'us_state',
				description: 'A US state',
				properties: [
					new StringSchema(
						name: 'state',
						description: 'The US state'
					)
				],
				requiredFields: ['state']
			);

			$response = Prism::structured()
				->using(Provider::OpenAI, 'gpt-4o')
				->withSchema($schema)
				->withPrompt('Here is information about a companies location, please determine the US state they operate in. Return the full state name (e.g., California, New York): ' . $textResponse->text)
				->asStructured();

			$result = $response->structured;

			// Update the organization with the state
			$this->model->update([
				'state' => $result['state']
			]);

			// Mark the job as completed
			$this->markJobAsCompleted('Saved organization state "' . $result['state'] . '"');

			// Generate phrases for the organization
			$jobDispatcher->dispatch($this->model, new GeneratePhrases($this->model, $this->teamId));
		} catch (Throwable $exception) {
			Log::error('Organization state determination job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
