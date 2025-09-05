<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\OpenAIPromptService;
use App\Models\Prompt;
use App\Jobs\PollOpenAIResponseJob;

class RunPromptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 300; // 5 minutes

    /**
     * The prompt instance.
     *
     * @var \App\Models\Prompt
     */
    protected $prompt;

    /**
     * Optional OpenAI service tier (e.g., 'flex').
     *
     * @var string|null
     */
    protected $serviceTier;

    /**
     * The team ID.
     *
     * @var int
     */
    protected $teamId;

    /**
     * The campaign ID.
     *
     * @var int
     */
    protected $campaignId;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Prompt  $prompt
     * @param  array  $providers
     * @param  int  $teamId
     * @param  int  $campaignId
     * @return void
     */
    public function __construct(Prompt $prompt, array $providers = [], int $teamId, int $campaignId, ?string $serviceTier = null)
    {
        $this->teamId = $teamId;
        $this->campaignId = $campaignId;
        $this->prompt = $prompt;
        $this->serviceTier = $serviceTier;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(OpenAIPromptService $openAI)
    {
        try {
            $team = \App\Models\Team::find($this->teamId);
            if ($team && ($remaining = $team->responsesRemaining()) !== null && $remaining <= 0) {
                return;
            }

            // Simplified: always use OpenAI gpt-4
            $providerName = 'openai';
            $model = 'gpt-4';

            // Get response from the LLM (single provider for reliability)
            $options = [];
            if ($this->serviceTier === 'flex') {
                $options['service_tier'] = 'flex';
            }
            $llm = $openAI->getResponse($this->prompt->content, $model, $options);

            // Always create a placeholder and rely on polling to finalize
            $response = $this->prompt->responses()->create([
                'provider' => $providerName,
                'model' => $model,
                'flex' => $this->serviceTier === 'flex',
                'status' => 'in_progress',
                'provider_id' => $llm->id ?? null,
                'content' => '',
                'usage' => null,
            ]);

            // Schedule a poll after 15 seconds to reduce costs
            $pollJob = new PollOpenAIResponseJob($response->id);
            $pollJob->delay(now()->addSeconds(15));
            dispatch($pollJob);
        } catch (Throwable $exception) {
            Log::error('RunPromptJob failed with exception', [
                'prompt_id' => $this->prompt->id,
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
                'job_id' => $this->job->getJobId() ?? 'unknown'
            ]);
            throw $exception;
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        Log::error('RunPromptJob definitively failed', [
            'prompt_id' => $this->prompt->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'job_id' => $this->job->getJobId() ?? 'unknown',
            'attempts' => $this->attempts(),
            'max_tries' => $this->tries
        ]);
    }
}
