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
use App\Models\Term;

class FindCompetitorsInResponseJob extends TrackableJob
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
			if ($this->isCancelled()) {
				return;
			}

			// Mark the job as started
			$this->markJobAsStarted('Finding competitors in response');

			// Update progress
			$this->updateJobProgress(10, 'Finding competitors in response');

			// Skip if team already has 50 competitors
			$competitorCount = Organization::where('team_id', $this->teamId)->where('is_competitor', true)->count();

			if ($competitorCount >= 10) {
				$this->markJobAsCompleted('Skipping prompt, max 50 competitors reached');
				return;
			}

			// Get the owned organization for this team
			$ownedOrganization = Organization::where('team_id', $this->teamId)
				->where('is_competitor', false)
				->first();

			if (!$ownedOrganization) {
				$this->markJobAsCompleted('Skipping prompt without owned organization');
				return;
			}

			// Get the latest response for this prompt
			$latestResponse = $this->model->responses()->latest()->first();

			// Skip prompts without responses
			if (!$latestResponse) {
				$this->markJobAsCompleted('Skipping prompt because it has no responses');
				return;
			}

			// Get competitors from the LLM
			$competitors = $this->findCompetitorsWithLlm($latestResponse->content, $ownedOrganization);

			// Create competitor
			$createdCount = $this->createCompetitorOrganizations($competitors);

			// Mark the job as completed
			$this->markJobAsCompleted('Successfully processed response and found ' . $createdCount . ' competitors');
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
				new UserMessage('Find other organizations mentioned in the prompt response that may be potential competitors. Include their name, and website. Only use the search tool to find an organizations website if you do not already know the website from your own knowledge. Use the organizations own website and never directories like Yelp, Thumbtack, Yellowpages, etc. IMPORTANT: ONLY GIVE ME ORGANIZATIONS MENTIONED IN THE PROMPT RESPONSE THAT MAY BE COMPETITORS!')
			])
			->withTools([$searchApiTool])
			->withToolChoice(ToolChoice::Auto)
			->asText();

		// Define the schema for structured output
		$schema = new ObjectSchema(
			name: 'competitor_suggestions',
			description: 'Competitor suggestions',
			properties: [
				new ArraySchema(
					name: 'competitors',
					description: 'List of organizations that may be competitors',
					items: new ObjectSchema(
						name: 'competitor',
						description: 'An organization that may be a competitor',
						properties: [
							new StringSchema(
								name: 'name',
								description: 'The normalized name of the organization. If the name provided contains a location (e.g. "ACME bank in New York"), only return the name (e.g. "ACME bank"). If the name includes a suffix (e.g. "ACME bank, Inc."), only return the name (e.g. "ACME bank"). IF the name is long, convoluted or contains descriptions of service (e.g. "Manwill Plumbing Heating & Air Conditioning"), only return the identifiable portion of the name (e.g. "Manwill").'
							),
							new StringSchema(
								name: 'website',
								description: 'The root domain of the organization (e.g. google.com) without scheme, subdomain or path'
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
			->withPrompt('Look at this list of my competitors and return them as a structured array including the competitor\'s name and website root domain: "' . $textResponse->text . '"')
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

			// Check if this competitor already exists by website or name
			$existingOrganization = Organization::where('team_id', $this->teamId)
				->where(function ($query) use ($competitor) {
					$query->where('website', $competitor['website'])
						->orWhere('name', $competitor['name']);
				})
				->first();

			if (!$existingOrganization) {
				// Create new competitor organization
				$competitorOrg = Organization::create([
					'team_id' => $this->teamId,
					'name' => $competitor['name'],
					'website' => $competitor['website'] ?? null,
					'is_competitor' => true,
				]);

				// Create a term for the competitor name
				$nameTerm = Term::create([
					'team_id' => $this->teamId,
					'organization_id' => $competitorOrg->id,
					'name' => $competitor['name'],
				]);

				// Create a term for the competitor website
				$websiteTerm = Term::create([
					'team_id' => $this->teamId,
					'organization_id' => $competitorOrg->id,
					'name' => $competitor['website'],
				]);

				// Dispatch a job to check past responses for this term
				$jobDispatcher = app(JobDispatcherService::class);
				foreach ([$nameTerm, $websiteTerm] as $term) {
					$jobDispatcher->dispatch($term, new CheckTermInPastResponsesJob($term, $this->teamId));
				}

				// Dispatch the GenerateOrganizationKeywords job for this organization
				// $jobDispatcher->dispatch($competitorOrg, new GenerateOrganizationKeywords($competitorOrg, $this->teamId));

				$createdCount++;
			}
		}

		return $createdCount;
	}
}
