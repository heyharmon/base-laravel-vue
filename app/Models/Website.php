<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Website extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', // The name of the website
        'description', // The description of the website
        'outline', // The AI generated outline of content of the website
    ];

    public function pages()
    {
        return $this->hasMany(Page::class);
    }
}
