<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Bus\Queueable;
use Illuminate\Bus\Batchable;
use App\Services\PerplexityService;
use App\Models\Prompt;
use App\Models\Article;
use App\Events\ArticleUpdated;
use App\Events\ArticleDeepResearchUpdated;

class GenerateArticleFromDeepResearchJob implements ShouldQueue
{
	use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
			Log::error('GenerateArticleFromDeepResearchJob failed: ' . $errorMessage);
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
		Log::info('Initiating deep research for article: ' . $this->model->title);

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
				'content' => $query
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

			Log::info('Perplexity deep research initiated. Request ID: ' . $requestId);

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
		Log::info('Checking Perplexity deep research status for request: ' . $requestId);

		try {
			// Use PerplexityService to check async chat completion status
			$data = $this->perplexityService->getAsyncChatCompletionStatus($requestId);
			$status = $data['status'];

			// Update article status
			$this->model->perplexity_status = $status;
			$this->model->save();

			// Dispatch ArticleDeepResearchUpdated event to notify frontend to refresh article
			ArticleDeepResearchUpdated::dispatch($this->model);

			if ($status === 'COMPLETED') {
				// Log the response structure to understand where the content is located
				Log::info('Perplexity completed response structure: ' . json_encode($data));
				Log::info('Article content updated with deep research results.');
				Log::info('Deep research completed and article updated.');
			} elseif ($status === 'IN_PROGRESS') {
				// Check if we've reached the maximum number of checks
				if ($this->model->perplexity_checks >= 60) {
					Log::error('Exceeded maximum number of perplexity checks (60)');
					throw new \Exception('Exceeded maximum number of perplexity checks (60)');
				}

				// Still processing, log status and re-queue
				Log::info('Perplexity deep research still in progress. Re-queuing check.');

				// Increment the perplexity_checks counter
				$this->model->increment('perplexity_checks');

				// Dispatch a new job instance instead of releasing
				self::dispatch($this->model, $this->teamId)->delay(now()->addSeconds(10));
			} else {
				// Check if we've reached the maximum number of checks
				if ($this->model->perplexity_checks >= 100) {
					Log::error('Exceeded maximum number of perplexity checks (50)');
					throw new \Exception('Exceeded maximum number of perplexity checks (50)');
				}

				Log::info('Perplexity deep research status: ' . $status . '. Re-queuing check.');

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

		$prompt = Prompt::with(['responses' => function ($query) {
			$query->latest()->limit(8);
		}])->find($this->model->prompt->id);

		$responseData = $prompt->responses->map(function ($response) {
			return "\n- {$response->content}";
		});

		$query = "I want to improve my visibility in LLM responses for a specific organization and topic. \n";

		$query .= "**Organization name:** \n";
		$query .= $organization->name . "\n\n";
		$query .= "**Organization website:** \n";
		$query .= $organization->website . "\n\n";
		$query .= "**Topic (the prompt given to LLMs):** \n";
		$query .= $prompt->content . "\n\n";
		$query .= "**LLM response data I've collected:** \n";
		$query .= $responseData . "\n\n";

		$query .= "**Article draft:** \n";
		$query .= $this->model->content . "\n\n";

		$query .= "Please do the following:\n";
		$query .= "1. Analyze the LLM response data thoroughly and identify what organizations or sources are being surfaced, what type of content is showing up, and why the content and those organizations are showing up.\n";
		$query .= "2. Recommend the **single most impactful thing** I should do to get my organization to show up in LLM responses. Specifically tell me:\n";
		$query .= "   - What type of content I should publish\n";
		$query .= "   - Where I should publish it (e.g. URL, internal links, directories)\n";
		$query .= "3. Write the full content I should publish inside a single code block for easy copy and paste.\n";
		$query .= "4. Evaluate the likelihood that this content will increase my visibility in LLM responses for the given prompt. Tell me how to **maximize** that likelihood (e.g. schema, backlinks, structure, freshness).\n";
		$query .= "Make the output strategic, accurate, and thorough. Use LLM optimization best practices.\n\n";

		$query .= "Use Deep Research to write a new version the article that is 10x more detailed and thorough. Make sure to link back to relevant pages on my website.\n\n";
		$query .= "If you mention competitors, make sure to emphasize why people should choose my organization over the competitors.\n\n";
		$query .= "Most importantly, remember that the purpose of the article is help people find and choose to work with my organization. Make sure to portray my organization in a positive light, accurately explain my products and services and how we can help our customers (or members). Lastly, make certain that the article is built around thoroughly answering the original prompt I gave you:\n\n";
		$query .= $prompt->content;

		$query .= "\n\n";
		$query .= "IMPORTANT: Write the article content in HTML format using where necessary <h1>, <h2>, <h3>, <h4>, <p>, <b>, <i>, <ul>, <li>, <blockquote>, <table> and <a> tags.";

		// Log this query
		Log::info('Deep Research query: ' . $query);

		return $query;
	}

	/**
	 * Handle a job failure.
	 *
	 * @param  \Throwable  $exception
	 * @return void
	 */
	public function failed(Throwable $exception)
	{
		Log::error('GenerateArticleFromDeepResearchJob failed: ' . $exception->getMessage());

		// You can add additional failure handling here if needed
		// For example, updating the article status or sending notifications
	}
}
