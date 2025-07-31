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
		'mentions_percentage',
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

		// Get all terms for this organization
		$termIds = $organization->terms()->pluck('id')->toArray();

		if (empty($termIds)) {
			return 0;
		}

		// Count responses that contain at least one term from the team's organization
		$mentions = $this->responses()
			->whereHas('terms', function ($query) use ($termIds) {
				$query->whereIn('terms.id', $termIds);
			})
			->count();

		return round(($mentions / $totalResponses) * 100);
	}
}
