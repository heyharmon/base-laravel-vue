<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'url',
        'summary',
        'llm_text',
        'website_id',
    ];

    /**
     * Get the website that owns the page.
     */
    public function website()
    {
        return $this->belongsTo(Website::class);
    }
}
