<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'provider',
        'model',
        'mentioned',
        'content',
        'metadata',
        'search',
        // 'error',
    ];

    protected $casts = [
        // 'tokens' => 'integer',
        // 'latency' => 'float',
        'metadata' => 'array',
        'search' => 'array',
    ];

    /**
     * The run that this response belongs to.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}