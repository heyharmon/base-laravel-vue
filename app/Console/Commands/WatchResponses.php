<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Response;
use App\Jobs\PollOpenAIResponseJob;

class WatchResponses extends Command
{
    protected $signature = 'responses:watch';

    protected $description = 'Revive or expire stuck responses';

    public function handle(): void
    {
        $threshold = now()->subMinutes(30);
        Response::whereIn('status', ['queued', 'in_progress', 'stalled'])
            ->where('updated_at', '<', $threshold)
            ->chunkById(50, function ($responses) {
                foreach ($responses as $response) {
                    if (($response->initial_attempts ?? 0) < 5) {
                        dispatch(new PollOpenAIResponseJob($response->id));
                    } else {
                        $response->update(['status' => 'expired', 'error_code' => 'watchdog_expired']);
                    }
                }
            });
    }
}

