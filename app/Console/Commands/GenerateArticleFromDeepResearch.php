<?php

namespace App\Console\Commands;

use App\Jobs\GenerateArticleFromDeepResearchJob;
use App\Models\Article;
use Illuminate\Console\Command;

class GenerateArticleFromDeepResearch extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'article:deep-research {article_id : The ID of the article to generate content for}';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate article content using Perplexity Deep Research API';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$articleId = $this->argument('article_id');
		
		// Find the article
		$article = Article::find($articleId);
		
		if (!$article) {
			$this->error("Article with ID {$articleId} not found.");
			return 1;
		}
		
		// Check if article has a prompt
		if (!$article->prompt_id) {
			$this->error("Article with ID {$articleId} does not have an associated prompt.");
			return 1;
		}
		
		// Get the team ID from the article
		$teamId = $article->team_id;
		
		$this->info("Dispatching deep research job for article: {$article->title}");
		
		// Dispatch the job
		GenerateArticleFromDeepResearchJob::dispatch($article, $teamId);
		
		$this->info("Job dispatched successfully. You can monitor the job progress in the jobs dashboard.");
		
		return 0;
	}
}
