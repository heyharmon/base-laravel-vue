<?php

use App\Models\Prompt;
use App\Models\Team;
use App\Models\User;
use App\Models\JobStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns not found when there are no prompts', function () {
	Bus::fake();

	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

	$response = $this->postJson("/api/teams/{$team->id}/organizations-find-competitors");

	$response->assertStatus(404);
});

it('dispatches competitor jobs for each prompt', function () {
	Bus::fake();

	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

	$prompt1 = Prompt::factory()->for($team)->create();
	$prompt2 = Prompt::factory()->for($team)->create();

	$response = $this->postJson("/api/teams/{$team->id}/organizations-find-competitors");

	$response->assertStatus(200)
		->assertJson([
			'prompts_count' => 2,
			'total_jobs' => 2,
		]);

	expect(JobStatus::where('trackable_type', Prompt::class)->count())->toBe(2);
});
