<?php

namespace App\Services;

class AgentDetector
{
    public static function isLLMAgent(?string $userAgent): bool
    {
        if (!$userAgent || !is_string($userAgent)) {
            return false;
        }

        $patterns = [
            // OpenAI
            '/gptbot/i', '/chatgpt-user/i', '/oai-searchbot/i',
            // Anthropic
            '/anthropic-ai/i', '/claudebot/i', '/claude-web/i', '/claude-user/i',
            // Google AI
            '/google-extended/i', '/googleother/i', '/googleagent-mariner/i',
            // Other Major Providers
            '/perplexitybot/i', '/perplexity-user/i', '/mistralai-user/i',
            '/meta-externalagent/i', '/applebot-extended/i', '/amazonbot/i',
            // Generic patterns
            '/\bai\s*bot\b/i', '/\bai\s*crawler/i', '/language\s*model/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return true;
            }
        }

        return false;
    }
}
