<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_id',
        'provider',
        'model',
        'flex',
        'provider_id',
        'status',
        'content',
        'search',
        'usage',
        'initial_attempts',
        'poll_attempts',
        'last_polled_at',
        'next_poll_at',
        'error_code',
        'processing_error_code',
        'processing_error_message',
    ];

    protected $casts = [
        'search' => 'array',
        'usage' => 'array',
        'flex' => 'boolean',
        'last_polled_at' => 'datetime',
        'next_poll_at' => 'datetime',
    ];

    /**
     * The prompt that this response belongs to.
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /**
     * The terms found in this response.
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_response')
            ->withTimestamps();
    }
}
