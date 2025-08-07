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

    protected $appends = [];

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
