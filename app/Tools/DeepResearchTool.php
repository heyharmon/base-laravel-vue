<?php

namespace App\Tools;

use Illuminate\Support\Facades\Log;
use App\Models\Article;
use App\Jobs\GenerateArticleFromDeepResearchJob;
use App\Events\ArticleUpdated;

class DeepResearchTool
{
	/**
	 * Get the schema definition for the deep_research tool.
	 */
	public static function getSchema(): array
	{
		return [
			'type' => 'function',
			'name' => 'deep_research',
			'description' => 'Generate comprehensive article content using Perplexity Deep Research API. Use this tool when an article has no content and needs in-depth research.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'reason' => [
						'type'        => 'string',
						'description' => 'The reason for initiating deep research (optional)'
					]
				],
				'required' => []
			]
		];
	}

	/**
	 * Execute the deep_research tool.
	 * Dispatches the GenerateArticleFromDeepResearchJob for the current article.
	 */
	public function execute(array $arguments, ?Article $currentArticle = null): array
	{
		if (!$currentArticle) {
			Log::error('Cannot initiate deep research: No article context provided.');
			return [
				'success' => false,
				'message' => 'No article context available for deep research.'
			];
		}

		// Check if the article has a prompt
		if (!$currentArticle->prompt_id) {
			return [
				'success' => false,
				'message' => 'The article must have an associated prompt to use deep research.'
			];
		}

		try {
			// Reset article perplexity request id and status
			$currentArticle->perplexity_request_id = null;
			$currentArticle->perplexity_status = null;
			$currentArticle->save();

			// Get the team ID from the article
			$teamId = $currentArticle->team_id;

			// Dispatch the job using standard Laravel dispatch
			GenerateArticleFromDeepResearchJob::dispatch($currentArticle, $teamId);

			// Dispatch ArticleUpdated event to update article in real-time
			ArticleUpdated::dispatch($currentArticle);

			return [
				'success'   => true,
				'message'   => 'Deep research job has been dispatched. The article content will be generated using Perplexity\'s Deep Research API. This process may take several minutes to complete.',
				'article_id' => $currentArticle->id,
			];
		} catch (\Exception $e) {
			Log::error('Error dispatching deep research job: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Failed to initiate deep research: ' . $e->getMessage()
			];
		}
	}
}
