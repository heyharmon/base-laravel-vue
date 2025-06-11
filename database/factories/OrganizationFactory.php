<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => $this->faker->company(),
            'website' => $this->faker->domainName(),
            'founded' => $this->faker->year(),
            'employee_count' => $this->faker->randomElement(['1-10', '11-50', '51-200', '201-500', '501-1000', '1000+']),
            'is_competitor' => true,
        ];
    }

    /**
     * Indicate that the organization is owned by the team.
     *
     * @return $this
     */
    public function owned(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_competitor' => false,
        ]);
    }
}
