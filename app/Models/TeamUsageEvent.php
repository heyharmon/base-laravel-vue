<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamUsageEvent extends Model
{
    use HasFactory;

    public const TYPE_RESPONSE = 'response';
    public const TYPE_ARTICLE = 'article';

    protected $fillable = [
        'team_id',
        'resource_type',
        'resource_id',
        'quantity',
    ];

    /**
     * Record a usage event for the given team.
     */
    public static function record(int $teamId, string $resourceType, ?int $resourceId = null, int $quantity = 1): void
    {
        if ($quantity === 0) {
            return;
        }

        static::create([
            'team_id' => $teamId,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'quantity' => $quantity,
        ]);
    }
}
