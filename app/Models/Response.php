<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\TeamUsageEvent;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_id',
        'provider',
        'model',
        'flex',
        'provider_id',
        'status',
        'content',
        'search',
        'usage',
    ];

    protected $casts = [
        'search' => 'array',
        'usage' => 'array',
        'flex' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::created(function (Response $response) {
            $teamId = $response->prompt()->value('team_id');

            if (! $teamId) {
                return;
            }

            TeamUsageEvent::record($teamId, TeamUsageEvent::TYPE_RESPONSE, $response->id);
        });
    }

    /**
     * The prompt that this response belongs to.
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /**
     * The terms found in this response.
     */
    public function terms(): BelongsToMany
    {
        return $this->belongsToMany(Term::class, 'term_response')
            ->withTimestamps();
    }
}
