<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TeamScope implements Scope
{
	public function apply(Builder $builder, Model $model): void
	{
		$user = Auth::user();

		if (!$user || $user->isSuperAdmin()) {
			return;
		}

		$teamId = $user->current_team_id;

		if ($teamId) {
			$builder->where($model->getTable() . '.team_id', $teamId);
		}
	}
}
