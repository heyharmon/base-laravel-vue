<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'content',
        'description',
        'is_active',
        'frequency',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * The keywords that are associated with this prompt.
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class)
            ->withPivot('count', 'last_found_at')
            ->withTimestamps();
    }

    /**
     * The runs of this prompt.
     */
    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }
}