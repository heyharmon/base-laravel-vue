<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Response;
use Illuminate\Support\Facades\Log;

class ResumeStalledResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 5;

    protected int $responseId;
    protected int $baseDelay = 15;

    public function __construct(int $responseId)
    {
        $this->responseId = $responseId;
    }

    public function handle(): void
    {
        $response = Response::find($this->responseId);
        if (!$response || $response->status !== 'stalled') {
            return;
        }

        $response->update(['poll_attempts' => 0, 'status' => 'queued']);
        dispatch((new PollOpenAIResponseJob($response->id))->delay($this->baseDelay));
        Log::info('ResumeStalledResponseJob dispatched PollOpenAIResponseJob', ['response_id' => $response->id, 'job_id' => $this->job->getJobId() ?? 'unknown']);
    }

    public function tags(): array
    {
        return ['response:' . $this->responseId];
    }
}

