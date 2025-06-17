<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use Illuminate\Support\Facades\Config;
use App\Models\Article;
use App\Services\JobDispatcherService;

class GenerateArticleFromDeepResearchJob extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 5;

	/**
	 * The number of seconds the job can run before timing out.
	 *
	 * @var int
	 */
	public $timeout = 60;

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
	}

	/**
	 * Execute the job.
	 *
	 * @param  \App\Services\JobDispatcherService  $jobDispatcher
	 * @return void
	 */
	public function handle(JobDispatcherService $jobDispatcher)
	{
		if ($this->isCancelled()) return;

		try {
			// Get Perplexity API key from config
			$apiKey = Config::get('services.perplexity.key');

			if (!$apiKey) {
				throw new \Exception('Perplexity API key not configured');
			}

			// Check if we already have a request ID (polling scenario)
			if ($this->model->perplexity_request_id) {
				return $this->checkResearchStatus($jobDispatcher, $apiKey);
			} else {
				// Initial run - start the research
				return $this->startResearch($jobDispatcher, $apiKey);
			}
		} catch (Throwable $exception) {
			Log::error('Deep research job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}

	/**
	 * Start a new deep research request with Perplexity.
	 *
	 * @param  \App\Services\JobDispatcherService  $jobDispatcher
	 * @param  string  $apiKey
	 * @return void
	 */
	protected function startResearch(JobDispatcherService $jobDispatcher, string $apiKey)
	{
		$this->markJobAsStarted('Initiating deep research for article: ' . $this->model->title);

		// Load related models for context
		$this->model->load(['prompt', 'organization']);

		// Construct the query for Perplexity
		$query = $this->buildResearchQuery();

		// Make API request to start async research using chat completions
		$response = Http::withHeaders([
			'Authorization' => 'Bearer ' . $apiKey,
			'Content-Type' => 'application/json',
		])->post('https://api.perplexity.ai/async/chat/completions', [
			'model' => 'sonar-deep-research',
			'messages' => [
				[
					'role' => 'system',
					'content' => 'You are a professional content writer creating well-researched, comprehensive articles. Use markdown formatting for structure. Include citations where appropriate.'
				],
				[
					'role' => 'user',
					'content' => $query
				]
			],
			'temperature' => 0.7,
			'max_tokens' => 4000
		]);

		if ($response->successful()) {
			$data = $response->json();
			$requestId = $data['id'];
			$status = $data['status'];

			// Update article with request ID and status
			$this->model->perplexity_request_id = $requestId;
			$this->model->perplexity_status = $status;
			$this->model->save();

			$this->updateJobProgress(10, 'Perplexity deep research initiated. Request ID: ' . $requestId);

			// Re-queue the job to check status after 60 seconds
			$this->release(60);
		} else {
			$errorMessage = $response->json()['error'] ?? 'Unknown API error';
			throw new \Exception('Failed to start Perplexity deep research: ' . $errorMessage);
		}
	}

	/**
	 * Check the status of an existing research request.
	 *
	 * @param  \App\Services\JobDispatcherService  $jobDispatcher
	 * @param  string  $apiKey
	 * @return void
	 */
	protected function checkResearchStatus(JobDispatcherService $jobDispatcher, string $apiKey)
	{
		$requestId = $this->model->perplexity_request_id;
		$this->updateJobProgress(20, 'Checking Perplexity deep research status for request: ' . $requestId);

		// Make API request to check async chat completion status
		$response = Http::withHeaders([
			'Authorization' => 'Bearer ' . $apiKey,
			'Content-Type' => 'application/json',
		])->get('https://api.perplexity.ai/async/chat/completions/' . $requestId);

		if ($response->successful()) {
			$data = $response->json();
			$status = $data['status'];

			// Update article status
			$this->model->perplexity_status = $status;
			$this->model->save();

			if ($status === 'completed') {
				// Research is complete, update article content
				$this->updateJobProgress(80, 'Perplexity deep research completed. Processing results.');

				if (isset($data['choices'][0]['message']['content'])) {
					$content = $data['choices'][0]['message']['content'];
					$formattedContent = $this->formatContentAsHtml($content);

					// Update article with the research content
					$this->model->content = $formattedContent;
					$this->model->save();

					$this->updateJobProgress(100, 'Article content updated with deep research results.');
					$this->markJobAsCompleted('Deep research completed and article updated.');
				} else {
					throw new \Exception('No content found in completed Perplexity deep research');
				}
			} elseif ($status === 'in_progress') {
				// Still processing, update progress and re-queue
				$this->updateJobProgress(40, 'Perplexity deep research still in progress. Re-queuing check.');
				$this->release(60);
			} elseif ($status === 'failed') {
				$errorMessage = $data['error'] ?? 'Perplexity deep research task failed: Unknown error';
				$this->markJobAsFailed($errorMessage);
			} else {
				$this->updateJobProgress(30, 'Perplexity deep research status: ' . $status . '. Re-queuing check.');
				$this->release(60);
			}
		} else {
			$errorMessage = $response->json()['error'] ?? 'Unknown API error';
			throw new \Exception('Failed to check Perplexity deep research status: ' . $errorMessage);
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

		$query .= "The article should be structured with appropriate headings, be comprehensive, and include specific facts, figures, and examples where relevant. Format the content using markdown for headings, lists, and emphasis.";

		return $query;
	}

	/**
	 * Format the markdown content as HTML.
	 *
	 * @param  string  $content
	 * @return string
	 */
	protected function formatContentAsHtml(string $content)
	{
		// This is a simple implementation - in a real app, you might use a proper markdown parser
		// For now, we'll do some basic replacements

		// Replace markdown headings with HTML headings
		$content = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $content);
		$content = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $content);
		$content = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $content);

		// Replace markdown lists with HTML lists
		$content = preg_replace('/^\* (.*?)$/m', '<li>$1</li>', $content);
		$content = preg_replace('/^- (.*?)$/m', '<li>$1</li>', $content);

		// Wrap lists in <ul> tags
		$content = preg_replace('/(<li>.*?<\/li>)\s+(?!<li>)/s', "$1\n</ul>\n", $content);
		$content = preg_replace('/(?<!<\/ul>)\s+(<li>)/s', "\n<ul>\n$1", $content);

		// Replace markdown emphasis with HTML tags
		$content = preg_replace('/\*\*(.*?)\*\*/s', '<strong>$1</strong>', $content);
		$content = preg_replace('/\*(.*?)\*/s', '<em>$1</em>', $content);

		// Replace markdown links with HTML links
		$content = preg_replace('/\[(.*?)\]\((.*?)\)/s', '<a href="$2">$1</a>', $content);

		// Replace newlines with <p> tags
		$content = '<p>' . str_replace("\n\n", '</p><p>', $content) . '</p>';

		return $content;
	}
}
