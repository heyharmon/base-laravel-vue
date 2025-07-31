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
			'name' => $this->faker->sentence(3),
			'description' => $this->faker->optional()->paragraph(),
			'is_default' => false,
		];
	}

	/**
	 * Indicate that the campaign is the default campaign.
	 */
	public function default(): static
	{
		return $this->state(fn(array $attributes) => [
			'is_default' => true,
		]);
	}
}
