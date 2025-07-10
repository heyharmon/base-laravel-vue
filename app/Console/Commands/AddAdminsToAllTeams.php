<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AddAdminsToAllTeams extends Command
{
	/**
	 * The name and signature of the console command.
	 *
	 * @var string
	 */
	protected $signature = 'app:add-admins-to-all-teams';

	/**
	 * The console command description.
	 *
	 * @var string
	 */
	protected $description = 'Add specific users by email to all teams they are not already part of';

	/**
	 * Execute the console command.
	 */
	public function handle()
	{
		$userEmails = ['derik.krauss@metrifi.com', 'ryan.harmon@metrifi.com', 'elisha.po@metrifi.com'];
		$teams = Team::all();
		$now = Carbon::now();
		$addedCount = 0;

		$this->info('Starting to add users to teams...');

		foreach ($userEmails as $email) {
			$user = User::where('email', $email)->first();

			if (!$user) {
				$this->error("User with email {$email} not found.");
				continue;
			}

			$this->info("Processing user {$user->id} ({$user->name}, {$email})...");

			$userTeamIds = $user->teams()->pluck('teams.id')->toArray();
			$teamsToAdd = $teams->whereNotIn('id', $userTeamIds);

			if ($teamsToAdd->isEmpty()) {
				$this->info("User {$user->name} ({$email}) is already part of all teams.");
				continue;
			}

			foreach ($teamsToAdd as $team) {
				$team->users()->attach($user->id, [
					'role' => 'member',
					'invitation_accepted' => true,
					'joined_at' => $now,
					'created_at' => $now,
					'updated_at' => $now
				]);

				$addedCount++;
				$this->info("Added user {$user->name} ({$email}) to team {$team->id} ({$team->name}).");
			}
		}

		$this->info("Completed. Added users to {$addedCount} team relationships.");

		return Command::SUCCESS;
	}
}
