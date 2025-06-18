<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Bus\Batchable;
use App\Models\Article;
use App\Events\ArticleDeepResearchUpdated;
use App\Services\PerplexityService;

class GenerateArticleFromDeepResearchJob extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 1;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public $timeout = 30;

	/**
	 * The article model to update with deep research content.
	 *
	 * @var \App\Models\Article
	 */
	public $model;

	/**
	 * The team ID.
	 *
	 * @var int
	 */
	protected $teamId;

	/**
	 * The Perplexity service instance.
	 *
	 * @var \App\Services\PerplexityService
	 */
	protected $perplexityService;

	/**
	 * Create a new job instance.
	 *
	 * @param  \App\Models\Article  $article
	 * @param  int  $teamId
	 * @return void
	 */
	public function __construct(Article $article, int $teamId)
	{
		$this->model = $article;
		$this->teamId = $teamId;
		$this->perplexityService = new PerplexityService();
	}

	/**
	 * Execute the job.
	 *
	 * @param  \App\Services\JobDispatcherService  $jobDispatcher
	 * @return void
	 */
	public function handle()
	{
		if ($this->isCancelled()) return;

		try {
			// Check if we already have a request ID (polling scenario)
			if ($this->model->perplexity_request_id) {
				return $this->checkResearchStatus();
			} else {
				// Initial run - start the research
				return $this->startResearch();
			}
		} catch (Throwable $exception) {
			$errorMessage = $exception->getMessage();
			$this->markJobAsFailed(new \Exception($errorMessage));
			throw $exception;
		}
	}

	/**
	 * Start a new deep research request with Perplexity.
	 *
	 * @return void
	 */
	protected function startResearch()
	{
		$this->markJobAsStarted('Initiating deep research for article: ' . $this->model->title);

		// Load related models for context
		$this->model->load(['prompt', 'organization']);

		// Construct the query for Perplexity
		$query = $this->buildResearchQuery();

		// Prepare messages for the Perplexity API
		$messages = [
			[
				'role' => 'system',
				'content' => 'You are a professional content writer creating well-researched, comprehensive articles. Include citations where appropriate.'
			],
			[
				'role' => 'user',
				'content' => $query . ' IMPORTANT: Write the article content in HTML format using where necessary <h1>, <h2>, <h3>, <h4>, <p>, <b>, <i>, <ul>, <li>, <blockquote>, <table> and <a> tags.'
			]
		];

		try {
			// Use PerplexityService to create async chat completion
			$data = $this->perplexityService->createAsyncChatCompletion(
				messages: $messages,
				model: 'sonar-deep-research',
				temperature: 0.7,
				maxTokens: 10000,
				reasoningEffort: 'medium'
			);

			$requestId = $data['id'];
			$status = $data['status'];

			// Update article with request ID, status, and initialize perplexity_checks
			$this->model->perplexity_request_id = $requestId;
			$this->model->perplexity_status = $status;
			$this->model->perplexity_checks = 1;
			$this->model->save();

			$this->updateJobProgress(10, 'Perplexity deep research initiated. Request ID: ' . $requestId);

			// Re-queue the job to check status with a 10-second delay
			self::dispatch($this->model, $this->teamId)->delay(now()->addSeconds(10));
		} catch (\Exception $e) {
			throw new \Exception('Failed to start Perplexity deep research: ' . $e->getMessage());
		}
	}

	/**
	 * Check the status of an existing research request.
	 *
	 * @return void
	 */
	protected function checkResearchStatus()
	{
		$requestId = $this->model->perplexity_request_id;
		$this->updateJobProgress(20, 'Checking Perplexity deep research status for request: ' . $requestId);

		try {
			// Use PerplexityService to check async chat completion status
			$data = $this->perplexityService->getAsyncChatCompletionStatus($requestId);
			$status = $data['status'];

			// Update article status
			$this->model->perplexity_status = $status;
			$this->model->save();

			if ($status === 'COMPLETED') {
				// Log the response structure to understand where the content is located
				Log::info('Perplexity completed response structure: ' . json_encode($data));

				// Dispatch ArticleDeepResearchUpdated event to notify frontend to refresh article
				ArticleDeepResearchUpdated::dispatch($this->model);

				$this->updateJobProgress(100, 'Article content updated with deep research results.');
				$this->markJobAsCompleted('Deep research completed and article updated.');
			} elseif ($status === 'IN_PROGRESS') {
				// Check if we've reached the maximum number of checks
				if ($this->model->perplexity_checks >= 60) {
					$this->markJobAsFailed(new \Exception('Exceeded maximum number of perplexity checks (60)'));
					return;
				}

				// Still processing, update progress and re-queue
				$this->updateJobProgress(40, 'Perplexity deep research still in progress. Re-queuing check.');

				// Increment the perplexity_checks counter
				$this->model->increment('perplexity_checks');

				// Dispatch a new job instance instead of releasing
				self::dispatch($this->model, $this->teamId)->delay(now()->addSeconds(10));
			} else {
				// Check if we've reached the maximum number of checks
				if ($this->model->perplexity_checks >= 50) {
					$this->markJobAsFailed(new \Exception('Exceeded maximum number of perplexity checks (50)'));
					return;
				}

				$this->updateJobProgress(30, 'Perplexity deep research status: ' . $status . '. Re-queuing check.');

				// Increment the perplexity_checks counter
				$this->model->increment('perplexity_checks');

				// Dispatch a new job instance instead of releasing
				self::dispatch($this->model, $this->teamId)->delay(now()->addSeconds(10));
			}
		} catch (\Exception $e) {
			throw new \Exception('Failed to check Perplexity deep research status: ' . $e->getMessage());
		}
	}

	/**
	 * Build the research query based on article context.
	 *
	 * @return string
	 */
	protected function buildResearchQuery()
	{
		$organization = $this->model->organization;
		$prompt = $this->model->prompt;

		$query = "Generate a comprehensive, well-researched article that answers this prompt: \"{$prompt->content}\". ";

		if ($organization) {
			$query .= "Include information about {$organization->name} ";

			if ($organization->website) {
				$query .= "({$organization->website}) ";
			}

			$query .= "where appropriate, but make the article primarily focused on answering the prompt with factual, up-to-date information. ";

			if ($organization->industry) {
				$query .= "The article should be relevant to the {$organization->industry} industry. ";
			}

			if ($organization->description) {
				$query .= "Additional context about the organization: {$organization->description}. ";
			}
		}

		$query .= "The article should be structured with appropriate headings, be comprehensive, and include specific facts, figures, and examples where relevant.";

		return $query;
	}
}
