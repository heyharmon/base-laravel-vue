<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Team;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Term;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SeedSampleData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sample:seed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed realistic sample data spanning two years for demo purposes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        DB::transaction(function () {
            $this->info('Finding existing users...');

            $user1 = User::find(1);
            $user2 = User::find(2);

            if (!$user1 || !$user2) {
                $this->error('Users with id 1 and 2 must exist before running this command.');
                return Command::FAILURE;
            }

            $this->info('Creating team and campaign...');

            $team = Team::firstOrCreate(
                ['name' => 'Demo Team'],
                ['owner_id' => $user1->id]
            );

            // Add both users to the team
            $team->users()->syncWithoutDetaching([
                $user1->id => [
                    'role' => 'owner',
                    'invitation_accepted' => true,
                    'invitation_sent_at' => now(),
                    'joined_at' => now(),
                ],
                $user2->id => [
                    'role' => 'member',
                    'invitation_accepted' => true,
                    'invitation_sent_at' => now(),
                    'joined_at' => now(),
                ],
            ]);

            // Set current team for both users
            $user1->update(['current_team_id' => $team->id]);
            $user2->update(['current_team_id' => $team->id]);

            $campaign = Campaign::firstOrCreate(
                [
                    'team_id' => $team->id,
                    'name' => 'Demo Campaign',
                ],
                [
                    'description' => 'Sample campaign for demo data',
                    'is_default' => true,
                ]
            );

            $this->info('Creating organizations and terms...');

            $organizations = collect();
            $owned = Organization::factory()->owned()->create([
                'team_id' => $team->id,
            ]);
            $organizations->push($owned);
            for ($i = 0; $i < 2; $i++) {
                $organizations->push(
                    Organization::factory()->create([
                        'team_id' => $team->id,
                        'campaign_id' => $campaign->id,
                        'is_competitor' => true,
                    ])
                );
            }

            $termsByOrg = [];
            foreach ($organizations as $org) {
                $termsByOrg[$org->id] = Term::factory()->count(2)->create([
                    'team_id' => $team->id,
                    'organization_id' => $org->id,
                ]);
            }

            $this->info('Creating prompts, responses and analytics...');

            $prompts = Prompt::factory()->count(3)->create([
                'team_id' => $team->id,
                'campaign_id' => $campaign->id,
            ]);

            $termPromptData = [];
            $allTerms = collect($termsByOrg)->flatten();
            $allResponses = collect();

            foreach ($prompts as $prompt) {
                for ($i = 0; $i < 250; $i++) {
                    $date = Carbon::now()->subDays(rand(0, 730));

                    $response = Response::factory()->create([
                        'prompt_id' => $prompt->id,
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    $allResponses->push($response);

                    $selectedTerms = $allTerms->filter(fn($term) => rand(0, 1));

                    if ($selectedTerms->isEmpty()) {
                        continue;
                    }

                    $attach = [];
                    foreach ($selectedTerms as $term) {
                        $attach[$term->id] = [
                            'created_at' => $date,
                            'updated_at' => $date,
                        ];

                        if (!isset($termPromptData[$term->id][$prompt->id])) {
                            $termPromptData[$term->id][$prompt->id] = [
                                'count' => 0,
                                'last_found_at' => $date,
                            ];
                        }

                        $termPromptData[$term->id][$prompt->id]['count']++;
                        if ($date->gt($termPromptData[$term->id][$prompt->id]['last_found_at'])) {
                            $termPromptData[$term->id][$prompt->id]['last_found_at'] = $date;
                        }
                    }

                    $response->terms()->attach($attach);
                }
            }

            // Ensure each term appears at least once
            foreach ($allTerms as $term) {
                if (!isset($termPromptData[$term->id])) {
                    $response = $allResponses->random();
                    $date = Carbon::now()->subDays(rand(0, 730));

                    $response->terms()->attach($term->id, [
                        'created_at' => $date,
                        'updated_at' => $date,
                    ]);

                    $termPromptData[$term->id][$response->prompt_id] = [
                        'count' => 1,
                        'last_found_at' => $date,
                    ];
                }
            }

            foreach ($termPromptData as $termId => $promptData) {
                $term = Term::find($termId);
                foreach ($promptData as $promptId => $data) {
                    $term->prompts()->syncWithoutDetaching([
                        $promptId => [
                            'count' => $data['count'],
                            'last_found_at' => $data['last_found_at'],
                            'created_at' => $data['last_found_at'],
                            'updated_at' => $data['last_found_at'],
                        ],
                    ]);
                }
            }
        });

        $this->info('Sample data created.');
        $this->info('Users with id 1 and 2 have been added to the Demo Team.');

        return Command::SUCCESS;
    }
}
