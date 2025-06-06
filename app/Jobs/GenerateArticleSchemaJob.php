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
use App\Models\Organization;

class GenerateArticleSchemaJob extends TrackableJob implements ShouldQueue
{
    use Queueable, Dispatchable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The article to generate schema for.
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

        $this->markJobAsStarted('Generating schema for article "' . $this->model->title . '"');

        try {
            $this->updateJobProgress(25, 'Analyzing article content');

            // Get article content and organization details
            $title = $this->model->title;
            $content = $this->model->content;
            $organization = $this->model->organization;
            $orgName = $organization ? $organization->name : '';
            $orgWebsite = $organization ? $organization->website : '';

            $this->updateJobProgress(50, 'Generating structured data schema');

            // Generate schema using Prism
            $prompt = "Generate JSON-LD structured data for the following article. " .
                "Include both Article schema (https://developers.google.com/search/docs/appearance/structured-data/article) " .
                "and QAPage schema (https://developers.google.com/search/docs/appearance/structured-data/qapage) if the article contains questions and answers. " .
                "Return only the JSON-LD code without any explanation or markdown formatting.\n\n" .
                "Article Title: {$title}\n\n" .
                "Article Content: {$content}\n\n" .
                "Organization Name: {$orgName}\n\n" .
                "Organization Website: {$orgWebsite}\n\n" .
                "Current Date: " . date('Y-m-d') . "\n\n" .
                "Format the output as a single <script type=\"application/ld+json\"> element containing all schema types.";

            $response = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4o')
                ->withPrompt($prompt)
                ->asText();

            // Extract just the JSON-LD content
            $schemaText = trim($response->text);

            // Remove any markdown code block formatting if present
            $schemaText = preg_replace('/^```json\s*|```\s*$/m', '', $schemaText);

            // Remove any script tags if they were included
            $schemaText = preg_replace('/<script[^>]*>|<\/script>/i', '', $schemaText);

            $this->updateJobProgress(75, 'Saving schema');

            // Update the article with the generated schema
            $this->model->update([
                'schema' => $schemaText
            ]);

            $this->markJobAsCompleted('Generated schema for article "' . $this->model->title . '"');
        } catch (Throwable $exception) {
            Log::error('Schema generation job failed: ' . $exception->getMessage());
            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }
}
