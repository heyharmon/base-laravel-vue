<?php

namespace App\Jobs;

use App\Services\GoogleMapsScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class StartApifyScrapingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private array $params, private int $userId)
    {
    }

    public function handle(GoogleMapsScraperService $scraperService): void
    {
        try {
            $apifyRun = $scraperService->startScraping($this->params, $this->userId);
            MonitorApifyRunJob::dispatch($apifyRun->id)->delay(30);
        } catch (\Exception $e) {
            Log::error('Failed to start Apify scraping job', [
                'params' => $this->params,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
