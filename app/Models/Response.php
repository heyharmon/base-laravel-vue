<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Response extends Model
{
    use HasFactory;

    protected $fillable = [
        'run_id',
        'provider',
        'content',
        'tokens',
        'latency',
        'error',
    ];

    protected $casts = [
        'tokens' => 'integer',
        'latency' => 'float',
    ];

    /**
     * The run that this response belongs to.
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(Run::class);
    }
}