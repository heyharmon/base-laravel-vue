<?php

namespace App\Services;

use App\Models\ApifyRun;
use Illuminate\Support\Facades\Log;

class GoogleMapsScraperService extends BaseApifyService
{
    private const ACTOR_ID = 'compass/crawler-google-places';

    public function startScraping(array $params, int $userId): ApifyRun
    {
        $input = $this->buildInput($params);
        try {
            $runData = $this->runActor(self::ACTOR_ID, $input);
            $apifyRun = ApifyRun::create([
                'apify_run_id' => $runData['id'],
                'status' => $runData['status'],
                'input_data' => $input,
                'started_at' => now(),
                'user_id' => $userId,
            ]);
            Log::info('Started Apify Google Maps scraping', [
                'run_id' => $runData['id'],
                'user_id' => $userId,
                'input' => $input,
            ]);
            return $apifyRun;
        } catch (\Exception $e) {
            Log::error('Failed to start Apify scraping', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'input' => $input,
            ]);
            throw $e;
        }
    }

    private function buildInput(array $params): array
    {
        return [
            'searchStringsArray' => [$params['search_term'] ?? ''],
            'locationQuery' => $params['location'] ?? '',
            'maxPlaces' => $params['max_places'] ?? 100,
            'minStars' => $params['min_rating'] ?? 3,
            'skipClosedPlaces' => $params['skip_closed'] ?? true,
            'includeWebsites' => true,
            'includePhoneNumbers' => true,
            'includeImages' => true,
            'includePlusCode' => false,
            'includeCheckInStatus' => false,
        ];
    }

    public function updateRunStatus(ApifyRun $apifyRun): ApifyRun
    {
        try {
            $runData = $this->getRunStatus($apifyRun->apify_run_id);
            $apifyRun->update([
                'status' => $runData['status'],
                'finished_at' => isset($runData['finishedAt']) ? \Carbon\Carbon::parse($runData['finishedAt']) : null,
            ]);
            return $apifyRun;
        } catch (\Exception $e) {
            Log::error('Failed to update Apify run status', [
                'run_id' => $apifyRun->apify_run_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function getRunResults(ApifyRun $apifyRun): array
    {
        if (!$apifyRun->isSuccessful()) {
            throw new \Exception('Cannot get results from unsuccessful run');
        }
        try {
            return $this->getRunDataset($apifyRun->apify_run_id);
        } catch (\Exception $e) {
            Log::error('Failed to get Apify run results', [
                'run_id' => $apifyRun->apify_run_id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
