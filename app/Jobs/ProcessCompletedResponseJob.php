<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Response as ResponseModel;
use App\Models\Prompt;
use App\Models\Term;
use App\Services\OpenAIPromptService;

class ProcessCompletedResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 120; // 2 minutes is plenty for processing

    protected int $responseDbId;

    public function __construct(int $responseDbId)
    {
        $this->responseDbId = $responseDbId;
    }

    public function handle(OpenAIPromptService $openAI): void
    {
        $response = ResponseModel::with('prompt')->find($this->responseDbId);
        if (!$response || $response->status !== 'completed') {
            return;
        }

        try {
            if ($response->provider_id) {
                $fresh = $openAI->retrieveResponse($response->provider_id);
                $this->saveSearchData($fresh, $response);
            }
        } catch (Throwable $e) {
            Log::warning('Failed to retrieve response for annotations during processing', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->checkForTerms($response, $response->prompt);

            if ($response->prompt->responses()->where('status', 'completed')->count() == 1) {
                dispatch(new FindCompetitorsInResponseJob($response->prompt));
            }
        } catch (Throwable $e) {
            $response->update([
                'status' => 'processing_failed',
                'processing_error_code' => $e->getCode() ?: null,
                'processing_error_message' => $e->getMessage(),
            ]);
            Log::error('ProcessCompletedResponseJob failed', [
                'response_id' => $response->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function tags(): array
    {
        return ['response:' . $this->responseDbId];
    }

    protected function saveSearchData(object $llmResponse, ResponseModel $response): void
    {
        $searchData = [
            'annotations' => $llmResponse->annotations ?? [],
        ];

        $response->update(['search' => $searchData]);
    }

    protected function checkForTerms(ResponseModel $response, Prompt $prompt): void
    {
        $teamId = $prompt->team_id;
        $campaignId = $prompt->campaign_id;

        // Get terms for all organizations scoped to the team and campaign
        $terms = Term::whereHas('organization', function ($query) use ($teamId, $campaignId) {
            $query->where('team_id', $teamId)
                ->where(function ($q) use ($campaignId) {
                    $q->where('campaign_id', $campaignId)
                        ->orWhere(function ($subQ) {
                            $subQ->whereNull('campaign_id')->where('is_competitor', false);
                        });
                });
        })->get();

        $responseText = strtolower($response->content ?? '');
        $foundTerms = [];

        foreach ($terms as $term) {
            $termName = strtolower($term->name);

            if ($termName !== '' && str_contains($responseText, $termName)) {
                $foundTerms[] = $term->id;
                $existingRelation = $prompt->terms()->where('term_id', $term->id)->exists();

                if (!$existingRelation) {
                    $prompt->terms()->attach($term->id, [
                        'count' => 1,
                        'last_found_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $prompt->terms()->updateExistingPivot($term->id, [
                        'count' => DB::raw('count + 1'),
                        'last_found_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        if (!empty($foundTerms)) {
            $response->terms()->syncWithoutDetaching($foundTerms);
        }
    }
}

