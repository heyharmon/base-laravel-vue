<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', // The title of the page
        'slug', // The slug (auto generated on creation)
        'outline', // The AI generated outline of content of the page
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (!$page->slug) {
                $page->slug = static::generateUniqueSlug($page->title);
            }
        });
    }

    public static function generateUniqueSlug($title)
    {
        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $count = 1;
        
        // Check if the slug already exists
        while (static::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$count}";
            $count++;
        }
        
        return $slug;
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
