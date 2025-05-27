<?php

namespace Database\Factories;

use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Response>
 */
class ResponseFactory extends Factory
{
    protected $model = Response::class;

    public function definition(): array
    {
        return [
            'prompt_id' => Prompt::factory(),
            'provider' => $this->faker->randomElement(['openai', 'anthropic']),
            'model' => $this->faker->word(),
            // 'mentioned' => $this->faker->boolean(),
            'content' => $this->faker->paragraph(),
            'metadata' => null,
            'search' => null,
        ];
    }
}
