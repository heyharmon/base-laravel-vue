<?php

namespace App\Jobs;

use App\Models\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Events\PromptResponseCompleted;
use Illuminate\Support\Facades\Schema;

class ProcessCompletedResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 30;

    public function __construct(private Response $response, private array $openAIResponse)
    {
        $this->onQueue('high');
    }

    public function handle(): void
    {
        try {
            Log::info('Processing completed response', ['response_id' => $this->response->id]);

            $content = $this->extractContent();
            $metadata = $this->extractMetadata();

            $this->response->markAsCompleted($content, $metadata);

            event(new PromptResponseCompleted($this->response));

            $this->performPostProcessing();

            Log::info('Successfully processed response', [
                'response_id' => $this->response->id,
                'processing_time' => $this->response->processing_time_seconds,
                'tokens_used' => $this->response->tokens_used,
                'cost' => $this->response->cost,
            ]);
        } catch (\Exception $e) {
            $this->handleProcessingError($e);
        }
    }

    private function extractContent(): string
    {
        if (isset($this->openAIResponse['choices'][0]['message']['content'])) {
            return $this->openAIResponse['choices'][0]['message']['content'];
        }
        if (isset($this->openAIResponse['output_text'])) {
            return $this->openAIResponse['output_text'];
        }
        if (isset($this->openAIResponse['text'])) {
            return $this->openAIResponse['text'];
        }
        throw new \Exception('Unable to extract content from OpenAI response');
    }

    private function extractMetadata(): array
    {
        $metadata = [
            'model' => $this->openAIResponse['model'] ?? null,
            'id' => $this->openAIResponse['id'] ?? null,
            'created' => $this->openAIResponse['created'] ?? null,
            'usage' => $this->openAIResponse['usage'] ?? null,
            'finish_reason' => $this->openAIResponse['choices'][0]['finish_reason'] ?? null,
            'service_tier' => $this->openAIResponse['service_tier'] ?? null,
        ];
        return array_filter($metadata, fn($v) => $v !== null);
    }

    private function performPostProcessing(): void
    {
        $this->checkContentModeration();
        $this->storeInVectorDatabase();
        $this->updateUserStatistics();
        $this->cacheIfNeeded();
    }

    private function checkContentModeration(): void
    {
        // placeholder
    }

    private function storeInVectorDatabase(): void
    {
        // placeholder for embeddings
    }

    private function updateUserStatistics(): void
    {
        $user = $this->response->prompt->team->owner ?? null;
        if (!$user) {
            return;
        }

        if (Schema::hasColumn('users', 'total_prompts')) {
            $user->increment('total_prompts');
        }

        if (Schema::hasColumn('users', 'total_tokens')) {
            $user->increment('total_tokens', $this->response->tokens_used ?? 0);
        }

        if (Schema::hasColumn('users', 'total_cost')) {
            $user->increment('total_cost', $this->response->cost ?? 0);
        }
    }

    private function cacheIfNeeded(): void
    {
        if ($this->shouldCache()) {
            $cacheKey = 'prompt:' . md5($this->response->prompt->content);
            cache()->put($cacheKey, $this->response->content, now()->addHours(24));
        }
    }

    private function shouldCache(): bool
    {
        return strlen($this->response->prompt->content) < 500 && !$this->response->use_flex_processing;
    }

    private function handleProcessingError(\Exception $e): void
    {
        Log::error('Error processing completed response', [
            'response_id' => $this->response->id,
            'error' => $e->getMessage(),
        ]);

        if ($this->response->content) {
            Log::warning('Response saved despite processing error', ['response_id' => $this->response->id]);
            return;
        }

        $this->response->markAsFailed('processing_error', 'Failed to process completed response: ' . $e->getMessage());
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessCompletedResponseJob failed', [
            'response_id' => $this->response->id,
            'exception' => $exception->getMessage(),
        ]);

        if (isset($this->openAIResponse['choices'][0]['message']['content'])) {
            $this->response->update([
                'content' => $this->openAIResponse['choices'][0]['message']['content'],
                'status' => 'completed',
                'error_message' => 'Post-processing failed but response saved',
            ]);
        } else {
            $this->response->markAsFailed('processing_job_failed', 'Processing job failed: ' . $exception->getMessage());
        }
    }
}
