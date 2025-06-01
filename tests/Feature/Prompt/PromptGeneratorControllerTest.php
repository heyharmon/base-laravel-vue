<?php

use App\Models\Team;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires a domain to generate prompts', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $this->postJson('/api/generate-prompts', [])
        ->assertStatus(422);
});
