<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Website extends Model
{
    protected $fillable = [
        'domain', 'protocol', 'base_url', 'status', 'crawl_settings', 'last_checked_at'
    ];

    protected $casts = [
        'crawl_settings' => 'array',
        'last_checked_at' => 'datetime',
    ];

    public function accessibilityResults(): HasMany
    {
        return $this->hasMany(BotAccessibilityResult::class);
    }
}
