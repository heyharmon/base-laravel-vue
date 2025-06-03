<?php

use App\Models\JobStatus;
use App\Models\Team;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns job statuses for the authenticated team', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    JobStatus::factory()->count(3)->for($team)->create();
    $otherTeam = Team::factory()->create();
    JobStatus::factory()->count(2)->for($otherTeam)->create();

    $response = $this->getJson('/api/team-jobs');

    $response->assertStatus(200)
        ->assertJsonCount(3)
        ->assertJson(fn ($json) =>
            $json->each(fn ($item) =>
                $item->where('team_id', $team->id)
            )
        );
});

it('limits results to 100 in descending order', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $jobs = JobStatus::factory()->for($team)
        ->count(105)
        ->sequence(fn ($sequence) => ['created_at' => now()->addSeconds($sequence->index)])
        ->create();

    $response = $this->getJson('/api/team-jobs');
    $response->assertStatus(200)
        ->assertJsonCount(100)
        ->assertJsonPath('0.id', $jobs->last()->id);
});

it('cancels pending jobs for the authenticated team', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    JobStatus::factory()->count(2)->for($team)->state(['status' => 'pending'])->create();

    $response = $this->postJson('/api/team-jobs/cancel');

    $response->assertStatus(200);

    expect(JobStatus::where('team_id', $team->id)->where('status', 'cancelled')->count())->toBe(2);
});
