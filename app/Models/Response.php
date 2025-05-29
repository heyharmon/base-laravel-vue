<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'prompt_id',
        'provider',
        'model',
        'content',
        'metadata',
        'search',
    ];

    protected $casts = [
        'metadata' => 'array',
        'search' => 'array',
    ];

    /**
     * The prompt that this response belongs to.
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /**
     * The keywords found in this response.
     */
    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(Keyword::class)
            ->withTimestamps();
    }


}
