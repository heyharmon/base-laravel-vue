<?php

namespace App\Jobs;

use App\Models\Response;
use App\Services\OpenAIService;
use App\Exceptions\OpenAIException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PollOpenAIResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 60;

    public function __construct(private Response $response)
    {
        $this->onQueue('polling');
    }

    public function handle(OpenAIService $openAIService): void
    {
        try {
            if ($this->response->status === 'cancelled') {
                Log::info('Polling cancelled by user', ['response_id' => $this->response->id]);
                return;
            }

            $this->response->markAsPolling();

            Log::info('Polling for response', [
                'response_id' => $this->response->id,
                'poll_count' => $this->response->poll_count,
                'request_id' => $this->response->provider_id,
            ]);

            $apiResponse = $this->checkRequestStatus($openAIService);

            if ($this->isResponseComplete($apiResponse)) {
                ProcessCompletedResponseJob::dispatch($this->response, $apiResponse)->onQueue('high');
            } else {
                $this->scheduleNextPoll();
            }
        } catch (OpenAIException $e) {
            $this->handleOpenAIException($e);
        } catch (\Exception $e) {
            $this->handleGenericException($e);
        }
    }

    private function checkRequestStatus(OpenAIService $service): array
    {
        try {
            return $service->checkRequestStatus($this->response->provider_id);
        } catch (OpenAIException $e) {
            if ($e->getStatusCode() === 404) {
                $payload = $service->buildPayload(
                    $this->response->prompt->content,
                    $this->response->parameters ?? [],
                    $this->response->use_flex_processing
                );
                return $service->createCompletion($payload);
            }
            throw $e;
        }
    }

    private function isResponseComplete(array $response): bool
    {
        return isset($response['choices'][0]['message']['content']) || (isset($response['status']) && $response['status'] === 'completed');
    }

    private function scheduleNextPoll(): void
    {
        $delay = $this->calculatePollDelay();
        $maxPollingTime = config('openai.polling.max_time', 3600);
        $started = $this->response->started_at;
        if ($started && now()->diffInSeconds($started) > $maxPollingTime) {
            Log::warning('Max polling time exceeded', [
                'response_id' => $this->response->id,
                'polling_time' => now()->diffInSeconds($started),
            ]);
            $this->response->markAsFailed('polling_timeout', 'Response polling exceeded maximum time limit');
            return;
        }

        PollOpenAIResponseJob::dispatch($this->response)
            ->delay(now()->addSeconds($delay))
            ->onQueue('polling');
        Log::info('Scheduled next poll', ['response_id' => $this->response->id, 'delay_seconds' => $delay]);
    }

    private function calculatePollDelay(): int
    {
        $pollCount = $this->response->poll_count;
        $baseDelay = config('openai.polling.base_delay', 5);
        $maxDelay = config('openai.polling.max_delay', 60);

        if ($pollCount <= 5) {
            return $baseDelay;
        } elseif ($pollCount <= 10) {
            return 10;
        } elseif ($pollCount <= 20) {
            return 30;
        }
        return $maxDelay;
    }

    private function handleOpenAIException(OpenAIException $e): void
    {
        Log::warning('Error during polling', [
            'response_id' => $this->response->id,
            'error_code' => $e->getErrorCode(),
            'error_message' => $e->getMessage(),
        ]);

        if ($e->getErrorCode() === 'resource_unavailable') {
            $this->scheduleNextPoll();
            return;
        }

        $this->response->markAsFailed($e->getErrorCode(), $e->getMessage());
    }

    private function handleGenericException(\Exception $e): void
    {
        Log::error('Unexpected error in PollOpenAIResponseJob', [
            'response_id' => $this->response->id,
            'error' => $e->getMessage(),
        ]);
        $this->scheduleNextPoll();
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('PollOpenAIResponseJob failed', [
            'response_id' => $this->response->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->response->markAsFailed('polling_failed', 'Polling job failed: ' . $exception->getMessage());
    }
}
