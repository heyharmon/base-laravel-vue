<?php
namespace App\Services\BotAccessibility;

class FirewallDetector
{
    private array $signatures = [
        'cloudflare' => ['cf-ray','cloudflare'],
        'sucuri' => ['x-sucuri-id','sucuri'],
        'incapsula' => ['x-iinfo','incap_ses'],
    ];

    public function analyzeResponse(int $statusCode, array $headers, string $content): array
    {
        $blocked = false;
        $method = null;
        $type = null;
        $confidence = 0;

        if (in_array($statusCode, [403,429,503])) {
            $blocked = true;
            $method = 'status_code';
            $confidence = 0.8;
        }

        foreach ($this->signatures as $name => $checks) {
            foreach ($checks as $check) {
                foreach ($headers as $h => $v) {
                    if (str_contains(strtolower($h), strtolower($check))) {
                        $blocked = true;
                        $method = 'waf_detected';
                        $type = $name;
                        $confidence = 0.9;
                        break 3;
                    }
                }
                if (str_contains(strtolower($content), strtolower($check))) {
                    $blocked = true;
                    $method = 'content';
                    $type = $name;
                    $confidence = max($confidence,0.8);
                }
            }
        }

        return [
            'blocked' => $blocked,
            'method' => $method,
            'waf_type' => $type,
            'confidence' => $confidence,
        ];
    }
}
