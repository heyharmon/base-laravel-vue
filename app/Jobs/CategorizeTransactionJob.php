<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\Category;
use App\Models\CategorizationJob;
use App\Services\OpenAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CategorizeTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        private Transaction $transaction,
        private ?CategorizationJob $categorizationJob = null
    ) {}

    public function handle(OpenAIService $openAIService): void
    {
        try {
            if ($this->transaction->category_id) {
                $this->updateProgress(true);
                return;
            }

            $categoryName = $openAIService->categorizeTransaction($this->transaction);

            if (! $categoryName) {
                throw new \Exception('OpenAI did not return a category');
            }

            $category = Category::firstOrCreate(
                [
                    'name' => $categoryName,
                    'user_id' => $this->transaction->user_id,
                ],
                [
                    'name' => $categoryName,
                    'user_id' => $this->transaction->user_id,
                ]
            );

            $this->transaction->update([
                'category_id' => $category->id,
            ]);

            $this->transaction->markAsAiCategorized();

            $this->updateProgress(true);

            Log::info('Transaction categorized successfully', [
                'transaction_id' => $this->transaction->id,
                'category' => $categoryName,
                'existing_category' => ! $category->wasRecentlyCreated,
            ]);
        } catch (\Exception $e) {
            $this->updateProgress(false);

            Log::error('Failed to categorize transaction', [
                'transaction_id' => $this->transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateProgress(false);

        Log::error('Transaction categorization job failed permanently', [
            'transaction_id' => $this->transaction->id,
            'error' => $exception->getMessage(),
        ]);
    }

    private function updateProgress(bool $successful): void
    {
        if ($this->categorizationJob) {
            $this->categorizationJob->incrementProgress($successful);

            if (! $successful) {
                $failedIds = $this->categorizationJob->failed_transaction_ids ?? [];
                $failedIds[] = $this->transaction->id;
                $this->categorizationJob->update([
                    'failed_transaction_ids' => $failedIds,
                ]);
            }
        }
    }
}
