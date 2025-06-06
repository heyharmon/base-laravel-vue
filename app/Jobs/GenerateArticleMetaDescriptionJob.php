<?php

namespace App\Jobs;

use Throwable;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use App\Models\Article;

class GenerateArticleMetaDescriptionJob extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 1;

	/**
	 * The article to generate meta description for.
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
	 * @return void
	 */
	public function handle(): void
	{
		if ($this->isCancelled()) return;

		$this->markJobAsStarted('Generating meta description for article "' . $this->model->title . '"');

		try {
			$this->updateJobProgress(25, 'Analyzing article content');

			// Get article content
			$title = $this->model->title;
			$content = $this->model->content;

			$this->updateJobProgress(50, 'Generating SEO optimized meta description');

			// Generate meta description using Prism
			$prompt = "Generate an SEO optimized meta description for the following article. " .
				"The meta description should be concise (150-160 characters), compelling, include primary keywords, " .
				"and provide a clear summary of what the reader will learn. Do not use quotes in your response, " .
				"just return the meta description text.\n\n" .
				"Article Title: {$title}\n\n" .
				"Article Content: {$content}";

			$response = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withPrompt($prompt)
				->asText();

			$metaDescription = trim($response->text);

			$this->updateJobProgress(75, 'Saving meta description');

			// Update the article with the generated meta description
			$this->model->update([
				'meta_description' => $metaDescription
			]);

			$this->markJobAsCompleted('Generated meta description for article "' . $this->model->title . '"');
		} catch (Throwable $exception) {
			Log::error('Meta description generation job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
