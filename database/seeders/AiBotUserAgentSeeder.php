<?php
namespace Database\Seeders;

use App\Models\UserAgent;
use Illuminate\Database\Seeder;

class AiBotUserAgentSeeder extends Seeder
{
    public function run(): void
    {
        $bots = [
            ['name' => 'GPTBot', 'user_agent_string' => 'GPTBot/1.0; +https://openai.com/gptbot'],
            ['name' => 'ClaudeBot', 'user_agent_string' => 'ClaudeBot/1.0; +claudebot@anthropic.com'],
            ['name' => 'BingBot', 'user_agent_string' => 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)'],
        ];
        foreach ($bots as $bot) {
            UserAgent::updateOrCreate(
                ['name' => $bot['name']],
                [
                    'user_agent_string' => $bot['user_agent_string'],
                    'user_agent_hash' => hash('sha256', $bot['user_agent_string']),
                    'type' => 'ai_bot',
                    'category' => 'good_bot',
                    'is_active' => true,
                ]
            );
        }
    }
}
