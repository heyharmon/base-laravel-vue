<?php
namespace App\Services\BotAccessibility;

use App\Models\{Website,UserAgent,BotAccessibilityResult};
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BotAccessibilityService
{
    public function __construct(
        private RobotsTxtParser $parser,
        private FirewallDetector $firewall
    ) {}

    public function checkBotAccess(Website $website, UserAgent $agent): array
    {
        $results = [
            'robots_txt' => $this->checkRobots($website, $agent),
        ];
        $http = $this->testHttp($website, $agent);
        $results['http_access'] = $http;
        $results['firewall'] = $http['firewall'];

        $this->store($website, $agent, $results);
        return $results;
    }

    private function checkRobots(Website $website, UserAgent $agent): array
    {
        $url = rtrim($website->base_url,'/') . '/robots.txt';
        try {
            $response = Http::timeout(15)->get($url);
            $body = $response->body();
            $allowed = $this->parser->isPathAllowed($body, $agent->user_agent_string, '/');
            return [
                'allowed' => $allowed,
                'status_code' => $response->status(),
                'content' => $body,
                'parsed_rules' => $this->parser->parseRules($body),
            ];
        } catch (\Exception $e) {
            return [ 'allowed' => true, 'status_code'=>null, 'error'=>$e->getMessage() ];
        }
    }

    private function testHttp(Website $website, UserAgent $agent): array
    {
        try {
            $start = microtime(true);
            $response = Http::withHeaders([
                'User-Agent' => $agent->user_agent_string,
            ])->timeout(30)->get($website->base_url);
            $time = (int)((microtime(true)-$start)*1000);
            $firewall = $this->firewall->analyzeResponse($response->status(), $response->headers(), $response->body());
            return [
                'accessible' => !$firewall['blocked'],
                'status_code' => $response->status(),
                'response_time' => $time,
                'headers' => $response->headers(),
                'firewall' => $firewall,
            ];
        } catch (\Exception $e) {
            return [
                'accessible' => false,
                'error' => $e->getMessage(),
                'firewall' => ['blocked'=>false,'method'=>null,'waf_type'=>null,'confidence'=>0],
            ];
        }
    }

    private function store(Website $website, UserAgent $agent, array $results): void
    {
        BotAccessibilityResult::create([
            'website_id' => $website->id,
            'user_agent_id' => $agent->id,
            'url' => $website->base_url,
            'url_hash' => hash('sha256', $website->base_url),
            'robots_txt_allowed' => $results['robots_txt']['allowed'],
            'robots_txt_status_code' => $results['robots_txt']['status_code'] ?? null,
            'robots_txt_content' => $results['robots_txt']['content'] ?? null,
            'robots_txt_rules' => $results['robots_txt']['parsed_rules'] ?? null,
            'http_accessible' => $results['http_access']['accessible'],
            'http_status_code' => $results['http_access']['status_code'] ?? null,
            'response_time_ms' => $results['http_access']['response_time'] ?? null,
            'response_headers' => json_encode($results['http_access']['headers'] ?? []),
            'firewall_detected' => $results['firewall']['blocked'] ?? false,
            'blocking_method' => $results['firewall']['method'] ?? null,
            'waf_type' => $results['firewall']['waf_type'] ?? null,
            'detection_confidence' => $results['firewall']['confidence'] ?? null,
            'error_message' => $results['http_access']['error'] ?? $results['robots_txt']['error'] ?? null,
            'checked_at' => now(),
        ]);
    }
}
