<?php
use App\Models\Website;
use App\Models\UserAgent;
use App\Jobs\CheckBotAccessibilityJob;
use Illuminate\Support\Facades\Http;

it('stores accessibility results', function () {
    Http::fake([
        'example.com/robots.txt' => Http::response("User-agent: *\nAllow: /", 200),
        'example.com' => Http::response('ok', 200)
    ]);

    $website = Website::create(['domain'=>'example.com','base_url'=>'https://example.com']);
    $agent = UserAgent::create([
        'name'=>'GPTBot',
        'user_agent_string'=>'GPTBot/1.0',
        'user_agent_hash'=>hash('sha256','GPTBot/1.0'),
        'type'=>'ai_bot',
        'category'=>'good_bot'
    ]);

    CheckBotAccessibilityJob::dispatchSync($website, [$agent->id]);

    expect($website->accessibilityResults()->count())->toBe(1);
});
