<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorizationJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'user_id',
        'type',
        'total_transactions',
        'processed_transactions',
        'successful_transactions',
        'failed_transactions',
        'status',
        'failed_transaction_ids',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'failed_transaction_ids' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->total_transactions === 0) {
            return 0;
        }

        return round(($this->processed_transactions / $this->total_transactions) * 100, 2);
    }

    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now(),
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    public function incrementProgress(bool $successful = true): void
    {
        $this->increment('processed_transactions');

        if ($successful) {
            $this->increment('successful_transactions');
        } else {
            $this->increment('failed_transactions');
        }

        if ($this->processed_transactions >= $this->total_transactions) {
            $this->markAsCompleted();
        }
    }
}
