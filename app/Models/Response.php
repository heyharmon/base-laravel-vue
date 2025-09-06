<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_id',
        'provider',
        'model',
        'use_flex_processing',
        'provider_id',
        'parameters',
        'status',
        'content',
        'response_metadata',
        'error_code',
        'error_message',
        'retry_count',
        'poll_count',
        'last_poll_at',
        'next_retry_at',
        'started_at',
        'completed_at',
        'processing_time_seconds',
        'tokens_used',
        'cost',
        'search',
    ];

    protected $casts = [
        'parameters' => 'array',
        'response_metadata' => 'array',
        'use_flex_processing' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_poll_at' => 'datetime',
        'next_retry_at' => 'datetime',
        'search' => 'array',
    ];

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_response')->withTimestamps();
    }

    public function markAsProcessing(string $requestId): void
    {
        $this->update([
            'status' => 'processing',
            'provider_id' => $requestId,
            'started_at' => now(),
        ]);
    }

    public function markAsPolling(): void
    {
        $this->update([
            'status' => 'polling',
            'poll_count' => $this->poll_count + 1,
            'last_poll_at' => now(),
        ]);
    }

    public function markAsCompleted(string $content, array $metadata = []): void
    {
        $processingTime = $this->started_at ? now()->diffInSeconds($this->started_at) : null;

        $this->update([
            'status' => 'completed',
            'content' => $content,
            'response_metadata' => $metadata,
            'completed_at' => now(),
            'processing_time_seconds' => $processingTime,
            'tokens_used' => $metadata['usage']['total_tokens'] ?? null,
            'cost' => $this->calculateCost($metadata),
        ]);
    }

    public function markAsFailed(string $code, string $message): void
    {
        $this->update([
            'status' => 'failed',
            'error_code' => $code,
            'error_message' => $message,
            'completed_at' => now(),
        ]);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
        $this->update([
            'next_retry_at' => $this->calculateNextRetryTime(),
        ]);
    }

    private function calculateNextRetryTime(): \Carbon\Carbon
    {
        $baseDelay = config('openai.retry.base_delay', 2);
        $maxDelay = config('openai.retry.max_delay', 300);

        $delay = min(
            $baseDelay * pow(2, $this->retry_count) + rand(0, 1000) / 1000,
            $maxDelay
        );

        return now()->addSeconds($delay);
    }

    private function calculateCost(array $metadata): float
    {
        if (!isset($metadata['usage'])) {
            return 0;
        }

        $inputTokens = $metadata['usage']['prompt_tokens'] ?? 0;
        $outputTokens = $metadata['usage']['completion_tokens'] ?? 0;

        if ($this->use_flex_processing) {
            $inputCost = $inputTokens * 0.000005;
            $outputCost = $outputTokens * 0.00002;
        } else {
            $inputCost = $inputTokens * 0.00001;
            $outputCost = $outputTokens * 0.00004;
        }

        return round($inputCost + $outputCost, 6);
    }
}
