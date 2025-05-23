<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\Keyword;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class AssignKeywordsToOrganizationsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all teams
        $teams = Team::all();

        foreach ($teams as $team) {
            // Find the owned (non-competitor) organization for this team
            $ownedOrg = $team->organizations()->where('is_competitor', false)->first();

            if (!$ownedOrg) {
                // If no owned organization exists, create one
                $ownedOrg = $team->organizations()->create([
                    'name' => $team->name,
                    'is_competitor' => false,
                ]);
            }

            // Assign all keywords for this team to the owned organization
            Keyword::whereNull('organization_id')
                ->update(['organization_id' => $ownedOrg->id]);
        }
    }
}
