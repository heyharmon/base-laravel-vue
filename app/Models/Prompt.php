<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\HasJobStatus;
use App\Models\Organization;

class Prompt extends Model
{
    use HasFactory, HasJobStatus;

    protected $fillable = [
        'team_id',
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
     * The responses to this prompt.
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
     * Get the mentions for this prompt.
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
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
        
        // Get the organization that belongs to the team and is not a competitor
        $organization = Organization::where('team_id', $this->team_id)
            ->where('is_competitor', false)
            ->first();
            
        if (!$organization) {
            return 0;
        }
        
        // Get all keywords for this organization
        $keywordIds = $organization->keywords()->pluck('id')->toArray();
        
        if (empty($keywordIds)) {
            return 0;
        }
        
        // Count responses that contain at least one keyword from the team's organization
        $mentions = $this->responses()
            ->whereHas('keywords', function ($query) use ($keywordIds) {
                $query->whereIn('keywords.id', $keywordIds);
            })
            ->count();
        
        return round(($mentions / $totalResponses) * 100);
    }
}