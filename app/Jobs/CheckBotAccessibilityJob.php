<?php
namespace App\Jobs;

use App\Models\Website;
use App\Models\UserAgent;
use App\Services\BotAccessibility\BotAccessibilityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CheckBotAccessibilityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Website $website, public array $userAgentIds = [])
    {
        $this->onQueue('bot-checking');
    }

    public function handle(BotAccessibilityService $service): void
    {
        $query = UserAgent::query();
        if (!empty($this->userAgentIds)) {
            $query->whereIn('id', $this->userAgentIds);
        } else {
            $query->where('is_active', true)->aiBots();
        }
        $agents = $query->get();
        foreach ($agents as $agent) {
            $service->checkBotAccess($this->website, $agent);
        }
        $this->website->update(['last_checked_at' => now()]);
    }
}
