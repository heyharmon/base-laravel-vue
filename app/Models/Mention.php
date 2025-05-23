<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Mention extends Model
{
    use HasFactory;

    protected $fillable = [
        'keyword_id',
        'response_id',
        'prompt_id',
        'organization_id',
        'team_id',
    ];

    /**
     * Get the keyword associated with this mention.
     */
    public function keyword(): BelongsTo
    {
        return $this->belongsTo(Keyword::class);
    }

    /**
     * Get the response associated with this mention.
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class);
    }

    /**
     * Get the prompt associated with this mention.
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }

    /**
     * Get the organization associated with this mention.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the team associated with this mention.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
