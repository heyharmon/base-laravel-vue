<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApifyRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'apify_run_id',
        'status',
        'input_data',
        'items_processed',
        'items_imported',
        'items_updated',
        'items_skipped',
        'error_message',
        'started_at',
        'finished_at',
        'user_id',
    ];

    protected $casts = [
        'input_data' => 'array',
        'items_processed' => 'integer',
        'items_imported' => 'integer',
        'items_updated' => 'integer',
        'items_skipped' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return in_array($this->status, ['SUCCEEDED', 'FAILED', 'ABORTED']);
    }

    public function isSuccessful(): bool
    {
        return $this->status === 'SUCCEEDED';
    }

    public function getProgressPercentageAttribute(): int
    {
        if ($this->status === 'SUCCEEDED') return 100;
        if ($this->status === 'FAILED') return 0;
        $expectedItems = $this->input_data['maxPlaces'] ?? 100;
        return (int) min(90, ($this->items_processed / $expectedItems) * 100);
    }
}
