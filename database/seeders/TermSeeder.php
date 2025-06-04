<?php

namespace Database\Seeders;

use App\Models\Term;
use Illuminate\Database\Seeder;

class TermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Term::create([
            'name' => 'laravel'
        ]);

        Term::create([
            'name' => 'taylor otwell'
        ]);
    }
}
