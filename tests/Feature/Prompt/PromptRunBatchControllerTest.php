<?php

use App\Models\Prompt;
use App\Models\Team;
use App\Services\JobDispatcherService;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns 404 when no prompts exist', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $mock = Mockery::mock(JobDispatcherService::class);
    $this->app->instance(JobDispatcherService::class, $mock);

    $this->postJson('/api/prompt-run-batch')
        ->assertStatus(404)
        ->assertJson(['message' => 'No prompts found to run']);
});

it('dispatches a job to run all prompts', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompts = Prompt::factory()->count(3)->for($team)->create();

    $mock = Mockery::mock(JobDispatcherService::class);
    $mock->shouldReceive('dispatch')->once();
    $this->app->instance(JobDispatcherService::class, $mock);

    $this->postJson('/api/prompt-run-batch', ['count' => 2])
        ->assertStatus(200)
        ->assertJson([
            'prompts_count' => $prompts->count(),
            'expected_jobs' => $prompts->count() * 2,
        ]);
});
