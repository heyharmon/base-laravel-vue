<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', // The name of the website
        'description', // The description of the website
        'outline', // The AI generated outline of content of the website
    ];

    // /**
    //  * Get the pages for the website.
    //  */
    // public function pages(): HasMany
    // {
    //     return $this->hasMany(Page::class);
    // }
}
