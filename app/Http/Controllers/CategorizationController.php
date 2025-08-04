<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\CategorizationJob;
use App\Jobs\CategorizeTransactionJob;
use App\Jobs\BatchCategorizeTransactionsJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategorizationController extends Controller
{
    public function categorizeTransaction(Transaction $transaction)
    {
        if ($transaction->category_id) {
            return response()->json([
                'message' => 'Transaction is already categorized',
            ], 422);
        }

        CategorizeTransactionJob::dispatch($transaction);

        return response()->json([
            'message' => 'Transaction categorization started',
        ]);
    }

    public function categorizeBatch(Request $request)
    {
        $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'exists:transactions,id',
        ]);

        $transactions = Auth::user()->transactions()
            ->whereIn('id', $request->transaction_ids)
            ->pluck('id')
            ->toArray();

        if (count($transactions) !== count($request->transaction_ids)) {
            return response()->json([
                'message' => 'Some transactions do not belong to you',
            ], 403);
        }

        BatchCategorizeTransactionsJob::dispatch(
            $transactions,
            Auth::id(),
            'batch'
        );

        return response()->json([
            'message' => 'Batch categorization started',
            'transaction_count' => count($transactions),
        ]);
    }

    public function categorizeAll()
    {
        $uncategorizedTransactions = Auth::user()->transactions()
            ->aiCategorizable()
            ->pluck('id')
            ->toArray();

        if (empty($uncategorizedTransactions)) {
            return response()->json([
                'message' => 'No uncategorized transactions found',
            ], 422);
        }

        BatchCategorizeTransactionsJob::dispatch(
            $uncategorizedTransactions,
            Auth::id(),
            'all'
        );

        return response()->json([
            'message' => 'Categorization of all uncategorized transactions started',
            'transaction_count' => count($uncategorizedTransactions),
        ]);
    }

    public function getJobs()
    {
        $jobs = Auth::user()->categorizationJobs()
            ->latest()
            ->paginate(10);

        return response()->json($jobs);
    }

    public function getJobStatus($batchId)
    {
        $job = Auth::user()->categorizationJobs()
            ->where('batch_id', $batchId)
            ->first();

        if (! $job) {
            return response()->json(['message' => 'Job not found'], 404);
        }

        return response()->json($job);
    }

    public function getActiveJobs()
    {
        $activeJobs = Auth::user()->categorizationJobs()
            ->whereIn('status', ['pending', 'processing'])
            ->get();

        return response()->json($activeJobs);
    }
}
