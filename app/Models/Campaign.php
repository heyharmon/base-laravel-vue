<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
	use HasFactory;

	protected $fillable = [
		'team_id',
		'name',
		'description',
		'is_default',
	];

	protected $casts = [
		'is_default' => 'boolean',
	];

	/**
	 * Get the team that owns the campaign.
	 */
	public function team(): BelongsTo
	{
		return $this->belongsTo(Team::class);
	}

	/**
	 * Get the prompts that belong to the campaign.
	 */
	public function prompts(): HasMany
	{
		return $this->hasMany(Prompt::class);
	}

	/**
	 * Get the organizations that belong to the campaign.
	 */
	public function organizations(): HasMany
	{
		return $this->hasMany(Organization::class);
	}

	/**
	 * Get the articles that belong to the campaign.
	 */
	public function articles(): HasMany
	{
		return $this->hasMany(Article::class);
	}
}
