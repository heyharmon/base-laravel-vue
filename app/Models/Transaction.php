<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'amount',
        'description',
        'account_id',
        'category_id',
        'user_id',
        'is_ai_categorized',
        'ai_categorized_at',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
        'is_ai_categorized' => 'boolean',
        'ai_categorized_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeDeposits($query)
    {
        return $query->where('amount', '>', 0);
    }

    public function scopeSpends($query)
    {
        return $query->where('amount', '<', 0);
    }

    public function scopeUncategorized($query)
    {
        return $query->whereNull('category_id');
    }

    public function scopeAiCategorizable($query)
    {
        return $query->whereNull('category_id')
            ->where('is_ai_categorized', false);
    }

    public function markAsAiCategorized(): void
    {
        $this->update([
            'is_ai_categorized' => true,
            'ai_categorized_at' => now(),
        ]);
    }
}
