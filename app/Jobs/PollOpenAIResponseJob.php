<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\OpenAIPromptService;
use App\Models\Response as ResponseModel;

class PollOpenAIResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 5;

    protected int $responseDbId;
    protected int $pollCount;
    protected int $retryCount;

    public const MAX_RETRIES = 5;

    public function __construct(int $responseDbId, int $pollCount = 0, int $retryCount = 0)
    {
        $this->responseDbId = $responseDbId;
        $this->pollCount = $pollCount;
        $this->retryCount = $retryCount;
    }

    public function handle(OpenAIPromptService $openAI)
    {
        try {
            $response = ResponseModel::with('prompt')->find($this->responseDbId);
            if (!$response) {
                return;
            }

            if ($response->status === 'completed') {
                return;
            }

            if (!$response->provider_id) {
                $this->reissueIfFailed($response, $openAI);
                return;
            }

            $fresh = $openAI->retrieveResponse($response->provider_id);
            $status = $fresh->status ?? 'in_progress';

            if ($status === 'completed') {
                $response->content = $fresh->content ?? '';
                $response->usage = $fresh->usage ?? null;
                $response->status = 'completed';
                $response->save();

                dispatch(new ProcessCompletedResponseJob($response->id));
                return;
            }

            if (in_array($status, ['failed', 'cancelled', 'incomplete'])) {
                $this->reissueIfFailed($response, $openAI);
                return;
            }

            $response->status = $status;
            $response->save();

            $delay = min(15 * pow(2, $this->pollCount), 900);
            $next = new self($response->id, $this->pollCount + 1, $this->retryCount);
            $next->delay(now()->addSeconds($delay));
            dispatch($next);
        } catch (Throwable $exception) {
            Log::warning('PollOpenAIResponseJob encountered exception', [
                'response_db_id' => $this->responseDbId,
                'error' => $exception->getMessage(),
            ]);

            $delay = min(15 * pow(2, $this->pollCount), 900);
            $next = new self($this->responseDbId, $this->pollCount + 1, $this->retryCount);
            $next->delay(now()->addSeconds($delay));
            dispatch($next);
        }
    }

    protected function reissueIfFailed(ResponseModel $response, OpenAIPromptService $openAI): void
    {
        if ($this->retryCount >= self::MAX_RETRIES) {
            $response->status = 'failed';
            $response->error = 'Max retries exceeded';
            $response->save();
            return;
        }

        try {
            $prompt = $response->prompt;
            $options = [];
            if ($response->flex) {
                $options['service_tier'] = 'flex';
            }
            $fresh = $openAI->getResponse($prompt->content, $response->model, $options);

            $response->provider_id = $fresh->id ?? null;
            $response->status = $fresh->status ?? 'in_progress';
            $response->content = '';
            $response->usage = null;
            $response->error = null;
            $response->save();

            $next = new self($response->id, 0, $this->retryCount + 1);
            $next->delay(now()->addSeconds(15));
            dispatch($next);
        } catch (\Exception $e) {
            Log::error('Failed to reissue OpenAI response after failure', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
            $response->status = 'failed';
            $response->error = $e->getMessage();
            $response->save();
        }
    }
}
