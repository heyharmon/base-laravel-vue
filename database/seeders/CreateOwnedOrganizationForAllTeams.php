<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class CreateOwnedOrganizationForAllTeams extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default organizations for existing teams that don't have one
        $teams = Team::doesntHave('organizations')->get();
        
        foreach ($teams as $team) {
            Organization::create([
                'team_id' => $team->id,
                'name' => $team->name,
                'website' => '',
                'founded' => '',
                'employee_count' => '',
                'is_competitor' => false,
            ]);
        }
    }
}
