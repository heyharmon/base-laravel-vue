<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TransactionController extends Controller
{
    public function index(Request $request)
    {
        $query = Auth::user()->transactions()->with(['account', 'category']);

        if ($request->has('account_id') && $request->account_id) {
            $query->where('account_id', $request->account_id);
        }

        if ($request->has('category_id') && $request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('type') && $request->type) {
            if ($request->type === 'deposit') {
                $query->deposits();
            } elseif ($request->type === 'spend') {
                $query->spends();
            }
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('date', '<=', $request->date_to);
        }

        if ($request->has('amount_min') && $request->amount_min) {
            $query->where('amount', '>=', $request->amount_min);
        }

        if ($request->has('amount_max') && $request->amount_max) {
            $query->where('amount', '<=', $request->amount_max);
        }

        if ($request->has('search') && $request->search) {
            $query->where('description', 'like', '%' . $request->search . '%');
        }

        $transactions = $query->latest('date')
            ->paginate($request->get('per_page', 50));

        return response()->json($transactions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'required|string',
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $validated['user_id'] = Auth::id();
        $transaction = Transaction::create($validated);
        $transaction->load(['account', 'category']);

        return response()->json($transaction, 201);
    }

    public function show(Transaction $transaction)
    {
        $transaction->load(['account', 'category']);

        return response()->json($transaction);
    }

    public function update(Request $request, Transaction $transaction)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'amount' => 'required|numeric',
            'description' => 'required|string',
            'account_id' => 'required|exists:accounts,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $transaction->update($validated);
        $transaction->load(['account', 'category']);

        return response()->json($transaction);
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted successfully']);
    }

    public function uploadCsv(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt',
            'account_id' => 'required|exists:accounts,id',
        ]);

        $account = Account::findOrFail($request->account_id);

        $file = $request->file('file');
        $csvData = array_map('str_getcsv', file($file->getPathname()));
        $header = array_shift($csvData);

        $dateIndex = $this->findColumnIndex($header, ['date']);
        $amountIndex = $this->findColumnIndex($header, ['amount']);
        $descriptionIndex = $this->findColumnIndex($header, ['description', 'desc']);

        if ($dateIndex === false || $amountIndex === false || $descriptionIndex === false) {
            return response()->json([
                'error' => 'Required columns not found. Expected: date, amount, description'
            ], 422);
        }

        $transactions = [];
        $errors = [];

        DB::beginTransaction();
        try {
            foreach ($csvData as $index => $row) {
                if (count($row) < max($dateIndex, $amountIndex, $descriptionIndex) + 1) {
                    $errors[] = "Row " . ($index + 2) . ": Insufficient columns";
                    continue;
                }

                $dateStr = trim($row[$dateIndex]);
                $amountStr = trim($row[$amountIndex]);
                $description = trim($row[$descriptionIndex]);

                try {
                    $date = Carbon::createFromFormat('m/d/Y', $dateStr);
                } catch (\Exception $e) {
                    $errors[] = "Row " . ($index + 2) . ": Invalid date format";
                    continue;
                }

                $amount = floatval($amountStr);

                if (empty($description)) {
                    $errors[] = "Row " . ($index + 2) . ": Empty description";
                    continue;
                }

                $transaction = Transaction::create([
                    'date' => $date,
                    'amount' => $amount,
                    'description' => $description,
                    'account_id' => $account->id,
                    'user_id' => Auth::id(),
                ]);

                $transactions[] = $transaction;
            }

            DB::commit();

            return response()->json([
                'message' => 'CSV uploaded successfully',
                'imported' => count($transactions),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Import failed'], 500);
        }
    }

    public function bulkUpdateCategory(Request $request)
    {
        $validated = $request->validate([
            'transaction_ids' => 'required|array',
            'transaction_ids.*' => 'exists:transactions,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $transactions = Auth::user()->transactions()
            ->whereIn('id', $validated['transaction_ids'])
            ->get();

        foreach ($transactions as $transaction) {
            $transaction->update(['category_id' => $validated['category_id']]);
        }

        return response()->json([
            'message' => 'Categories updated successfully',
            'updated' => $transactions->count(),
        ]);
    }

    private function findColumnIndex($header, $possibleNames)
    {
        foreach ($header as $index => $column) {
            foreach ($possibleNames as $name) {
                if (stripos($column, $name) !== false) {
                    return $index;
                }
            }
        }
        return false;
    }
}
