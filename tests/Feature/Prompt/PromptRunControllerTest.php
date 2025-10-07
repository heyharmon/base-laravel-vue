<?php

use App\Jobs\RunPromptJob;
use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dispatches a job to run a prompt', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();

    Bus::fake();

    $this->postJson("/api/prompts/{$prompt->id}/run")
        ->assertStatus(200)
        ->assertJsonPath('prompt.id', $prompt->id)
        ->assertJsonPath('queued_jobs', 1);

    Bus::assertDispatchedTimes(RunPromptJob::class, 1);
});

it('dispatches multiple independent jobs when multiple runs are requested', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();

    Bus::fake();

    $this->postJson("/api/prompts/{$prompt->id}/run", ['count' => 2])
        ->assertStatus(200)
        ->assertJsonPath('prompt.id', $prompt->id)
        ->assertJsonPath('queued_jobs', 2);

    Bus::assertDispatchedTimes(RunPromptJob::class, 2);
});
