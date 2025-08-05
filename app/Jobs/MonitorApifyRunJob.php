<?php

namespace App\Jobs;

use App\Models\ApifyRun;
use App\Services\GoogleMapsScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MonitorApifyRunJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 60;
    public int $backoff = 30;

    public function __construct(private int $apifyRunId)
    {
    }

    public function handle(GoogleMapsScraperService $scraperService): void
    {
        $apifyRun = ApifyRun::findOrFail($this->apifyRunId);
        try {
            $apifyRun = $scraperService->updateRunStatus($apifyRun);
            if ($apifyRun->isCompleted()) {
                if ($apifyRun->isSuccessful()) {
                    ProcessApifyResultsJob::dispatch($apifyRun->id);
                } else {
                    Log::error('Apify run failed', [
                        'run_id' => $apifyRun->apify_run_id,
                        'status' => $apifyRun->status,
                    ]);
                }
            } else {
                MonitorApifyRunJob::dispatch($apifyRun->id)->delay(30);
            }
        } catch (\Exception $e) {
            Log::error('Failed to monitor Apify run', [
                'apify_run_id' => $apifyRun->id,
                'error' => $e->getMessage(),
            ]);
            $apifyRun->update([
                'status' => 'FAILED',
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }
}
