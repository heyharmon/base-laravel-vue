<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentBlock extends Model
{
    protected $fillable = [
        'blockable_id', 'blockable_type', 'block_type',
        'block_data', 'sort_order'
    ];

    protected $casts = [
        'block_data' => 'array',
    ];

    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }
}
