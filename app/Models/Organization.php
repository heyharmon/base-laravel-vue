<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasJobStatus;
use App\Traits\BelongsToTeam;
use App\Models\Article;
use App\Models\Campaign;

class Organization extends Model
{
	use HasFactory, HasJobStatus, BelongsToTeam;

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
	 * Get the terms that belong to the organization.
	 */
	public function terms(): HasMany
	{
		return $this->hasMany(Term::class);
	}

	/**
	 * Get the articles that belong to the organization.
	 */
        public function articles(): HasMany
        {
                return $this->hasMany(Article::class);
        }

        /**
         * Get the campaign that owns the organization (for competitors only).
         */
        public function campaign(): BelongsTo
        {
                return $this->belongsTo(Campaign::class);
        }

        /**
         * Scope a query to only include organizations for a specific campaign.
         */
        public function scopeForCampaign($query, $campaignId)
        {
                return $query->where(function ($q) use ($campaignId) {
                        $q->where('campaign_id', $campaignId)
                          ->orWhere('is_competitor', false);
                });
        }
}
