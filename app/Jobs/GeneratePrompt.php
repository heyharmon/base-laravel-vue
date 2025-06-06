<?php

namespace App\Jobs;

use Throwable;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\Prism;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\Enums\Provider;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Batchable;
use App\Tools\SearchApiTool;
use App\Services\JobDispatcherService;
use App\Models\Prompt;

class GeneratePrompt extends TrackableJob
{
    use Batchable;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

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
     * The keyword to generate a prompt for.
     *
     * @var string
     */
    protected $keyword;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Prompt  $prompt
     * @param  array  $providers
     * @param  int  $teamId
     * @return void
     */
    public function __construct($model, int $teamId, string $keyword)
    {
        $this->model = $model;
        $this->teamId = $teamId;
        $this->keyword = $keyword;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(JobDispatcherService $jobDispatcher)
    {
        if ($this->isCancelled()) return;

        $this->markJobAsStarted('Generating prompt from keyword "' . $this->keyword . '"');

        try {
            $searchApiTool = new SearchApiTool();

            // Create an array of messages
            $messages = [
                new UserMessage("Here is a keyword: \"" . $this->keyword . "\". Your job is to turn the keyword into a statement, question, or prompt that a person would likely put into ChatGPT.
The prompt should elicit a response that mentions specific brands. So, let's pretend you are given the keyword, \"car loan\". In that case, an example of an acceptable prompt is, \"Where can I get the best car loan?\" because ChatGPT is likely to respond to that prompt with a list of organizations that can provide a loan. On the other hand, a bad example is, \"Tell me about auto loans\", because that is likely to elicit a response that gives general information rather than recommending specific companies.")
            ];

            // Add location message conditionally if location is available
            if (isset($this->model->location) && !empty($this->model->location)) {
                $messages[] = new UserMessage("You also need to incorporate the brand location \"" . $this->model->location . "\" in the prompt when necessary.
So, again pretend you are given the keyword, \"car loan\" and the location is \"" . $this->model->location . "\". In that case, an example of an acceptable prompt is, \"Where in " . $this->model->location . " can I get the best car loan?\" because ChatGPT is likely to respond to that prompt with a list of organizations in " . $this->model->location . " that can provide a loan.");
            }

            // Add industry message conditionally if industry is available
            if (isset($this->model->industry) && !empty($this->model->industry)) {
                $messages[] = new UserMessage("You also need to incorporate the industry \"" . $this->model->industry . "\" in the prompt when it makes sense.
For example, if the keyword is related to the " . $this->model->industry . " industry, make sure your prompt specifically mentions or implies this industry context. This will help ChatGPT provide more targeted brand recommendations related to this specific industry.");
            }

            // Add description message conditionally if description is available
            if (isset($this->model->description) && !empty($this->model->description)) {
                $messages[] = new UserMessage("Here is additional context about the organization that might help you create a more relevant prompt: \"" . $this->model->description . "\".
Use this information to better understand what the organization does and create a prompt that would elicit responses mentioning organizations in this specific line of business. However, don't make the prompt too specific or narrow that it would only return this single organization.");
            }

            $messages[] = new UserMessage("Do not mention brand names or product names in the prompt.
Also, remember to keep prompts simple. Don't make assumptions about the intent behind the keyword.
Output your suggested prompt as plain text, without quotation marks, or any type of formatting.");

            $textResponse = Prism::text()
                ->using(Provider::OpenAI, 'gpt-4o')
                ->withMaxSteps(10)
                ->withMessages($messages)
                ->withTools([$searchApiTool])
                ->withToolChoice(ToolChoice::Auto)
                ->asText();

            $this->updateJobProgress(50, 'Storing new prompt for keyword "' . $this->keyword . '"');

            $prompt = Prompt::create([
                'team_id' => $this->teamId,
                'content' => $textResponse->text
            ]);

            // Mark the job as completed
            $this->markJobAsCompleted('Created new prompt for keyword "' . $this->keyword . '"');

            // Run the prompt
            $jobDispatcher->dispatch($prompt, new RunPromptJob($prompt, ['openai'], $prompt->team_id));
        } catch (Throwable $exception) {
            Log::error('Prompt generation job failed: ' . $exception->getMessage());
            $this->markJobAsFailed($exception);
            throw $exception;
        }
    }
}
