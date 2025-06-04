<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasJobStatus;

class Organization extends Model
{
	use HasFactory, HasJobStatus;

	protected $guarded = [
		'id'
	];

	protected $casts = [
		'is_competitor' => 'boolean',
		'terms' => 'array',
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
	 * Get the terms that belong to the organization.
	 */
	public function terms(): HasMany
	{
		return $this->hasMany(Term::class);
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

	//     // Count responses that contain at least one term from this organization
	//     $termIds = $this->terms()->pluck('id')->toArray();

	//     $totalMentions = 0;
	//     if (!empty($termIds)) {
	//         $totalMentions = Response::whereHas('prompt', function ($query) use ($teamId) {
	//             $query->where('team_id', $teamId);
	//         })->whereHas('terms', function ($query) use ($termIds) {
	//             $query->whereIn('terms.id', $termIds);
	//         })->count();
	//     }

	//     return round(($totalMentions / $totalResponses) * 100, 2);
	// }
}
