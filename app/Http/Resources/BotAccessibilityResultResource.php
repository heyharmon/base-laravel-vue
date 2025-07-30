<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BotAccessibilityResultResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_agent' => $this->userAgent->name ?? null,
            'robots_txt_allowed' => $this->robots_txt_allowed,
            'http_accessible' => $this->http_accessible,
            'firewall_detected' => $this->firewall_detected,
            'checked_at' => $this->checked_at,
            'waf_type' => $this->waf_type,
            'blocking_method' => $this->blocking_method,
        ];
    }
}
