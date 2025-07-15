<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Scopes\TeamScope;
use App\Models\Team;

trait BelongsToTeam
{
	protected static function bootBelongsToTeam(): void
	{
		static::addGlobalScope(new TeamScope());
	}

	public function team(): BelongsTo
	{
		return $this->belongsTo(Team::class);
	}
}
