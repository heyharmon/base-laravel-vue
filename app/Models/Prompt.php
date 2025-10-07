<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasJobStatus;
use App\Traits\BelongsToTeam;
use App\Models\Organization;
use App\Models\Campaign;

class Prompt extends Model
{
    use HasFactory, HasJobStatus, BelongsToTeam;

    protected $fillable = [
        'team_id',
        'campaign_id',
        'name',
        'content',
        'description',
        'is_active',
        'frequency',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'in_progress_responses_count',
        'in_progress_responses',
    ];

    /**
     * The terms that are associated with this prompt.
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_prompt')
            ->withPivot('count', 'last_found_at')
            ->withTimestamps();
    }

    /**
     * The responses to this prompt.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * The responses to this prompt that are currently in progress.
     */
    public function inProgressResponses(): HasMany
    {
        // Treat both 'in_progress' and 'queued' as active/in-flight
        return $this->hasMany(Response::class)
            ->whereIn('status', ['in_progress', 'queued']);
    }

    /**
     * Attribute: number of responses that are in progress for this prompt.
     */
    public function getInProgressResponsesCountAttribute(): int
    {
        // If eager counted via withCount('inProgressResponses'), use that value
        if (array_key_exists('in_progress_responses_count', $this->attributes)) {
            return (int) $this->attributes['in_progress_responses_count'];
        }

        return (int) $this->inProgressResponses()->count();
    }

    /**
     * Attribute: array of in-progress/queued responses for this prompt.
     */
    public function getInProgressResponsesAttribute()
    {
        // Use loaded relation if available to avoid extra query
        if ($this->relationLoaded('inProgressResponses')) {
            return $this->getRelation('inProgressResponses');
        }

        return $this->inProgressResponses()->get();
    }

    /**
     * Get the articles that are associated with this prompt.
     */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class)->latest();
    }

    /**
     * Get the campaign that owns the prompt.
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
