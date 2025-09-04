<?php

use App\Models\Prompt;
use App\Models\Team;
use App\Models\Campaign;
use App\Services\JobDispatcherService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 when no prompts exist', function () {
	$team = Team::factory()->create();
	$campaign = Campaign::factory()->for($team)->create();
	$user = $team->owner;
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

	$mock = Mockery::mock(JobDispatcherService::class);
	$this->app->instance(JobDispatcherService::class, $mock);

	$this->postJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/prompt-run-batch")
		->assertStatus(404)
		->assertJson(['message' => 'No prompts found to run']);
});

it('queues independent jobs for all prompts', function () {
	$team = Team::factory()->create();
	$campaign = Campaign::factory()->for($team)->create();
	$user = $team->owner;
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

	$prompts = Prompt::factory()->count(3)->for($team)->for($campaign)->create();

	$mock = Mockery::mock(JobDispatcherService::class);
	$expectedJobs = $prompts->count() * 2; // count=2 below
	$mock->shouldReceive('dispatch')->times($expectedJobs);
	$this->app->instance(JobDispatcherService::class, $mock);

	$this->postJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/prompt-run-batch", ['count' => 2])
		->assertStatus(200)
		->assertJson([
			'prompts_count' => $prompts->count(),
			'queued_jobs' => $expectedJobs,
		]);
});
