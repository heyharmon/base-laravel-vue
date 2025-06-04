<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use App\Traits\HasJobStatus;

class Term extends Model
{
    use HasFactory, HasJobStatus;

    protected $fillable = [
        'team_id',
        'organization_id',
        'name',
        'description',
        'is_recommended',
    ];
    
    protected $casts = [
        'is_recommended' => 'boolean',
    ];
    
    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        static::addGlobalScope('without-recommended', function (Builder $builder) {
            $builder->where('is_recommended', false);
        });
    }

    /**
     * The prompts that are associated with this term.
     */
    public function prompts(): BelongsToMany
    {
        return $this->belongsToMany(Prompt::class, 'term_prompt')
            ->withPivot('count', 'last_found_at')
            ->withTimestamps();
    }

    /**
     * The responses that contain this term.
     */
    public function responses(): BelongsToMany
    {
        return $this->belongsToMany(Response::class, 'term_response')
            ->withTimestamps();
    }

    /**
     * Get the team that owns the term.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the organization that owns the term.
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }


    
    /**
     * Scope a query to include recommended terms.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithRecommended($query)
    {
        return $query->withoutGlobalScope('without-recommended');
    }
}
