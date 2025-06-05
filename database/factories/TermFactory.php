<?php

namespace Database\Factories;

use App\Models\Term;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Term>
 */
class TermFactory extends Factory
{
    protected $model = Term::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->word(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
