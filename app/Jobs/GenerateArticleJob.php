<?php

namespace App\Jobs;

use Throwable;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use App\Tools\SearchApiTool;
use App\Models\Article;
use App\Models\Prompt;
use App\Models\Organization;

class GenerateArticleJob extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 3;

	/**
	 * The prompt to generate an article for.
	 *
	 * @var \App\Models\Prompt
	 */
	public $model;

	/**
	 * The organization associated with the article.
	 *
	 * @var \App\Models\Organization
	 */
	protected $organization;

	/**
	 * The team ID.
	 *
	 * @var int
	 */
	protected $teamId;

	/**
	 * Create a new job instance.
	 *
	 * @param  \App\Models\Prompt  $prompt
	 * @param  \App\Models\Organization  $organization
	 * @param  int  $teamId
	 * @return void
	 */
	public function __construct(Prompt $prompt, Organization $organization, int $teamId)
	{
		$this->model = $prompt;
		$this->organization = $organization;
		$this->teamId = $teamId;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle()
	{
		if ($this->isCancelled()) return;

		$this->markJobAsStarted('Generating article for prompt "' . $this->model->content . '"');

		try {
			$searchApiTool = new SearchApiTool();

			// System instructions for the LLM
			$systemInstructions = "You are a helpful, knowledgeable assistant that writes structured, question-and-answer style articles to help businesses increase their visibility in LLM completions for specific prompts.

Your responsibilities include:
- Writing a credible, semantic article that directly answers a prompt and will increase our visibility in LLM completions
- Writing an article title that is optimized and relevant to the prompt

Your output must be:
- Human readable
- Structured as a Q&A article
- Written in clear, neutral, helpful language
- Optimized for inclusion in LLM responses, not just search engines

You can use the search api tool to search google for information, high-ranking competitors, awards, reviews, relevant stats or proof points and anything else you need to generate the most effective article possible.

Content Guidelines:
- Directly address the target prompt early in the intro and use close variants naturally throughout the article.
- Use neutral, expert language. Avoid salesy or exaggerated phrases unless citing a real award or review.
- Be informative and structured, as if you are answering user questions in an assistant-like tone.
- Write in a way that is easy for a language model to parse, quote, or summarize.
- Use specific data when available.";

			// User message with prompt and organization details
			$userMessage = "Generate a credible, semantic article for {$this->organization->name} ({$this->organization->website}) that will increase our visibility in LLM completions.
Do not make {$this->organization->name} the focus of the article and only include us where relevant.
Most importantly, directly answers this prompt in the most authentic, honest attempt to help users: \"{$this->model->content}\"";

			$this->updateJobProgress(25, 'Generating article content');

			// Generate the article text using Prism
			$textResponse = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withMaxSteps(15)
				->withMessages([
					new SystemMessage($systemInstructions),
					new UserMessage($userMessage)
				])
				->withTools([$searchApiTool])
				->withToolChoice(ToolChoice::Auto)
				->asText();

			$this->updateJobProgress(75, 'Processing article structure');

			// Define schema for structured output
			$schema = new ObjectSchema(
				name: 'article',
				description: 'Article with title and content.',
				properties: [
					new StringSchema(
						name: 'title',
						description: 'The title of the article'
					),
					new StringSchema(
						name: 'content',
						description: 'The full content of the article in HTML format'
					)
				],
				requiredFields: ['title', 'content']
			);

			// Process the text response to get structured output
			$response = Prism::structured()
				->using(Provider::OpenAI, 'gpt-4o')
				->withSchema($schema)
				->withPrompt('Here is an article. Please extract the title and content, and convert the content to valid html: ' . $textResponse->text)
				->asStructured();

			$result = $response->structured;

			$this->updateJobProgress(90, 'Saving article');

			// Create the article record
			Article::create([
				'team_id' => $this->teamId,
				'organization_id' => $this->organization->id,
				'prompt_id' => $this->model->id,
				'title' => $result['title'],
				'content' => $result['content'],
			]);

			// Mark the job as completed
			$this->markJobAsCompleted('Created article for prompt "' . $this->model->content . '"');
		} catch (Throwable $exception) {
			Log::error('Article generation job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
