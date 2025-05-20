<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id', // TODO: Change this if adding projects model
        'name',
        'content',
        'description',
        'is_active',
        'frequency',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The keywords that are associated with this prompt.
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class)
            ->withPivot('count', 'last_found_at')
            ->withTimestamps();
    }

    /**
     * The responses of this prompt.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }
    
    /**
     * Get the team that owns the prompt.
     */
    // TODO: Change this if adding projects model
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}