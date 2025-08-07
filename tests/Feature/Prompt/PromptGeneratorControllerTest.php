<?php

use App\Models\Team;
use App\Models\Campaign;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires terms to generate prompts', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    $user->current_team_id = $team->id;
    $user->save();
    Sanctum::actingAs($user);

    $campaign = Campaign::factory()->for($team)->create([
        'keywords' => null
    ]);

    $this->postJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/generate-prompts", [])
        ->assertStatus(422);
});
