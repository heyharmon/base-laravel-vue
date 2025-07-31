<?php

namespace Database\Factories;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Prompt>
 */
class PromptFactory extends Factory
{
	protected $model = Prompt::class;

	public function definition(): array
	{
		$team = Team::factory();

		return [
			'team_id' => $team,
			'campaign_id' => Campaign::factory()->for($team, 'team'),
			'name' => $this->faker->sentence(3),
			'content' => $this->faker->paragraph(),
			'description' => $this->faker->optional()->sentence(),
		];
	}
}
