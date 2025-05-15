<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Run extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_id',
        'status',
        'run_date',
    ];

    protected $casts = [
        'run_date' => 'datetime',
    ];

    /**
     * The prompt that this run belongs to.
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /**
     * The responses for this run.
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * The keywords found in this run.
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class)
            ->withTimestamps();
    }
}