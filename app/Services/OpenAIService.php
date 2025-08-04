<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use App\Models\Category;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAIService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.openai.com/v1';

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key');
    }

    public function categorizeTransaction(Transaction $transaction): ?string
    {
        try {
            $systemInstructions = $this->buildSystemInstructions($transaction->user);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $systemInstructions,
                    ],
                    [
                        'role' => 'user',
                        'content' => $this->formatTransactionForAI($transaction),
                    ],
                ],
                'temperature' => 0.1,
                'max_tokens' => 150,
                'response_format' => [
                    'type' => 'json_object',
                ],
            ]);

            if (! $response->successful()) {
                Log::error('OpenAI API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                return null;
            }

            $result = json_decode($content, true);

            return $result['category'] ?? null;
        } catch (\Exception $e) {
            Log::error('OpenAI service error', [
                'message' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);
            return null;
        }
    }

    private function buildSystemInstructions(User $user): string
    {
        $categories = $user->categories()->withCount('transactions')->get();
        $examples = $this->getCategorizationExamples($user);

        $instructions = "You are a financial transaction categorization assistant. Your job is to categorize banking transactions into appropriate categories based on the transaction description, amount, and date.\n\n";

        $instructions .= "EXISTING CATEGORIES:\n";
        foreach ($categories as $category) {
            $instructions .= "- {$category->name} ({$category->transactions_count} transactions)\n";
        }

        if ($examples->isNotEmpty()) {
            $instructions .= "\nEXAMPLE CATEGORIZATIONS:\n";
            foreach ($examples as $example) {
                $instructions .= "Description: \"{$example->description}\" → Category: \"{$example->category->name}\"\n";
            }
        }

        $instructions .= "\nRULES:\n";
        $instructions .= "1. Analyze the transaction description to understand what the transaction is for\n";
        $instructions .= "2. Consider the amount and whether it's positive (income) or negative (expense)\n";
        $instructions .= "3. Use existing categories when possible\n";
        $instructions .= "4. If no existing category fits well, suggest a new appropriate category name\n";
        $instructions .= "5. Be consistent with the examples provided\n";
        $instructions .= "6. Category names should be clear, concise, and descriptive\n\n";

        $instructions .= "RESPONSE FORMAT:\n";
        $instructions .= "Always respond with valid JSON in this exact format:\n";
        $instructions .= '{"category": "Category Name"}\n\n';

        $instructions .= "Examples of good categories: Groceries, Gas, Restaurants, Salary, Utilities, Shopping, Entertainment, Healthcare, etc.";

        return $instructions;
    }

    private function getCategorizationExamples(User $user, int $limit = 20)
    {
        return $user->transactions()
            ->whereNotNull('category_id')
            ->with('category')
            ->inRandomOrder()
            ->limit($limit)
            ->get()
            ->groupBy('category_id')
            ->map(function ($transactions) {
                return $transactions->first();
            })
            ->values();
    }

    private function formatTransactionForAI(Transaction $transaction): string
    {
        $amount = $transaction->amount;
        $type = $amount > 0 ? 'income' : 'expense';
        $date = $transaction->date->format('Y-m-d');

        return "Transaction Details:\n" .
            "Date: {$date}\n" .
            "Amount: \${$amount} ({$type})\n" .
            "Description: \"{$transaction->description}\"\n\n" .
            "Please categorize this transaction.";
    }
}
