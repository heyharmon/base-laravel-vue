<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id',
        'name',
        'website',
        'founded',
        'employee_count',
        'is_competitor',
    ];

    protected $casts = [
        'is_competitor' => 'boolean',
    ];

    /**
     * Get the team that owns the organization.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the keywords that belong to the organization.
     */
    public function keywords(): HasMany
    {
        return $this->hasMany(Keyword::class);
    }
}
