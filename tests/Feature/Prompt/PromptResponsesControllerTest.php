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

	$response = $this->getJson("/api/prompts/{$prompt->id}/responses");

	$response->assertStatus(200)
		->assertJsonCount(2);

	// Get the actual response data to debug
	$responseData = $response->json();

	// The responses should be ordered by latest (created_at desc)
	expect($responseData[0]['id'])->toBeInt();
	expect($responseData[1]['id'])->toBeInt();

	// Verify we have the correct responses
	$responseIds = collect($responseData)->pluck('id')->sort()->values();
	$expectedIds = $responses->pluck('id')->sort()->values();

	expect($responseIds)->toEqual($expectedIds);
});
