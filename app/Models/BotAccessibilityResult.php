<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotAccessibilityResult extends Model
{
    protected $fillable = [
        'website_id',
        'user_agent_id',
        'url',
        'url_hash',
        'robots_txt_allowed',
        'robots_txt_status_code',
        'robots_txt_content',
        'robots_txt_rules',
        'http_accessible',
        'http_status_code',
        'response_time_ms',
        'response_headers',
        'firewall_detected',
        'blocking_method',
        'waf_type',
        'detection_confidence',
        'error_message',
        'checked_at'
    ];

    protected $casts = [
        'robots_txt_allowed' => 'boolean',
        'http_accessible' => 'boolean',
        'firewall_detected' => 'boolean',
        'robots_txt_rules' => 'array',
        'checked_at' => 'datetime',
    ];

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }

    public function userAgent(): BelongsTo
    {
        return $this->belongsTo(UserAgent::class);
    }
}
