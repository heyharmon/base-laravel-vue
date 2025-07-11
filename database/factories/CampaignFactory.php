<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
	protected $model = Campaign::class;

	public function definition(): array
	{
		return [
			'team_id' => Team::factory(),
			'name' => $this->faker->words(2, true),
			'description' => $this->faker->sentence(),
			'is_default' => false,
		];
	}

	public function default(): static
	{
		return $this->state([
			'is_default' => true,
		]);
	}
}
