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
use App\Models\Response as ResponseModel;
use App\Jobs\ResumeStalledResponseJob;

class PollOpenAIResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 2;

    /**
     * The number of seconds the job can run before timing out.
     * Keep this short since we only poll a single endpoint.
     *
     * @var int
     */
    public $timeout = 5;

    protected int $maxPollAttempts = 25;
    protected int $maxReissueAttempts = 5;
    protected int $baseDelay = 15;

    /**
     * The response ID (DB primary key) to poll.
     *
     * @var int
     */
    protected $responseDbId;

    public function __construct(int $responseDbId)
    {
        $this->responseDbId = $responseDbId;
    }

    public function handle(OpenAIPromptService $openAI)
    {
        try {
            $response = ResponseModel::with('prompt')->find($this->responseDbId);
            if (!$response) {
                return;
            }

            if (in_array($response->status, ['completed', 'failed', 'cancelled', 'processing_failed'])) {
                return;
            }

            $attempt = $response->poll_attempts;
            $response->update(['poll_attempts' => $attempt + 1, 'last_polled_at' => now()]);

            if (!$response->provider_id) {
                $this->reissueIfFailed($response, $openAI);
                return;
            }

            try {
                $fresh = $openAI->retrieveResponse($response->provider_id);
            } catch (Throwable $e) {
                $this->scheduleNext($response, $attempt, $e->getCode() ?: null);
                Log::warning('PollOpenAIResponseJob retrieve failed', ['response_id' => $response->id, 'error' => $e->getMessage()]);
                return;
            }

            $status = $fresh->status ?? 'in_progress';

            if ($status === 'completed') {
                $response->update([
                    'content' => $fresh->content ?? '',
                    'usage' => $fresh->usage ?? null,
                    'status' => 'completed',
                ]);
                dispatch(new ProcessCompletedResponseJob($response->id));
                Log::info('PollOpenAIResponseJob completed', ['response_id' => $response->id]);
                return;
            }

            if (in_array($status, ['failed', 'cancelled', 'incomplete'])) {
                $this->reissueIfFailed($response, $openAI);
                return;
            }

            $response->update(['status' => $status]);
            $this->scheduleNext($response, $attempt);
        } catch (Throwable $exception) {
            Log::error('PollOpenAIResponseJob failed with exception', [
                'response_db_id' => $this->responseDbId,
                'error' => $exception->getMessage(),
            ]);
            throw $exception;
        }
    }

    protected function scheduleNext(ResponseModel $response, int $attempt, ?string $errorCode = null): void
    {
        if ($attempt + 1 >= $this->maxPollAttempts) {
            $response->update(['status' => 'stalled', 'error_code' => $errorCode]);
            dispatch((new ResumeStalledResponseJob($response->id))->delay(now()->addHour()));
            return;
        }
        $delay = (int) min($this->baseDelay * (2 ** $attempt), 900);
        $delay = (int) ($delay * random_int(80, 120) / 100);
        $response->update(['next_poll_at' => now()->addSeconds($delay), 'error_code' => $errorCode]);
        $this->release($delay);
    }

    protected function reissueIfFailed(ResponseModel $response, OpenAIPromptService $openAI): void
    {
        $attempts = $response->initial_attempts ?? 0;
        if ($attempts >= $this->maxReissueAttempts) {
            $response->update(['status' => 'failed']);
            Log::error('Response failed after max retries', ['response_id' => $response->id]);
            return;
        }

        try {
            $prompt = $response->prompt;
            $options = [];
            if ($response->flex) {
                $options['service_tier'] = 'flex';
            }
            $fresh = $openAI->getResponse($prompt->content, $response->model, $options);
            $response->update([
                'provider_id' => $fresh->id ?? null,
                'status' => $fresh->status ?? 'in_progress',
                'content' => '',
                'usage' => null,
                'initial_attempts' => $attempts + 1,
                'poll_attempts' => 0,
            ]);
            $this->release($this->baseDelay);
        } catch (\Exception $e) {
            $response->update(['status' => 'failed', 'error_code' => $e->getCode() ?: null]);
            Log::error('Failed to reissue OpenAI response after failure', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return ['response:' . $this->responseDbId];
    }
}
