<?php

namespace App\Traits;

use App\Scopes\TeamScope;

trait BelongsToTeam
{
    protected static function bootBelongsToTeam(): void
    {
        static::addGlobalScope(new TeamScope());
    }
}
