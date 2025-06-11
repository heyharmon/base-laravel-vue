<?php

use App\Models\Prompt;
use App\Models\Team;
use Laravel\Sanctum\Sanctum;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lists prompts for the current team', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompts = Prompt::factory()->count(2)->for($team)->create();
    Prompt::factory()->create(); // other team

    $response = $this->getJson('/api/prompts');

    $response->assertStatus(200)
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $prompts[0]->id)
        ->assertJsonPath('1.id', $prompts[1]->id);
});

it('shows a prompt belonging to the team', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();

    $this->getJson("/api/prompts/{$prompt->id}")
        ->assertStatus(200)
        ->assertJson(['id' => $prompt->id, 'content' => $prompt->content]);
});

it('returns 404 when showing a prompt from another team', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->create();

    $this->getJson("/api/prompts/{$prompt->id}")->assertStatus(404);
});

it('creates a prompt', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $payload = ['content' => 'Example prompt', 'name' => 'Prompt'];

    $this->postJson('/api/prompts', $payload)
        ->assertStatus(201)
        ->assertJson(['content' => 'Example prompt', 'team_id' => $team->id]);

    $this->assertDatabaseHas('prompts', ['content' => 'Example prompt', 'team_id' => $team->id]);
});

it('updates a prompt', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();

    $this->putJson("/api/prompts/{$prompt->id}", ['content' => 'Updated'])
        ->assertStatus(200)
        ->assertJson(['id' => $prompt->id, 'content' => 'Updated']);

    $this->assertDatabaseHas('prompts', ['id' => $prompt->id, 'content' => 'Updated']);
});

it('deletes a prompt', function () {
    $team = Team::factory()->create();
    $user = $team->owner;
    Sanctum::actingAs($user);

    $prompt = Prompt::factory()->for($team)->create();

    $this->deleteJson("/api/prompts/{$prompt->id}")
        ->assertStatus(204);

    $this->assertDatabaseMissing('prompts', ['id' => $prompt->id]);
});
