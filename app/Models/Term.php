<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasJobStatus;

class Term extends Model
{
	use HasFactory, HasJobStatus;

	protected $fillable = [
		'team_id',
		'organization_id',
		'name',
	];

	/**
	 * The prompts that are associated with this term.
	 */
	public function prompts(): BelongsToMany
	{
		return $this->belongsToMany(Prompt::class, 'term_prompt')
			->withPivot('count', 'last_found_at')
			->withTimestamps();
	}

	/**
	 * The responses that contain this term.
	 */
	public function responses(): BelongsToMany
	{
		return $this->belongsToMany(Response::class, 'term_response')
			->withTimestamps();
	}

	/**
	 * Get the team that owns the term.
	 */
	public function team(): BelongsTo
	{
		return $this->belongsTo(Team::class);
	}

	/**
	 * Get the organization that owns the term.
	 */
	public function organization(): BelongsTo
	{
		return $this->belongsTo(Organization::class);
	}
}
