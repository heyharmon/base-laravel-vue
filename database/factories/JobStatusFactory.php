<?php

namespace Database\Factories;

use App\Models\JobStatus;
use App\Models\Team;
use App\Models\Prompt;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<JobStatus>
 */
class JobStatusFactory extends Factory
{
    protected $model = JobStatus::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'campaign_id' => Campaign::factory(),
            'job_id' => (string) Str::uuid(),
            'job_class' => 'App\\Jobs\\ExampleJob',
            'job_batch_id' => null,
            'status' => $this->faker->randomElement(['pending', 'processing', 'completed', 'failed', 'cancelled']),
            'output' => null,
            'error' => null,
            'progress' => $this->faker->numberBetween(0, 100),
            'trackable_id' => Prompt::factory(),
            'trackable_type' => Prompt::class,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
