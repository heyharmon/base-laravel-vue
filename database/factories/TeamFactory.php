<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    protected $model = Team::class;

    public function definition(): array
    {
        static $count = 1;
        return [
            'name' => 'Team ' . $count++,
            'owner_id' => User::factory(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Team $team): void {
            $owner = $team->owner;

            if ($owner) {
                $team->users()->attach($owner->id, [
                    'role' => 'owner',
                    'invitation_accepted' => true,
                    'invitation_sent_at' => now(),
                    'joined_at' => now(),
                ]);

                $owner->update(['current_team_id' => $team->id]);
            }
        });
    }
}
