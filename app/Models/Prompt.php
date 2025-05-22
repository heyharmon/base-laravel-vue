<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasJobStatus;

class Prompt extends Model
{
    use HasFactory, HasJobStatus;

    protected $fillable = [
        'team_id',
        'organization_id',
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
        'mentions_percentage',
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
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
    
    /**
     * Get the organization that owns the prompt.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
    
    /**
     * Get the mentions percentage for this prompt.
     */
    public function getMentionsPercentageAttribute(): int
    {
        $totalResponses = $this->responses()->count();
        
        if ($totalResponses === 0) {
            return 0;
        }
        
        $mentionedResponses = $this->responses()->where('mentioned', true)->count();
        
        return round(($mentionedResponses / $totalResponses) * 100);
    }
}