<?php

namespace Database\Factories;

use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prompt>
 */
class PromptFactory extends Factory
{
    protected $model = Prompt::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->sentence(3),
            'content' => $this->faker->paragraph(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
