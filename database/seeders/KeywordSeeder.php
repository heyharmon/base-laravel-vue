<?php

namespace Database\Seeders;

use App\Models\Keyword;
use Illuminate\Database\Seeder;

class KeywordSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Keyword::create([
            'name' => 'laravel'
        ]);

        Keyword::create([
            'name' => 'taylor otwell'
        ]);
    }
}
