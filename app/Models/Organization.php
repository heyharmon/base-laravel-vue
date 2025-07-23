<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasJobStatus;
use App\Traits\BelongsToTeam;
use App\Traits\BelongsToCampaign;
use App\Models\Article;

class Organization extends Model
{
        use HasFactory, HasJobStatus, BelongsToTeam, BelongsToCampaign;

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
}
