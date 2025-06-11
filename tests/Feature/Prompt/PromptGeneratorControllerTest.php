<?php

use App\Models\Team;
use App\Models\Organization;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires terms to generate prompts', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    $user->current_team_id = $team->id;
    $user->save();
    Sanctum::actingAs($user);
    
    $organization = Organization::factory()->for($team)->create();

    $this->postJson("/api/organizations/{$organization->id}/generate-prompts", [])
        ->assertStatus(422);
});
