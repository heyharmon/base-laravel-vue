<?php

namespace App\Jobs;

use App\Models\CategorizationJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class BatchCategorizeTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private array $transactionIds,
        private int $userId,
        private string $type = 'batch'
    ) {}

    public function handle(): void
    {
        $batchId = Str::uuid();

        $categorizationJob = CategorizationJob::create([
            'batch_id' => $batchId,
            'user_id' => $this->userId,
            'type' => $this->type,
            'total_transactions' => count($this->transactionIds),
            'status' => 'pending',
        ]);

        $categorizationJob->markAsStarted();

        foreach ($this->transactionIds as $transactionId) {
            $transaction = \App\Models\Transaction::find($transactionId);

            if ($transaction && $transaction->user_id === $this->userId) {
                CategorizeTransactionJob::dispatch($transaction, $categorizationJob);
            }
        }
    }
}
