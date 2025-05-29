<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\DB;

class Organization extends Model
{
    use HasFactory;

	protected $guarded = [
		'id'
	];

    protected $casts = [
        'is_competitor' => 'boolean',
    ];

    protected $appends = [
        // 'visibility',
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


	// TODO: do we need this if we already calculate visibility in the OrganizationVisibilityController?
    /**
     * Calculate the visibility percentage for this organization.
     * Visibility is defined as the total mentions divided by total responses.
     */
    // public function getVisibilityAttribute(): float
    // {
    //     // Get the team_id for this organization
    //     $teamId = $this->team_id;

    //     // Count total responses for this team
    //     $totalResponses = Response::whereHas('prompt', function ($query) use ($teamId) {
    //         $query->where('team_id', $teamId);
    //     })->count();

    //     if ($totalResponses === 0) {
    //         return 0;
    //     }

    //     // Count responses that contain at least one keyword from this organization
    //     $keywordIds = $this->keywords()->pluck('id')->toArray();

    //     $totalMentions = 0;
    //     if (!empty($keywordIds)) {
    //         $totalMentions = Response::whereHas('prompt', function ($query) use ($teamId) {
    //             $query->where('team_id', $teamId);
    //         })->whereHas('keywords', function ($query) use ($keywordIds) {
    //             $query->whereIn('keywords.id', $keywordIds);
    //         })->count();
    //     }

    //     return round(($totalMentions / $totalResponses) * 100, 2);
    // }
}
