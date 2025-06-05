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
use App\Models\Article;

class Organization extends Model
{
	use HasFactory, HasJobStatus;

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
