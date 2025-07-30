<?php
namespace App\Services\BotAccessibility;

class RobotsTxtParser
{
    public function parseRules(string $robotsTxt): array
    {
        $lines = preg_split('/\r?\n/', $robotsTxt);
        $rules = [];
        $currentAgent = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m)) {
                $currentAgent = strtolower(trim($m[1]));
                $rules[$currentAgent] = $rules[$currentAgent] ?? ['allow'=>[], 'disallow'=>[]];
            } elseif (preg_match('/^(allow|disallow):\s*(.*)$/i', $line, $m)) {
                if ($currentAgent) {
                    $rules[$currentAgent][strtolower($m[1])][] = trim($m[2]);
                }
            }
        }
        return $rules;
    }

    public function isPathAllowed(string $robotsTxt, string $userAgent, string $path): bool
    {
        $rules = $this->parseRules($robotsTxt);
        $userAgent = strtolower($userAgent);
        $applicable = $rules[$userAgent] ?? $rules['*'] ?? null;
        if (!$applicable) {
            return true;
        }
        foreach ($applicable['disallow'] as $pattern) {
            if ($pattern !== '' && str_starts_with($path, $pattern)) {
                foreach ($applicable['allow'] as $allow) {
                    if (str_starts_with($path, $allow) && strlen($allow) >= strlen($pattern)) {
                        return true;
                    }
                }
                return false;
            }
        }
        return true;
    }
}
