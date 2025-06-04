<?php

use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\Term;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('calculates visibility for organizations', function () {
    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    $user->current_team_id = $team->id;
    $user->save();
    Sanctum::actingAs($user);

    $org1 = Organization::factory()->for($team)->owned()->create(['name' => 'Org1']);
    $org2 = Organization::factory()->for($team)->create(['name' => 'Org2']);

    $term1 = Term::factory()->for($team)->for($org1, 'organization')->create();
    $term2 = Term::factory()->for($team)->for($org2, 'organization')->create();

    $prompt = Prompt::factory()->for($team)->create();

    $response1 = Response::factory()->for($prompt)->create();
    $response1->terms()->attach($term1->id);

    $response2 = Response::factory()->for($prompt)->create();
    $response2->terms()->attach($term2->id);

    $response = $this->getJson('/api/organization-visibility');

    $response->assertStatus(200)
        ->assertJsonFragment(['id' => $org1->id, 'visibility' => 50.0])
        ->assertJsonFragment(['id' => $org2->id, 'visibility' => 50.0]);
});

