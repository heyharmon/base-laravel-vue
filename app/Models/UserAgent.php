<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserAgent extends Model
{
    protected $fillable = [
        'name', 'user_agent_string', 'user_agent_hash', 'type', 'category', 'is_active', 'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
    ];

    public function accessibilityResults(): HasMany
    {
        return $this->hasMany(BotAccessibilityResult::class);
    }

    public function scopeAiBots($query)
    {
        return $query->where('type', 'ai_bot');
    }
}
