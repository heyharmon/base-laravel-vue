<?php

namespace Database\Factories;

use App\Models\InvitationToken;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvitationToken>
 */
class InvitationTokenFactory extends Factory
{
    protected $model = InvitationToken::class;

    public function definition(): array
    {
        return [
            'email' => $this->faker->safeEmail(),
            'token' => Str::random(32),
            'team_id' => Team::factory(),
            'expires_at' => now()->addDays(7),
        ];
    }
}
