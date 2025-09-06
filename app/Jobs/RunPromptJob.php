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

class RunPromptJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 960;

    public function __construct(private Response $response)
    {
        $this->onQueue($response->use_flex_processing ? 'flex' : 'standard');
    }

    public function handle(OpenAIService $openAIService): void
    {
        try {
            Log::info('Starting prompt request', [
                'response_id' => $this->response->id,
                'use_flex' => $this->response->use_flex_processing,
            ]);

            $promptContent = $this->response->prompt->content;
            $payload = $openAIService->buildPayload(
                $promptContent,
                $this->response->parameters ?? [],
                $this->response->use_flex_processing
            );

            $result = $openAIService->createCompletion($payload);

            if (isset($result['choices'][0]['message']['content'])) {
                ProcessCompletedResponseJob::dispatch($this->response, $result)->onQueue('high');
            } else {
                $requestId = $result['id'] ?? null;
                if ($requestId) {
                    $this->response->markAsProcessing($requestId);
                    PollOpenAIResponseJob::dispatch($this->response)->delay(now()->addSeconds(5))->onQueue('polling');
                } else {
                    throw new \Exception('No request ID received from OpenAI');
                }
            }
        } catch (OpenAIException $e) {
            $this->handleOpenAIException($e);
        } catch (\Exception $e) {
            $this->handleGenericException($e);
        }
    }

    private function handleOpenAIException(OpenAIException $e): void
    {
        Log::warning('OpenAI API error', [
            'response_id' => $this->response->id,
            'error_code' => $e->getErrorCode(),
            'error_message' => $e->getMessage(),
            'status_code' => $e->getStatusCode(),
        ]);

        if ($this->shouldRetry($e)) {
            $this->response->incrementRetry();
            $delay = $this->response->next_retry_at->diffInSeconds(now());
            RunPromptJob::dispatch($this->response)
                ->delay(now()->addSeconds($delay))
                ->onQueue('retries');
        } else {
            $this->response->markAsFailed($e->getErrorCode(), $e->getMessage());
        }
    }

    private function handleGenericException(\Exception $e): void
    {
        Log::error('Unexpected error in RunPromptJob', [
            'response_id' => $this->response->id,
            'error' => $e->getMessage(),
        ]);
        $this->response->markAsFailed('unexpected_error', $e->getMessage());
    }

    private function shouldRetry(OpenAIException $e): bool
    {
        $maxRetries = config('openai.retry.max_attempts', 10);
        if ($this->response->retry_count >= $maxRetries) {
            return false;
        }

        $retryableCodes = [
            'resource_unavailable',
            'rate_limit',
            'timeout',
            'service_unavailable',
            'network_error',
        ];

        return in_array($e->getErrorCode(), $retryableCodes);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('RunPromptJob failed completely', [
            'response_id' => $this->response->id,
            'exception' => $exception->getMessage(),
        ]);

        $this->response->markAsFailed('job_failed', 'The job failed unexpectedly: ' . $exception->getMessage());
    }
}
