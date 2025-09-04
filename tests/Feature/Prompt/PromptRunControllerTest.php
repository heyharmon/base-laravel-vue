<?php

use App\Models\Prompt;
use App\Models\Team;
use App\Services\JobDispatcherService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dispatches a job to run a prompt', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();

    $mock = Mockery::mock(JobDispatcherService::class);
    $mock->shouldReceive('dispatch')->once()->andReturn(['id' => 'job']);
    $this->app->instance(JobDispatcherService::class, $mock);

    $this->postJson("/api/prompts/{$prompt->id}/run")
        ->assertStatus(200)
        ->assertJsonPath('prompt.id', $prompt->id)
        ->assertJsonPath('job_statuses.0.id', 'job');
});

it('dispatches multiple independent jobs when multiple runs are requested', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();

    $mock = Mockery::mock(JobDispatcherService::class);
    $mock->shouldReceive('dispatch')->times(2)->andReturn(['id' => 'job']);
    $this->app->instance(JobDispatcherService::class, $mock);

    $this->postJson("/api/prompts/{$prompt->id}/run", ['count' => 2])
        ->assertStatus(200)
        ->assertJsonPath('prompt.id', $prompt->id)
        ->assertJson(fn (\Illuminate\Testing\Fluent\AssertableJson $json) =>
            $json->has('job_statuses', 2)->etc()
        );
});
