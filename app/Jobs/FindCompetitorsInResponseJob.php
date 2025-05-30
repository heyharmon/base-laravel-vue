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
use App\Models\Response;
use App\Models\Prompt;
use App\Models\Organization;
use App\Models\Keyword;

class FindCompetitorsInResponseJob extends TrackableJob
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
	 * @param  int|null  $teamId
	 * @return void
	 */
	public function __construct(Prompt $prompt, int $teamId)
	{
		$this->model = $prompt;
		$this->teamId = $teamId;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		try {
			// Mark the job as started
			$this->markJobAsStarted();

			// Update progress
			$this->updateJobProgress(10, 'Finding competitors in response');

			// Get the owned organization for this team
			$ownedOrganization = Organization::where('team_id', $this->teamId)
				->where('is_competitor', false)
				->first();

			if (!$ownedOrganization) {
				$this->markJobAsCompleted('No owned organization found for this team');
				return;
			}

			$this->updateJobProgress(30, 'Analyzing response content with LLM');

			// Get the latest response for this prompt
			$latestResponse = $this->model->responses()->latest()->first();

			// Skip prompts without responses
			if (!$latestResponse) {
				return;
			}

			// Get competitors from the LLM
			$competitors = $this->findCompetitorsWithLlm($latestResponse->content, $ownedOrganization);

			$this->updateJobProgress(70, 'Creating competitor organizations');

			// Create or update competitor organizations
			$createdCount = $this->createCompetitorOrganizations($competitors);

			// Mark the job as completed
			$this->markJobAsCompleted("Successfully processed response and found {$createdCount} competitors");
		} catch (Throwable $exception) {
			Log::error('Find competitors job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}

	/**
	 * Find competitors in response content using LLM.
	 *
	 * @param  string  $responseContent
	 * @param  \App\Models\Organization  $ownedOrganization
	 * @return array
	 */
	private function findCompetitorsWithLlm(string $responseContent, Organization $ownedOrganization): array
	{
		$searchApiTool = new SearchApiTool();

		// Get a text response containing recommended competitors
		$textResponse = Prism::text()
			->using(Provider::OpenAI, 'gpt-4o')
			->withMaxSteps(10)
			->withMessages([
				new UserMessage('Here is a prompt response about my organization ' . $ownedOrganization->name . ': "' . $responseContent . '".'),
				new UserMessage('Find mentions of my potential competitors in the prompt. Include their name and website. Only use the search tool to find a competitors website if you do not already know the website from your own knowledge.')
			])
			->withTools([$searchApiTool])
			->withToolChoice(ToolChoice::Auto)
			->asText();

		// Define the schema for structured output
		$schema = new ObjectSchema(
			name: 'competitor_suggestions',
			description: 'Competitor suggestions related to an organization',
			properties: [
				new ArraySchema(
					name: 'competitors',
					description: 'List of competitor suggestions',
					items: new ObjectSchema(
						name: 'competitor',
						description: 'A suggested competitor',
						properties: [
							new StringSchema(
								name: 'name',
								description: 'The name of the competitor'
							),
							new StringSchema(
								name: 'website',
								description: 'The root domain of the competitor (e.g. google.com) without scheme, subdomain or path'
							),
						],
						requiredFields: ['name']
					),
				)
			],
			requiredFields: ['competitors']
		);

		// Get structured response from LLM
		$response = Prism::structured()
			->using(Provider::OpenAI, 'gpt-4o')
			->withSchema($schema)
			->withPrompt('Look at text about my competitors and return them as a structured array including the competitor\'s name and website root domain: "' . $textResponse->text . '"')
			->asStructured();

		$result = $response->structured;

		return $result['competitors'] ?? [];
	}

	/**
	 * Create or update competitor organizations.
	 *
	 * @param  array  $competitors
	 * @return int
	 */
	private function createCompetitorOrganizations(array $competitors): int
	{
		$createdCount = 0;

		foreach ($competitors as $competitor) {
			// Skip if name or website is empty
			if (empty($competitor['name']) || empty($competitor['website'])) {
				continue;
			}

			// Check if this competitor already exists
			$existingOrganization = Organization::where('team_id', $this->teamId)
				->where('website', $competitor['website'])
				->first();

			if (!$existingOrganization) {
				// Create new competitor organization
				$competitorOrg = Organization::create([
					'team_id' => $this->teamId,
					'name' => $competitor['name'],
					'website' => $competitor['website'] ?? null,
					'is_competitor' => true,
				]);

				// Create a keyword for the competitor name
				$nameKeyword = Keyword::create([
					'team_id' => $this->teamId,
					'organization_id' => $competitorOrg->id,
					'name' => $competitor['name'],
				]);

				// Create a keyword for the competitor website
				$websiteKeyword = Keyword::create([
					'team_id' => $this->teamId,
					'organization_id' => $competitorOrg->id,
					'name' => $competitor['website'],
				]);

				// Dispatch a job to check past responses for this keyword
				$jobDispatcher = app(JobDispatcherService::class);
				foreach ([$nameKeyword, $websiteKeyword] as $keyword) {
					$job = new CheckKeywordInPastResponsesJob($keyword, $this->teamId);
					$jobDispatcher->dispatch($keyword, $job);
				}

				$createdCount++;
			}
		}

		return $createdCount;
	}
}
