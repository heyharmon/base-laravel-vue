<?php

namespace App\Jobs;

use Throwable;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Models\Article;

class GenerateArticleMetaTitleJob extends TrackableJob implements ShouldQueue
{
    use Queueable, Dispatchable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The article to generate meta title for.
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

        $this->markJobAsStarted('Generating meta title for article "' . $this->model->title . '"');

        try {
            $this->updateJobProgress(25, 'Analyzing article content');

            // Get article content
            $title = $this->model->title;
            $content = $this->model->content;

            $this->updateJobProgress(50, 'Generating SEO optimized meta title');

            // Generate meta title using Prism
            $prompt = "Generate an SEO optimized meta title for the following article. " .
                "The meta title should be concise (60-70 characters), compelling, include primary keywords, " .
                "and accurately represent the content. Do not use quotes in your response, just return the meta title text.\n\n" .
                "Article Title: {$title}\n\n" .
                "Article Content: {$content}";

            $response = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4o')
                ->withPrompt($prompt)
                ->asText();

            $metaTitle = trim($response->text);

            $this->updateJobProgress(75, 'Saving meta title');

            // Update the article with the generated meta title
            $this->model->update([
                'meta_title' => $metaTitle
            ]);

            $this->markJobAsCompleted('Generated meta title for article "' . $this->model->title . '"');
        } catch (Throwable $exception) {
            Log::error('Meta title generation job failed: ' . $exception->getMessage());
            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }
}
