<?php

use App\Models\Prompt;
use App\Models\Response;
use App\Models\Team;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists responses for a prompt', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();
    $responses = Response::factory()->count(2)->for($prompt)->create();

    $this->getJson("/api/prompts/{$prompt->id}/responses")
        ->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $responses[0]->id)
        ->assertJsonPath('1.id', $responses[1]->id);
});
