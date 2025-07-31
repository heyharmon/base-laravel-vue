<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Team;
use App\Models\Campaign;
use App\Models\User;
use App\Models\Organization;
use App\Models\Term;
use App\Jobs\CheckTermInPastResponsesJob;
use App\Services\JobDispatcherService;

/**
 * Consolidate multiple teams into parent teams by moving campaigns.
 * 
 * This command:
 * - Moves campaigns from child teams to parent teams
 * - Recalculates visibility metrics for the parent's owned organization
 * - Migrates users, data, and relationships
 * - Deletes owned organizations from child teams (keeping only parent's)
 * - Handles all data integrity and foreign key updates
 * 
 * @package App\Console\Commands
 */
class ConsolidateTeams extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'teams:consolidate 
                            {--dry-run : Run the command in dry-run mode without making changes}
                            {--force : Skip confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consolidate child teams into parent teams by moving their campaigns and recalculating visibility metrics';

    /**
     * Define the teams to consolidate.
     * Format: parent_team_id => [child_team_ids]
     *
     * @var array
     */
    protected $teamsToConsolidate = [
        // Example structure:
        // 1 => [2, 3, 4], // ACME Company 1 will absorb teams 2, 3, 4
        // 5 => [6, 7],    // Another Company will absorb teams 6, 7

        171 => [181, 179],
        53 => [118],
        33 => [116, 186, 187, 200],
        135 => [137],
        196 => [198, 203],
        102 => [119],
        24 => [127, 128],
        131 => [23],
        224 => [225],
        30 => [217],
        195 => [197, 202],
        191 => [192, 193],
        183 => [184, 190],
        76 => [109],
        103 => [123],
        95 => [163, 165],
        32 => [36, 86, 156],
        56 => [124],
        88 => [166],
        80 => [218],
        97 => [138],
        188 => [185],
        70 => [83, 129],
        113 => [114, 115, 219],
        55 => [105],
        71 => [18],
        178 => [1],
        175 => [176, 177],
        35 => [84],
        104 => [117],
        164 => [173, 174],
        54 => [126],
        141 => [151, 154],
        168 => [59],
        81 => [107, 108],
        160 => [161],
        147 => [148],
        39 => [20],
    ];

    /**
     * Execute the console command.
     */
    public function handle()
    {
        if (empty($this->teamsToConsolidate)) {
            $this->error('No teams defined for consolidation. Please update the $teamsToConsolidate array.');
            return 1;
        }

        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->info('🏃 Running in DRY-RUN mode - no changes will be made');
        }

        // Validate all teams exist before proceeding
        if (!$this->validateTeams()) {
            return 1;
        }

        // Show summary of what will happen
        $this->showConsolidationSummary();

        // Confirm unless forced
        if (!$this->option('force') && !$isDryRun) {
            if (!$this->confirm('Do you want to proceed with the consolidation?')) {
                $this->info('Consolidation cancelled.');
                return 0;
            }
        }

        $this->info('Starting team consolidation process...');

        if (!$isDryRun) {
            DB::beginTransaction();
        }

        try {
            foreach ($this->teamsToConsolidate as $parentTeamId => $childTeamIds) {
                $parentTeam = Team::find($parentTeamId);

                $this->info("\nProcessing parent team: {$parentTeam->name} (ID: {$parentTeamId})");

                foreach ($childTeamIds as $childTeamId) {
                    $childTeam = Team::find($childTeamId);

                    $this->info("  - Consolidating child team: {$childTeam->name} (ID: {$childTeamId})");
                    $this->consolidateTeam($parentTeam, $childTeam, $isDryRun);
                }
            }

            if (!$isDryRun) {
                DB::commit();
                $this->info("\nTeam consolidation completed successfully!");
            } else {
                $this->info("\n✅ Dry-run completed successfully! No changes were made.");
            }

            return 0;
        } catch (\Exception $e) {
            if (!$isDryRun) {
                DB::rollBack();
            }
            $this->error('An error occurred during consolidation: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }

    /**
     * Consolidate a child team into a parent team.
     *
     * @param Team $parentTeam
     * @param Team $childTeam
     * @param bool $isDryRun
     */
    protected function consolidateTeam(Team $parentTeam, Team $childTeam, bool $isDryRun = false)
    {
        // Step 1: Move the child team's default campaign to the parent team
        $this->info("    Moving campaigns...");
        $childDefaultCampaign = Campaign::where('team_id', $childTeam->id)
            ->where('is_default', true)
            ->first();

        if ($childDefaultCampaign) {
            $originalName = $childDefaultCampaign->name;
            $newName = $originalName === 'Default Campaign'
                ? "Campaign from {$childTeam->name}"
                : $originalName;

            if (!$isDryRun) {
                $childDefaultCampaign->update([
                    'team_id' => $parentTeam->id,
                    'name' => $newName,
                    'is_default' => false, // Only one default campaign per team
                ]);
            }
            $this->info("      ✓ " . ($isDryRun ? "Would move" : "Moved") . " campaign: {$childDefaultCampaign->name} → {$newName}");
        }

        // Move any other campaigns (non-default) from child team
        $otherCampaigns = Campaign::where('team_id', $childTeam->id)
            ->where('is_default', false)
            ->get();

        foreach ($otherCampaigns as $campaign) {
            if (!$isDryRun) {
                $campaign->update(['team_id' => $parentTeam->id]);
            }
            $this->info("      ✓ " . ($isDryRun ? "Would move" : "Moved") . " campaign: {$campaign->name}");
        }

        // Step 2: Recalculate visibility metrics for parent's owned organization
        $this->info("    Recalculating visibility metrics...");

        // Get parent team's owned organization
        $parentOwnedOrg = Organization::where('team_id', $parentTeam->id)
            ->where('is_competitor', false)
            ->first();

        if ($parentOwnedOrg && ($childDefaultCampaign || $otherCampaigns->isNotEmpty())) {
            // Get all terms for the parent's owned organization
            $parentOrgTerms = Term::where('organization_id', $parentOwnedOrg->id)->get();

            if ($parentOrgTerms->isNotEmpty()) {
                $this->info("      Found {$parentOrgTerms->count()} terms for parent's owned organization");

                if (!$isDryRun) {
                    // Get job dispatcher service
                    $jobDispatcher = app(JobDispatcherService::class);

                    // Queue jobs to check each term in past responses
                    foreach ($parentOrgTerms as $term) {
                        $job = new CheckTermInPastResponsesJob($term);
                        $jobDispatcher->dispatch($term, $job);
                    }

                    $this->info("      ✓ Queued {$parentOrgTerms->count()} jobs to recalculate visibility metrics");
                } else {
                    $this->info("      ✓ Would queue {$parentOrgTerms->count()} jobs to recalculate visibility metrics");
                }
            } else {
                $this->info("      No terms found for parent's owned organization");
            }
        }

        // Step 3: Add child team users to parent team
        $this->info("    Adding users to parent team...");
        $childTeamUsers = $childTeam->users()->get();

        foreach ($childTeamUsers as $user) {
            // Check if user is already in parent team
            if (!$parentTeam->users()->where('user_id', $user->id)->exists()) {
                // Get the user's role and invitation details from child team
                $pivotData = $childTeam->users()->where('user_id', $user->id)->first()->pivot;

                if (!$isDryRun) {
                    // Attach user to parent team with same role and status
                    $parentTeam->users()->attach($user->id, [
                        'role' => $pivotData->role,
                        'invitation_accepted' => $pivotData->invitation_accepted,
                        'invitation_sent_at' => $pivotData->invitation_sent_at,
                        'joined_at' => $pivotData->joined_at,
                    ]);
                }

                $this->info("      ✓ " . ($isDryRun ? "Would add" : "Added") . " user: {$user->name} ({$user->email})");
            } else {
                $this->info("      - User already in parent team: {$user->name} ({$user->email})");
            }
        }

        // Step 4: Handle owned organizations (only one per team allowed)
        $this->info("    Handling owned organizations...");

        // Get parent team's owned organization
        $parentOwnedOrg = DB::table('organizations')
            ->where('team_id', $parentTeam->id)
            ->where('is_competitor', false)
            ->first();

        // Check if child team has an owned organization
        $childOwnedOrg = DB::table('organizations')
            ->where('team_id', $childTeam->id)
            ->where('is_competitor', false)
            ->first();

        if ($childOwnedOrg) {
            // Count associated terms that will be deleted
            $termCount = DB::table('terms')
                ->where('organization_id', $childOwnedOrg->id)
                ->count();

            // Count articles that need to be reassigned
            $articleCount = DB::table('articles')
                ->where('organization_id', $childOwnedOrg->id)
                ->count();

            if (!$isDryRun) {
                // If parent has an owned org, reassign articles to it
                if ($parentOwnedOrg && $articleCount > 0) {
                    DB::table('articles')
                        ->where('organization_id', $childOwnedOrg->id)
                        ->update(['organization_id' => $parentOwnedOrg->id]);
                } elseif ($articleCount > 0) {
                    // If no parent owned org, set to null
                    DB::table('articles')
                        ->where('organization_id', $childOwnedOrg->id)
                        ->update(['organization_id' => null]);
                }

                // Delete associated terms (due to foreign key constraint)
                DB::table('terms')
                    ->where('organization_id', $childOwnedOrg->id)
                    ->delete();

                // Delete the owned organization
                DB::table('organizations')
                    ->where('id', $childOwnedOrg->id)
                    ->delete();
            }

            $this->info("      ✓ " . ($isDryRun ? "Would delete" : "Deleted") . " owned organization: {$childOwnedOrg->name}");
            if ($termCount > 0) {
                $this->info("        └─ " . ($isDryRun ? "Would delete" : "Deleted") . " {$termCount} associated terms");
            }
            if ($articleCount > 0 && $parentOwnedOrg) {
                $this->info("        └─ " . ($isDryRun ? "Would reassign" : "Reassigned") . " {$articleCount} articles to parent's owned org");
            } elseif ($articleCount > 0) {
                $this->info("        └─ " . ($isDryRun ? "Would unlink" : "Unlinked") . " {$articleCount} articles (no parent owned org)");
            }
        }

        // Step 5: Update all references from child team to parent team
        $this->info("    Updating team references...");

        // Update Prompts
        $promptCount = DB::table('prompts')->where('team_id', $childTeam->id)->count();
        if (!$isDryRun && $promptCount > 0) {
            DB::table('prompts')
                ->where('team_id', $childTeam->id)
                ->update(['team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$promptCount} prompts");

        // Update Organizations (only competitors, as owned org was deleted)
        $orgCount = DB::table('organizations')
            ->where('team_id', $childTeam->id)
            ->where('is_competitor', true)
            ->count();
        if (!$isDryRun && $orgCount > 0) {
            DB::table('organizations')
                ->where('team_id', $childTeam->id)
                ->where('is_competitor', true)
                ->update(['team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$orgCount} competitor organizations");

        // Update Articles
        $articleCount = DB::table('articles')->where('team_id', $childTeam->id)->count();
        if (!$isDryRun && $articleCount > 0) {
            DB::table('articles')
                ->where('team_id', $childTeam->id)
                ->update(['team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$articleCount} articles");

        // Update Conversations
        $convCount = DB::table('conversations')->where('team_id', $childTeam->id)->count();
        if (!$isDryRun && $convCount > 0) {
            DB::table('conversations')
                ->where('team_id', $childTeam->id)
                ->update(['team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$convCount} conversations");

        // Update Terms
        $termCount = DB::table('terms')->where('team_id', $childTeam->id)->count();
        if (!$isDryRun && $termCount > 0) {
            DB::table('terms')
                ->where('team_id', $childTeam->id)
                ->update(['team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$termCount} terms");

        // Update JobStatuses
        $jobCount = DB::table('job_statuses')->where('team_id', $childTeam->id)->count();
        if (!$isDryRun && $jobCount > 0) {
            DB::table('job_statuses')
                ->where('team_id', $childTeam->id)
                ->update(['team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$jobCount} job statuses");

        // Update InvitationTokens
        $inviteCount = DB::table('invitation_tokens')->where('team_id', $childTeam->id)->count();
        if (!$isDryRun && $inviteCount > 0) {
            DB::table('invitation_tokens')
                ->where('team_id', $childTeam->id)
                ->update(['team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$inviteCount} invitation tokens");

        // Step 6: Update users' current_team_id if it points to child team
        $userCount = DB::table('users')->where('current_team_id', $childTeam->id)->count();
        if (!$isDryRun && $userCount > 0) {
            DB::table('users')
                ->where('current_team_id', $childTeam->id)
                ->update(['current_team_id' => $parentTeam->id]);
        }
        $this->info("      ✓ " . ($isDryRun ? "Would update" : "Updated") . " {$userCount} users' current team");

        // Step 7: Delete the child team
        $this->info("    " . ($isDryRun ? "Would delete" : "Deleting") . " child team...");
        $childTeamName = $childTeam->name;
        if (!$isDryRun) {
            $childTeam->delete();
        }
        $this->info("      ✓ " . ($isDryRun ? "Would delete" : "Deleted") . " team: {$childTeamName}");
    }

    /**
     * Validate that all teams exist before proceeding.
     *
     * @return bool
     */
    protected function validateTeams(): bool
    {
        $valid = true;

        foreach ($this->teamsToConsolidate as $parentTeamId => $childTeamIds) {
            $parentTeam = Team::find($parentTeamId);

            if (!$parentTeam) {
                $this->error("Parent team with ID {$parentTeamId} not found!");
                $valid = false;
                continue;
            }

            // Check if parent team has an owned organization
            $parentOwnedOrg = DB::table('organizations')
                ->where('team_id', $parentTeamId)
                ->where('is_competitor', false)
                ->first();

            if (!$parentOwnedOrg) {
                $this->warn("⚠️  Parent team '{$parentTeam->name}' (ID: {$parentTeamId}) does not have an owned organization!");
                $this->warn("   This is unusual but consolidation will proceed.");
            }

            foreach ($childTeamIds as $childTeamId) {
                $childTeam = Team::find($childTeamId);

                if (!$childTeam) {
                    $this->error("Child team with ID {$childTeamId} not found!");
                    $valid = false;
                }

                // Check for circular references
                if ($childTeamId === $parentTeamId) {
                    $this->error("Team {$childTeamId} cannot be its own parent!");
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    /**
     * Show a summary of what will be consolidated.
     */
    protected function showConsolidationSummary(): void
    {
        $this->info("\n📊 Consolidation Summary:");
        $this->info(str_repeat('=', 50));

        foreach ($this->teamsToConsolidate as $parentTeamId => $childTeamIds) {
            $parentTeam = Team::find($parentTeamId);
            $this->info("\n🏢 Parent Team: {$parentTeam->name} (ID: {$parentTeamId})");

            foreach ($childTeamIds as $childTeamId) {
                $childTeam = Team::find($childTeamId);
                $this->info("   └─ Child Team: {$childTeam->name} (ID: {$childTeamId})");

                // Show counts
                $ownedOrgCount = DB::table('organizations')
                    ->where('team_id', $childTeamId)
                    ->where('is_competitor', false)
                    ->count();

                $competitorCount = DB::table('organizations')
                    ->where('team_id', $childTeamId)
                    ->where('is_competitor', true)
                    ->count();

                $counts = [
                    'Campaigns' => Campaign::where('team_id', $childTeamId)->count(),
                    'Users' => $childTeam->users()->count(),
                    'Prompts' => DB::table('prompts')->where('team_id', $childTeamId)->count(),
                    'Owned Organization' => $ownedOrgCount,
                    'Competitor Organizations' => $competitorCount,
                    'Articles' => DB::table('articles')->where('team_id', $childTeamId)->count(),
                    'Terms' => DB::table('terms')->where('team_id', $childTeamId)->count(),
                ];

                foreach ($counts as $type => $count) {
                    if ($count > 0) {
                        $emoji = $type === 'Owned Organization' ? '⚠️ ' : '• ';
                        $this->info("      {$emoji}{$count} {$type}");
                    }
                }

                if ($ownedOrgCount > 0) {
                    $this->warn("      ⚠️  Owned organization will be deleted during consolidation");
                }
            }
        }

        // Show note about visibility metrics
        if ($parentTeam = Team::find(array_key_first($this->teamsToConsolidate))) {
            $parentOwnedOrg = DB::table('organizations')
                ->where('team_id', $parentTeam->id)
                ->where('is_competitor', false)
                ->first();

            if ($parentOwnedOrg) {
                $termCount = DB::table('terms')
                    ->where('organization_id', $parentOwnedOrg->id)
                    ->count();

                if ($termCount > 0) {
                    $this->info("\n📈 Visibility Metrics:");
                    $this->info("   {$termCount} visibility tracking jobs will be queued to recalculate metrics");
                    $this->info("   for the parent team's owned organization across all campaigns.");
                }
            }
        }

        $this->info("\n" . str_repeat('=', 50));
    }
}
