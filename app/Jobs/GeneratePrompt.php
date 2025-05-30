<?php

namespace App\Jobs;

use Throwable;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Bus\Batchable;
use App\Tools\SearchApiTool;
use App\Services\JobDispatcherService;
use App\Models\Team;
use App\Models\Response;
use App\Models\Prompt;
use App\Models\Keyword;

class GeneratePrompt extends TrackableJob
{
	use Batchable;

	/**
	 * The number of times the job may be attempted.
	 *
	 * @var int
	 */
	public $tries = 3;

	/**
	 * The model to use for job tracking.
	 *
	 * @var \Illuminate\Database\Eloquent\Model
	 */
	public $model;

	/**
	 * The team ID.
	 *
	 * @var int
	 */
	protected $teamId;

	/**
	 * The term to generate a prompt for.
	 *
	 * @var string
	 */
	protected $term;

	/**
	 * The location to be used in prompts.
	 *
	 * @var string
	 */
	protected $location;

	/**
	 * Create a new job instance.
	 *
	 * @param  \App\Models\Prompt  $prompt
	 * @param  array  $providers
	 * @param  int  $teamId
	 * @return void
	 */
	public function __construct($model, int $teamId, string $term, string $location)
	{
		$this->model = $model;
		$this->teamId = $teamId;
		$this->term = $term;
		$this->location = $location;
	}

	/**
	 * Execute the job.
	 *
	 * @return void
	 */
	public function handle(JobDispatcherService $jobDispatcher)
	{
		try {
			// Mark the job as started
			$this->markJobAsStarted(`Generating prompt from term "{$this->term}"`);

			$searchApiTool = new SearchApiTool();

			$textResponse = Prism::text()
				->using(Provider::OpenAI, 'gpt-4o')
				->withMaxSteps(10)
				->withMessages([new UserMessage("Here is a keyword term: \"" . $this->term . "\". Your job is to turn the term into a statement, question, or prompt that a person would likely put into ChatGPT.
You also need to incorporate the end-user's location \"" . $this->location . "\" in the prompt.
The prompt should elicit a response that mentions specific brands. So, let's pretend you are given the keyword term, \"car loan\" and the location is Colorado. In that case, an example of an acceptable prompt is, \"Where in Colorado can I get the best car loan?\" because ChatGPT is likely to respond to that prompt with a list of organizations that can provide a loan. On the other hand, a bad example is, \"Tell me about auto loans\", because that's likely to elicit a response that gives general information rather than recommending specific companies.
Also, remember to keep the prompts simple. Don't make assumptions about the intent behind the keyword.
Output your suggested prompt as plain text, without quotation marks, or any type of formatting.")])
				->withTools([$searchApiTool])
				->withToolChoice(ToolChoice::Auto)
				->asText();

			$this->updateJobProgress(50, `Storing new prompt from term "{$this->term}"`);

			$prompt = Prompt::create([
				'team_id' => $this->teamId,
				'content' => $textResponse->text
			]);

			// Mark the job as completed
			$this->markJobAsCompleted('Running the new prompt');

			// Run the prompt
			$jobDispatcher->dispatch($prompt, new RunPromptJob($prompt, ['openai'], $prompt->team_id));
		} catch (Throwable $exception) {
			Log::error('Prompt generation job failed: ' . $exception->getMessage());
			$this->markJobAsFailed($exception);
			throw $exception;
		}
	}
}
