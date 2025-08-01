<?php

use App\Models\JobStatus;
use App\Models\Team;
use App\Models\Campaign;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns job statuses for the authenticated team', function () {
        $team = Team::factory()->create();
        $campaign = Campaign::factory()->for($team)->create();
        $user = $team->owner;
        $user->current_team_id = $team->id;
        $user->save();
        Sanctum::actingAs($user);

        JobStatus::factory()->count(3)->for($team)->state(['campaign_id' => $campaign->id])->create();
        $otherTeam = Team::factory()->create();
        JobStatus::factory()->count(2)->for($otherTeam)->create();

        $response = $this->getJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/jobs");

	$response->assertStatus(200)
                ->assertJsonCount(3)
                ->assertJsonPath('0.campaign_id', $campaign->id)
                ->assertJsonPath('1.campaign_id', $campaign->id)
                ->assertJsonPath('2.campaign_id', $campaign->id);
});

it('limits results to 150 in descending order', function () {
        $team = Team::factory()->create();
        $campaign = Campaign::factory()->for($team)->create();
        $user = $team->owner;
        $user->current_team_id = $team->id;
        $user->save();
        Sanctum::actingAs($user);

        $jobs = JobStatus::factory()->for($team)
                ->state(['campaign_id' => $campaign->id])
                ->count(155)
                ->sequence(fn($sequence) => ['created_at' => now()->addSeconds($sequence->index)])
                ->create();

        $response = $this->getJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/jobs");
        $response->assertStatus(200)
                ->assertJsonCount(150)
                ->assertJsonPath('0.id', $jobs->last()->id);
});

it('cancels pending jobs for the authenticated team and campaign', function () {
        $team = Team::factory()->create();
        $campaign = Campaign::factory()->for($team)->create();
        $user = $team->owner;
        $user->current_team_id = $team->id;
        $user->save();
        Sanctum::actingAs($user);

        JobStatus::factory()->count(2)->for($team)->state(['status' => 'pending', 'campaign_id' => $campaign->id])->create();

        $response = $this->postJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/jobs/cancel");

        $response->assertStatus(200);

        expect(JobStatus::where('team_id', $team->id)->where('campaign_id', $campaign->id)->where('status', 'cancelled')->count())->toBe(2);
});
