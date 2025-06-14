<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ArticleVersion;
use App\Models\Article;

class GenerateArticleVersions extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'article:generate-versions';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Generate initial versions for all articles that do not have versions';

	/**
	 * Execute the console command.
	 *
	 * @return int
	 */
	public function handle()
	{
		$this->info('Generating versions for articles...');

		// Get all articles
		$articles = Article::all();

		$this->info("Found {$articles->count()} articles");

		$processed = 0;
		$created = 0;

		foreach ($articles as $article) {
			$processed++;

			// Check if the article already has versions
			$hasVersions = ArticleVersion::where('article_id', $article->id)->exists();

			if (!$hasVersions) {
				// Create initial version
				ArticleVersion::create([
					'article_id' => $article->id,
					'version_number' => 1,
					'data' => $article->getAttributes(),
				]);

				// Set current version to 1
				$article->current_version = 1;
				$article->saveQuietly();

				$created++;
			}

			if ($processed % 100 === 0) {
				$this->info("Processed {$processed} articles...");
			}
		}

		$this->info("Completed! Created versions for {$created} articles out of {$processed} total articles.");

		return 0;
	}
}
