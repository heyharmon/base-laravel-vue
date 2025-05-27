<?php

namespace App\Jobs;

use App\Models\Keyword;
use App\Models\Response;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckKeywordInPastResponsesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The keyword instance.
     *
     * @var \App\Models\Keyword
     */
    protected $keyword;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\Keyword  $keyword
     * @return void
     */
    public function __construct(Keyword $keyword)
    {
        $this->keyword = $keyword;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            Log::info('Starting CheckKeywordInPastResponsesJob for keyword: ' . $this->keyword->name);

            // Get all responses for prompts in the same team
            $responses = Response::whereHas('prompt', function ($query) {
                $query->whereHas('team', function ($teamQuery) {
                    $teamQuery->where('id', $this->keyword->team_id);
                });
            })->get();

            $keywordName = strtolower($this->keyword->name);
            $foundInResponses = 0;
            $foundInPrompts = [];

            foreach ($responses as $response) {
                $responseText = strtolower($response->content);

                // Check if the keyword exists in the response
                if (str_contains($responseText, $keywordName)) {
                    $foundInResponses++;

                    // Attach keyword to response
                    $response->keywords()->syncWithoutDetaching([$this->keyword->id]);

                    // Get the prompt for this response
                    $prompt = $response->prompt;

                    // Update the pivot table for keyword-prompt relationship
                    $pivot = $prompt->keywords()->syncWithoutDetaching([$this->keyword->id]);

                    // If this is a new relationship, initialize the count
                    if (isset($pivot[$this->keyword->id]) && $pivot[$this->keyword->id]['created']) {
                        $prompt->keywords()->updateExistingPivot($this->keyword->id, [
                            'count' => 1,
                            'last_found_at' => now(),
                        ]);
                        $foundInPrompts[$prompt->id] = 1;
                    } else {
                        // Increment the count and update last_found_at
                        $prompt->keywords()->updateExistingPivot($this->keyword->id, [
                            'count' => DB::raw('count + 1'),
                            'last_found_at' => now(),
                        ]);
                        $foundInPrompts[$prompt->id] = ($foundInPrompts[$prompt->id] ?? 0) + 1;
                    }
                }
            }

            Log::info("Completed CheckKeywordInPastResponsesJob for keyword '{$this->keyword->name}'. Found in {$foundInResponses} responses across " . count($foundInPrompts) . " prompts.");

        } catch (\Exception $e) {
            Log::error('Error in CheckKeywordInPastResponsesJob: ' . $e->getMessage());
            throw $e;
        }
    }
}
