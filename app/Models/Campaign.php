<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTeam;
use App\Traits\HasJobStatus;

class Campaign extends Model
{
	use HasFactory, BelongsToTeam, HasJobStatus;

	protected $fillable = [
		'team_id',
		'name',
		'description',
		'location',
		'keywords',
		'is_default',
	];

	protected $casts = [
		'is_default' => 'boolean',
		'keywords' => 'array',
	];

	/**
	 * Get the competitors (organizations) that belong to this campaign.
	 */
	public function competitors(): HasMany
	{
		return $this->hasMany(Organization::class)->where('is_competitor', true);
	}

	/**
	 * Get the prompts that belong to this campaign.
	 */
	public function prompts(): HasMany
	{
		return $this->hasMany(Prompt::class);
	}

	/**
	 * Get the articles that belong to this campaign.
	 */
	public function articles(): HasMany
	{
		return $this->hasMany(Article::class);
	}

	/**
	 * Scope a query to only include default campaigns.
	 */
	public function scopeDefault($query)
	{
		return $query->where('is_default', true);
	}
}
