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
            ['name' => 'AI2Bot', 'user_agent_string' => 'Mozilla/5.0 (compatible; AI2Bot/1.0; +http://www.allenai.org/crawler)'],
            ['name' => 'Amazonbot', 'user_agent_string' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/600.2.5 (KHTML, like Gecko) Version/8.0.2 Safari/600.2.5 (Amazonbot/0.1; +https://developer.amazon.com/support/amazonbot)'],
            ['name' => 'Anthropic AI Bot', 'user_agent_string' => 'Mozilla/5.0 (compatible; anthropic-ai/1.0; +http://www.anthropic.com/bot.html)'],
            ['name' => 'Claude Web', 'user_agent_string' => 'Mozilla/5.0 (compatible; claude-web/1.0; +http://www.anthropic.com/bot.html)'],
            ['name' => 'ClaudeBot Extended', 'user_agent_string' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; ClaudeBot/1.0; +claudebot@anthropic.com)'],
            ['name' => 'Applebot-Extended', 'user_agent_string' => 'Mozilla/5.0 (compatible; Applebot-Extended/1.0; +http://www.apple.com/bot.html)'],
            ['name' => 'Applebot', 'user_agent_string' => 'Mozilla/5.0 (compatible; Applebot/1.0; +http://www.apple.com/bot.html)'],
            ['name' => 'BingBot Extended', 'user_agent_string' => 'Mozilla/5.0 (compatible; BingBot/1.0; +http://www.bing.com/bot.html)'],
            ['name' => 'Bytespider', 'user_agent_string' => 'Mozilla/5.0 (compatible; Bytespider/1.0; +http://www.bytedance.com/bot.html)'],
            ['name' => 'GPTBot Extended', 'user_agent_string' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; GPTBot/1.1; +https://openai.com/gptbot'],
            ['name' => 'ChatGPT-User', 'user_agent_string' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; ChatGPT-User/1.0; +https://openai.com/bot'],
            ['name' => 'OAI-SearchBot', 'user_agent_string' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko); compatible; OAI-SearchBot/1.0; +https://openai.com/searchbot'],
            ['name' => 'CCBot', 'user_agent_string' => 'Mozilla/5.0 (compatible; CCBot/1.0; +http://www.commoncrawl.org/bot.html)'],
            ['name' => 'DuckAssistBot', 'user_agent_string' => 'Mozilla/5.0 (compatible; DuckAssistBot/1.0; +http://www.duckduckgo.com/bot.html)'],
            ['name' => 'Google-Extended', 'user_agent_string' => 'Mozilla/5.0 (compatible; Google-Extended/1.0; +http://www.google.com/bot.html)'],
            ['name' => 'LinkedInBot', 'user_agent_string' => 'LinkedInBot/1.0 (compatible; Mozilla/5.0; Jakarta Commons-HttpClient/3.1 +http://www.linkedin.com)'],
            ['name' => 'Meta External Fetcher', 'user_agent_string' => 'Mozilla/5.0 (compatible; meta-externalagent/1.1 (+https://developers.facebook.com/docs/sharing/webmasters/crawler))'],
            ['name' => 'FacebookBot', 'user_agent_string' => 'Mozilla/5.0 (compatible; FacebookBot/1.0; +http://www.facebook.com/bot.html)'],
            ['name' => 'Omgili Bot', 'user_agent_string' => 'Mozilla/5.0 (compatible; omgili/1.0; +http://www.omgili.com/bot.html)'],
            ['name' => 'PerplexityBot', 'user_agent_string' => 'Mozilla/5.0 AppleWebKit/537.36 (KHTML, like Gecko; compatible; PerplexityBot/1.0; +https://perplexity.ai/perplexitybot)'],
            ['name' => 'YouBot', 'user_agent_string' => 'Mozilla/5.0 (compatible; YouBot (+http://www.you.com))'],
            ['name' => 'Cohere AI', 'user_agent_string' => 'Mozilla/5.0 (compatible; cohere-ai/1.0; +http://www.cohere.ai/bot.html)'],
            ['name' => 'Timpi', 'user_agent_string' => 'Timpibot/0.8 (+http://www.timpi.io)'],
            ['name' => 'DiffBot', 'user_agent_string' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 (.NET CLR 3.5.30729; Diffbot/0.1; +http://www.diffbot.com)'],
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
