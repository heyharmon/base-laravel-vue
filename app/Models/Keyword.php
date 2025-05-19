<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Keyword extends Model
{
    use HasFactory;

    protected $fillable = [
        'team_id', // TODO: Change this if adding projects model
        'name',
        'description',
    ];

    /**
     * The prompts that are associated with this keyword.
     */
    public function prompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class)
            ->withPivot('count', 'last_found_at')
            ->withTimestamps();
    }

    /**
     * The runs that found this keyword.
     */
    public function runs(): BelongsToMany
    {
        return $this->belongsToMany(Run::class)
            ->withTimestamps();
    }
    
    /**
     * Get the team that owns the keyword.
     */
    // TODO: Change this if adding projects model
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}