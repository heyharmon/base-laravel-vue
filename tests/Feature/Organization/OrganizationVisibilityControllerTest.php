<?php

use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('calculates visibility for organizations', function () {
    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    Sanctum::actingAs($user);

    $org1 = Organization::factory()->for($team)->owned()->create(['name' => 'Org1']);
    $org2 = Organization::factory()->for($team)->create(['name' => 'Org2']);

    $keyword1 = Keyword::factory()->for($team)->for($org1, 'organization')->create();
    $keyword2 = Keyword::factory()->for($team)->for($org2, 'organization')->create();

    $prompt = Prompt::factory()->for($team)->create();

    $response1 = Response::factory()->for($prompt)->create();
    $response1->keywords()->attach($keyword1->id);

    $response2 = Response::factory()->for($prompt)->create();
    $response2->keywords()->attach($keyword2->id);

    $response = $this->getJson('/api/organization-visibility');

    $response->assertStatus(200)
        ->assertJsonFragment(['id' => $org1->id, 'visibility' => 50.0])
        ->assertJsonFragment(['id' => $org2->id, 'visibility' => 50.0]);
});

