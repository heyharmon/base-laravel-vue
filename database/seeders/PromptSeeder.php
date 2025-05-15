<?php

namespace Database\Seeders;

use App\Models\Prompt;
use Illuminate\Database\Seeder;

class PromptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Prompt::create([
            'content' => 'How\'s the weather in Salt Lake City?',
            // 'is_active' => true,
            // 'frequency' => 'daily',
        ]);

        Prompt::create([
            'content' => 'What is the most popular php framework?',
            // 'is_active' => true,
            // 'frequency' => 'daily',
        ]);
    }
}
