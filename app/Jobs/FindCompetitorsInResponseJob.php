<?php

namespace App\Jobs;

use Throwable;
use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use App\Services\JobDispatcherService;
use App\Models\Prompt;
use App\Models\Organization;
use App\Models\Term;
use App\Jobs\CheckTermInPastResponsesJob;

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
     * The prompt instance.
     *
     * @var \App\Models\Prompt
     */
    protected $prompt;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Prompt  $prompt
     * @return void
     */
    public function __construct(Prompt $prompt)
    {
        $this->prompt = $prompt;
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

            // Skip if campaign already has 100 competitors
            $competitorCount = Organization::where('campaign_id', $this->prompt->campaign_id)
                ->where('is_competitor', true)
                ->count();

            if ($competitorCount >= 100) {
                $this->markJobAsCompleted('Skipping prompt, max 100 competitors reached');
                return;
            }

            // Get the owned organization for this team
            $ownedOrganization = Organization::where('team_id', $this->prompt->team_id)
                ->where('is_competitor', false)
                ->first();

            if (!$ownedOrganization) {
                Log::info('FindCompetitorsInResponseJob: No owned organization found', [
                    'prompt_id' => $this->prompt->id,
                    'team_id' => $this->prompt->team_id
                ]);
                $this->markJobAsCompleted('Skipping prompt without owned organization');
                return;
            }

            // Get the last 5 responses for this prompt
            $recentResponses = $this->prompt->responses()->latest()->take(5)->get();

            // Skip prompts without responses
            if ($recentResponses->isEmpty()) {
                Log::info('FindCompetitorsInResponseJob: No responses found for prompt', [
                    'prompt_id' => $this->prompt->id
                ]);
                $this->markJobAsCompleted('Skipping prompt because it has no responses');
                return;
            }

            Log::info('FindCompetitorsInResponseJob: Processing prompt', [
                'prompt_id' => $this->prompt->id,
                'owned_organization' => $ownedOrganization->name,
                'responses_count' => $recentResponses->count(),
                'content_length' => strlen($recentResponses->pluck('content')->implode('\n\n---\n\n'))
            ]);

            // Combine content from all recent responses
            $combinedContent = $recentResponses->pluck('content')->implode('\n\n---\n\n');

            // Get competitors from the LLM
            $competitors = $this->findCompetitorsWithLlm($combinedContent, $ownedOrganization);

            Log::info('FindCompetitorsInResponseJob: Found competitors from LLM', [
                'prompt_id' => $this->prompt->id,
                'competitors_count' => count($competitors),
                'competitors' => $competitors
            ]);

            // Create competitor organizations
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
     * Find competitors in response content using OpenAI directly.
     *
     * @param  string  $responseContent
     * @param  \App\Models\Organization  $ownedOrganization
     * @return array
     */
    private function findCompetitorsWithLlm(string $responseContent, Organization $ownedOrganization): array
    {
        try {
            // Use the Responses API with structured output to extract competitors
            $response = OpenAI::responses()->create([
                'model' => 'gpt-4o',
                'input' => 'Here is a prompt response about my organization ' . $ownedOrganization->name . ': "' . $responseContent . '". Find other organizations mentioned in the prompt response that may be potential competitors. Return them as a structured array including the competitor\'s name and website root domain if you know it. IMPORTANT: ONLY GIVE ME ORGANIZATIONS MENTIONED IN THE PROMPT RESPONSE THAT MAY BE COMPETITORS!',
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'competitor_suggestions',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'competitors' => [
                                    'type' => 'array',
                                    'description' => 'List of organizations that may be competitors',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'name' => [
                                                'type' => 'string',
                                                'description' => 'The normalized name of the organization. If the name provided contains a location (e.g. "ACME bank in New York"), only return the name (e.g. "ACME bank"). If the name includes a suffix (e.g. "ACME bank, Inc."), only return the name (e.g. "ACME bank"). If the name is long, convoluted or contains descriptions of service (e.g. "Manwill Plumbing Heating & Air Conditioning"), only return the identifiable portion of the name (e.g. "Manwill").'
                                            ],
                                            'website' => [
                                                'type' => ['string', 'null'],
                                                'description' => 'The root domain of the organization (e.g. google.com) without scheme, subdomain or path. Use null if website is unknown.'
                                            ]
                                        ],
                                        'required' => ['name', 'website'],
                                        'additionalProperties' => false
                                    ]
                                ]
                            ],
                            'required' => ['competitors'],
                            'additionalProperties' => false
                        ],
                        'strict' => true
                    ]
                ],
                'store' => false,
            ]);

            // Extract the content from the response based on the Responses API structure
            $competitorText = '';
            if (isset($response->output) && is_array($response->output)) {
                foreach ($response->output as $item) {
                    if ($item->type === 'message' && isset($item->content[0]->text)) {
                        $competitorText = $item->content[0]->text;
                        break;
                    }
                }
            }

            if (empty($competitorText)) {
                Log::warning('No competitor text extracted from OpenAI response', [
                    'response_structure' => json_encode($response)
                ]);
                return [];
            }

            $result = json_decode($competitorText, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to decode JSON from structured response', [
                    'content' => $competitorText,
                    'json_error' => json_last_error_msg()
                ]);
                return [];
            }

            return $result['competitors'] ?? [];
        } catch (\Exception $e) {
            Log::error('Error in findCompetitorsWithLlm: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
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

            // Check if this competitor already exists by website or name
            $existingOrganization = Organization::where('campaign_id', $this->prompt->campaign_id)
                ->where(function ($query) use ($competitor) {
                    $query->where('name', $competitor['name']);
                    if (!empty($competitor['website'])) {
                        $query->orWhere('website', $competitor['website']);
                    }
                })
                ->first();

            if (!$existingOrganization) {
                // Create new competitor organization
                $competitorOrg = Organization::create([
                    'team_id' => $this->prompt->team_id,
                    'campaign_id' => $this->prompt->campaign_id,
                    'name' => $competitor['name'],
                    'website' => $competitor['website'] ?? null,
                    'is_competitor' => true,
                ]);

                // Create a term for the competitor name
                $nameTerm = Term::create([
                    'team_id' => $this->prompt->team_id,
                    'organization_id' => $competitorOrg->id,
                    'name' => $competitor['name'],
                ]);

                // Create a term for the competitor website if it exists
                if (!empty($competitor['website'])) {
                    $websiteTerm = Term::create([
                        'team_id' => $this->prompt->team_id,
                        'organization_id' => $competitorOrg->id,
                        'name' => $competitor['website'],
                    ]);
                }

                // Dispatch a job to check past responses for this term
                $jobDispatcher = app(JobDispatcherService::class);
                $jobDispatcher->dispatch($nameTerm, new CheckTermInPastResponsesJob($nameTerm));

                if (!empty($competitor['website'])) {
                    $jobDispatcher->dispatch($websiteTerm, new CheckTermInPastResponsesJob($websiteTerm));
                }

                $createdCount++;
            }
        }

        return $createdCount;
    }
}
