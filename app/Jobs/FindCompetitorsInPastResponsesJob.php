<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Throwable;

class FindCompetitorsInPastResponsesJob extends TrackableJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The prompt instance.
     *
     * @var \App\Models\Prompt
     */
    // protected $prompt;

    /**
     * The response instance.
     *
     * @var \App\Models\Response
     */
    protected $response;

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
     * @param  \App\Models\Response  $response
     * @param  int|null  $teamId
     * @return void
     */
    public function __construct(Prompt $prompt, Response $response, int $teamId)
    {
        $this->model = $prompt;
		$this->teamId = $teamId;
        $this->response = $response;
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

            // Get competitors from the LLM
            $competitors = $this->findCompetitorsWithLlm($this->response->content, $ownedOrganization);

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
            ->withPrompt('Here is a prompt response about my organization ' . $ownedOrganization->name . ', please find mentions of my potential competitors (do not include potential competitors not mentioned in the prompt response) and return them as an array including the competitor\'s name and website root domain (if website is found in the response): ' . $responseContent)
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
            // Skip if name is empty
            if (empty($competitor['name'])) {
                continue;
            }

            // Check if this competitor already exists
            $existingOrganization = Organization::where('team_id', $this->teamId)
                ->where('website', $competitor['website'])
				->withRecommended()
                ->first();

            if ($existingOrganization) {
                // Ensure it's marked as a competitor
                if (!$existingOrganization->is_competitor) {
                    $existingOrganization->update(['is_competitor' => true]);
                }
            } else {
                // Create new competitor organization
                Organization::create([
					'team_id' => $this->teamId,
					'name' => $competitor['name'],
					'website' => $competitor['website'] ?? null,
					'is_competitor' => true,
					'is_recommended' => true,
				]);

				$createdCount++;
            }
        }

        return $createdCount;
    }
}
