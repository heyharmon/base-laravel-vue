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
use App\Models\Campaign;

class GenerateCampaignKeywords extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 1;

	/**
	 * The campaign keywords will belong to.
	 *
	 * @var Campaign
	 */
	public $campaign;

	/**
	 * The organization used to generate keywords.
	 *
	 * @var Organization|null
	 */
	protected $organization;

	/**
	 * Create a new job instance.
	 *
	 * @param \App\Models\Campaign $campaign
	 * @return void
	 */
	public function __construct(Campaign $campaign)
	{
		$this->campaign = $campaign;
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

			$this->organization = Organization::where('team_id', $this->campaign->team_id)
				->where('is_competitor', false)
				->first();

			if (!$this->organization) {
				$this->markJobAsCompleted('No owned organization found for team');
				return;
			}

			// Mark the job as started
			$this->markJobAsStarted('Finding keywords for ' . $this->organization->name);

			// Create a search API tool
			$searchApiTool = new SearchApiTool();

			// Build organization context with available properties
			$context = "Here is a company: \"" . $this->organization->name . "\" (" . $this->organization->website . ")";

			// Add location if available from campaign
			if (!empty($this->campaign->location)) {
				$context .= " located in " . $this->campaign->location;
			}

			// Add description if available from campaign
			if (!empty($this->campaign->description)) {
				$context .= ". Description: " . $this->campaign->description;
			}

			$prompt = $context . ". Your job is to come up with a list of keywords relevent to this company. These are keywords people are likely to be searching for when looking for products and services this company offer. For example, if you are given the company, \"ACME bank\", you might come up with keywords like \"auto loan\", \"home loan\", \"checking account\", etc. Output keywords as a plain text list.";

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

			$this->updateJobProgress(90, 'Saving keywords for ' . $this->organization->name);

			// Save keywords to the campaign
			$this->campaign->update([
				'keywords' => $result['keywords']
			]);

			// Generate a prompt for each keyword
			if (!$this->organization->is_competitor) {
				foreach ($result['keywords'] as $keyword) {
					$jobDispatcher->dispatch($this->organization, new GeneratePrompt($this->campaign, $this->organization, $keyword));
				}
			}

			// Mark the job as completed
			$this->markJobAsCompleted('Saved keywords for ' . $this->organization->name);
		} catch (Throwable $exception) {
			Log::error('Keyword generation job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
