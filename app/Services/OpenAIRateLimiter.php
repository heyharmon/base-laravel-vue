<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class OpenAIRateLimiter
{
    private const CACHE_KEY = 'openai_rate_limit';

    /**
     * Wait until the OpenAI rate limit allows a new request.
     */
    public static function awaitAvailability(): void
    {
        $data = Cache::get(self::CACHE_KEY);
        if (!$data) {
            return;
        }

        $wait = 0;
        if (($data['remaining_requests'] ?? 1) < 1) {
            $wait = max($wait, ($data['reset_requests'] ?? time()) - time());
        }
        if (($data['remaining_tokens'] ?? 1) < 1) {
            $wait = max($wait, ($data['reset_tokens'] ?? time()) - time());
        }

        if ($wait > 0) {
            sleep($wait);
        }
    }

    /**
     * Update cached rate limit information from response headers.
     */
    public static function update(array $headers): void
    {
        $data = [
            'remaining_requests' => self::headerValue($headers, 'x-ratelimit-remaining-requests'),
            'reset_requests' => time() + self::headerValue($headers, 'x-ratelimit-reset-requests'),
            'remaining_tokens' => self::headerValue($headers, 'x-ratelimit-remaining-tokens'),
            'reset_tokens' => time() + self::headerValue($headers, 'x-ratelimit-reset-tokens'),
        ];

        Cache::put(self::CACHE_KEY, $data, now()->addMinutes(1));
    }

    /**
     * Helper to extract an integer header value.
     */
    private static function headerValue(array $headers, string $key): int
    {
        $lower = strtolower($key);
        $upper = strtoupper($key);
        $value = $headers[$key][0] ?? $headers[$lower][0] ?? $headers[$upper][0] ?? null;
        return $value !== null ? (int) $value : 0;
    }
}
