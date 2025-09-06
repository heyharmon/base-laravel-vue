<?php

namespace Database\Factories;

use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Database\Eloquent\Factories\Factory;

class ResponseFactory extends Factory
{
    protected $model = Response::class;

    public function definition(): array
    {
        return [
            'prompt_id' => Prompt::factory(),
            'provider' => 'openai',
            'model' => 'gpt-5',
            'use_flex_processing' => false,
            'parameters' => [],
            'status' => 'pending',
            'content' => '',
        ];
    }
}
