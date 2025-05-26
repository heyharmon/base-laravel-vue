<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'website',
        'founded',
        'employee_count',
        'location',
        'is_competitor',
    ];

    protected $casts = [
        'is_competitor' => 'boolean',
    ];
    
    protected $appends = [
        'visibility',
    ];

    /**
     * Get the team that owns the organization.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the keywords that belong to the organization.
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }
    
    /**
     * Get the mentions that belong to the organization.
     */
    public function mentions(): HasMany
    {
        return $this->hasMany(Mention::class);
    }
    
    /**
     * Calculate the visibility percentage for this organization.
     * Visibility is defined as the total mentions divided by total responses.
     */
    public function getVisibilityAttribute(): float
    {
        // Get the team_id for this organization
        $teamId = $this->team_id;
        
        // Count total responses for this team
        $totalResponses = Response::whereHas('prompt', function ($query) use ($teamId) {
            $query->where('team_id', $teamId);
        })->count();
        
        if ($totalResponses === 0) {
            return 0;
        }
        
        // Count total mentions for this organization
        $totalMentions = $this->mentions()->count();
        
        return round(($totalMentions / $totalResponses) * 100, 2);
    }
}
