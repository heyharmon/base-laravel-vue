<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Keyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'organization_id',
        'name',
        'description',
    ];

    /**
     * The prompts that are associated with this keyword.
     */
    public function prompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class)
            ->withPivot('count', 'last_found_at')
            ->withTimestamps();
    }

    /**
     * The responses that contain this keyword.
     */
    public function responses(): BelongsToMany
    {
        return $this->belongsToMany(Response::class)
            ->withTimestamps();
    }
    
    /**
     * Get the team that owns the keyword.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
    
    /**
     * Get the organization that owns the keyword.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    
    /**
     * Get the mentions for this keyword.
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }
}