<?php

namespace App\Jobs;

use Throwable;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Batchable;
use App\Tools\SearchApiTool;
use App\Services\JobDispatcherService;
use App\Models\Response;
use App\Models\Prompt;
use App\Models\Term;

class RunPromptJob extends TrackableJob
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
	 * The model to use for job tracking.
	 *
	 * @var \Illuminate\Database\Eloquent\Model
	 */
	public $model;

	/**
	 * The providers to use for running the prompt.
	 *
	 * @var array
	 */
	protected $providers;

	/**
	 * The team ID.
	 *
	 * @var int
	 */
	protected $teamId;

	/**
	 * The campaign ID.
	 *
	 * @var int
	 */
	protected $campaignId;

	/**
	 * Available LLM providers.
	 *
	 * @var array
	 */
	private array $availableProviders = [
		'openai' => ['gpt-4o', Provider::OpenAI],
		'anthropic' => ['claude-3-5-haiku-latest', Provider::Anthropic],
		'gemini' => ['gemini-pro', Provider::Gemini],
		'xai' => ['grok-1', Provider::XAI],
		'deepseek' => ['deepseek-chat', Provider::DeepSeek],
	];

	/**
	 * Create a new job instance.
	 *
	 * @param  \App\Models\Prompt  $prompt
	 * @param  array  $providers
	 * @param  int  $teamId
	 * @return void
	 */
	public function __construct(Prompt $prompt, array $providers = [], int $teamId, int $campaignId)
	{
		$this->model = $prompt;
		$this->teamId = $teamId;
		$this->campaignId = $campaignId;
		$this->prompt = $prompt;
		$this->providers = $providers;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle(JobDispatcherService $jobDispatcher)
	{
		try {
			if ($this->isCancelled()) {
				return;
			}
			// Mark the job as started
			$this->markJobAsStarted('Running a prompt');

			// If no providers specified, use all
			if (!$this->providers) {
				$this->providers = array_keys($this->availableProviders);
			}

			$responses = [];

			$this->updateJobProgress(20, 'Preparing to send prompt to LLMs');

			// Run the prompt with each provider
			$totalProviders = count($this->providers);
			$currentProvider = 0;

			foreach ($this->providers as $providerName) {
				$currentProvider++;
				$progress = 20 + (60 * ($currentProvider / $totalProviders));

				// Setup the LLM provider
				if (!isset($this->availableProviders[$providerName])) {
					continue;
				}

				[$model, $provider] = $this->availableProviders[$providerName];

				$this->updateJobProgress((int)$progress, 'Sending prompt "' . substr($this->prompt->content, 0, 50) . (strlen($this->prompt->content) > 50 ? '...' : '') . '" to ' . $providerName);

				try {
					// Get response from the LLM
					$llm = $this->getLlmResponse($this->prompt->content, $model, $provider);

					// Store the LLM's response
					$response = $this->prompt->responses()->create([
						'provider' => $providerName,
						'model' => $model,
						'content' => $llm->responseMessages->last()->content,
						'metadata' => [
							'usage' => $llm->usage ?? null,
						],
					]);

					$responses[] = $response;

					// Save search tool results
					$this->saveSearchToolResults($llm->steps, $response);

					// Check for terms in the response
					$this->checkForTerms($response, $this->prompt);
				} catch (\Exception $e) {
					// Log the error but continue with other providers
					Log::error('Error running prompt: ' . $e);
				}
			}

			$this->updateJobProgress(90, 'Processing LLM response');

			// Find competitors in response if this is the first response to this prompt
			if ($this->prompt->responses()->count() == 1) {
				$jobDispatcher->dispatch($this->prompt, new FindCompetitorsInResponseJob($this->prompt, $this->prompt->team_id));
			}

			// Mark the job as completed
			$this->markJobAsCompleted('Successfully generated ' . count($responses) . ' responses for prompt');
		} catch (Throwable $exception) {
			Log::error('Prompt run failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}

	/**
	 * Get response from the LLM.
	 *
	 * @param  string  $promptContent
	 * @param  string  $model
	 * @param  Provider  $provider
	 * @return mixed
	 */
	private function getLlmResponse(string $promptContent, string $model, Provider $provider)
	{
		$searchApiTool = new SearchApiTool();

		$response = Prism::text()
			->using($provider, $model)
			->withMaxSteps(10)
			->withMessages([new UserMessage($promptContent)])
			->withTools([$searchApiTool])
			->withToolChoice(ToolChoice::Auto)
			->asText();

		return $response;
	}

	/**
	 * Save search tool results.
	 *
	 * @param  iterable  $steps
	 * @param  Response  $response
	 * @return void
	 */
	private function saveSearchToolResults(iterable $steps, Response $response): void
	{
		$searchQueries = [];

		foreach ($steps as $step) {
			foreach ($step->toolResults as $tool) {
				if ($tool->toolName == 'search_api') {
					$searchQueries[] = $tool->args['query'];
				}
			}
		}

		$response->update(['search' => ['queries' => $searchQueries]]);
	}

	/**
	 * Check for terms in the response.
	 *
	 * @param  iterable  $terms
	 * @param  Response  $response
	 * @param  Prompt  $prompt
	 * @return void
	 */
	private function checkForTerms(Response $response, Prompt $prompt): void
	{
		// Get terms for all organizations scoped to the team and campaign
		$terms = Term::whereHas('organization', function ($query) {
			$query->where("campaign_id", $this->campaignId);
		})->get();

		$responseText = strtolower($response->content);
		$foundTerms = [];

		foreach ($terms as $term) {
			$termName = strtolower($term->name);

			// Check if the term exists in the response
			if (str_contains($responseText, $termName)) {
				$foundTerms[] = $term->id;

				// Check if the relationship already exists
				$existingRelation = $prompt->terms()->where('term_id', $term->id)->exists();

				if (!$existingRelation) {
					// Create new relationship
					$prompt->terms()->attach($term->id, [
						'count' => 1,
						'last_found_at' => now(),
						'created_at' => now(),
						'updated_at' => now(),
					]);
				} else {
					// Update existing relationship
					$prompt->terms()->updateExistingPivot($term->id, [
						'count' => DB::raw('count + 1'),
						'last_found_at' => now(),
						'updated_at' => now(),
					]);
				}
			}
		}

		// Attach found terms to the response and record a mention
		if (!empty($foundTerms)) {
			$response->terms()->syncWithoutDetaching($foundTerms);
		}
	}
}
