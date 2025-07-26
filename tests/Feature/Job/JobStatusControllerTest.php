<?php

use App\Models\JobStatus;
use App\Models\Team;
use App\Models\Campaign;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns job statuses for the authenticated team', function () {
	$team = Team::factory()->create();
	$user = $team->owner;
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

        $campaign = Campaign::factory()->for($team)->create();
        JobStatus::factory()->count(3)->for($team)->for($campaign)->create();
        $otherTeam = Team::factory()->create();
        $otherCampaign = Campaign::factory()->for($otherTeam)->create();
        JobStatus::factory()->count(2)->for($otherTeam)->for($otherCampaign)->create();

	$response = $this->getJson("/api/teams/{$team->id}/jobs");

	$response->assertStatus(200)
		->assertJsonCount(3)
		->assertJsonPath('0.team_id', $team->id)
		->assertJsonPath('1.team_id', $team->id)
		->assertJsonPath('2.team_id', $team->id);
});

it('limits results to 100 in descending order', function () {
	$team = Team::factory()->create();
	$user = $team->owner;
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

        $campaign = Campaign::factory()->for($team)->create();
        $jobs = JobStatus::factory()->for($team)->for($campaign)
                ->count(105)
                ->sequence(fn($sequence) => ['created_at' => now()->addSeconds($sequence->index)])
                ->create();

	$response = $this->getJson("/api/teams/{$team->id}/jobs");
	$response->assertStatus(200)
		->assertJsonCount(100)
		->assertJsonPath('0.id', $jobs->last()->id);
});

it('cancels pending jobs for the authenticated team', function () {
	$team = Team::factory()->create();
	$user = $team->owner;
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

        $campaign = Campaign::factory()->for($team)->create();
        JobStatus::factory()->count(2)->for($team)->for($campaign)->state(['status' => 'pending'])->create();

	$response = $this->postJson("/api/teams/{$team->id}/jobs/cancel");

	$response->assertStatus(200);

	expect(JobStatus::where('team_id', $team->id)->where('status', 'cancelled')->count())->toBe(2);
});
