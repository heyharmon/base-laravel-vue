<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasJobStatus;
use App\Models\Article;
use App\Models\OrganizationIndustry;

class Organization extends Model
{
	use HasFactory, HasJobStatus;

	protected $fillable = [
		'team_id',
		'campaign_id',
		'industry_id',
		'name',
		'website',
		'logo',
		'color',
		'description',
		'long_description',
		'location',
		'city',
		'state',
		'country',
		'founded',
		'employee_count',
		'is_competitor',
		'keywords',
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
	 * Get the campaign that owns the organization.
	 */
	public function campaign(): BelongsTo
	{
		return $this->belongsTo(Campaign::class);
	}

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
	 * Get the industry that owns the organization.
	 */
	public function industry(): BelongsTo
	{
		return $this->belongsTo(OrganizationIndustry::class, 'industry_id');
	}
}
