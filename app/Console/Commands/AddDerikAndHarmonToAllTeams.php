<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AddDerikAndHarmonToAllTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-derik-and-harmon-to-all-teams';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add users with IDs 1 and 2 to all teams they are not already part of';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userIds = [1, 2];
        $teams = Team::all();
        $now = Carbon::now();
        $addedCount = 0;

        $this->info('Starting to add users to teams...');

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            
            if (!$user) {
                $this->error("User with ID {$userId} not found.");
                continue;
            }
            
            $this->info("Processing user {$userId} ({$user->name})...");
            
            $userTeamIds = $user->teams()->pluck('teams.id')->toArray();
            $teamsToAdd = $teams->whereNotIn('id', $userTeamIds);
            
            if ($teamsToAdd->isEmpty()) {
                $this->info("User {$userId} is already part of all teams.");
                continue;
            }
            
            foreach ($teamsToAdd as $team) {
                $team->users()->attach($userId, [
                    'role' => 'member',
                    'invitation_accepted' => true,
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now
                ]);
                
                $addedCount++;
                $this->info("Added user {$userId} ({$user->name}) to team {$team->id} ({$team->name}).");
            }
        }

        $this->info("Completed. Added users to {$addedCount} team relationships.");
        
        return Command::SUCCESS;
    }
}
