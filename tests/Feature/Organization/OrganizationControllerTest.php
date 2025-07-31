<?php

use App\Models\Organization;
use App\Models\Team;
use App\Models\User;
use App\Models\Term;
use App\Models\JobStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('lists organizations for the current team', function () {
	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();

	$campaign = \App\Models\Campaign::factory()->for($team)->create();

	$org1 = Organization::factory()->for($team)->for($campaign)->owned()->create();
	$org2 = Organization::factory()->for($team)->for($campaign)->create();
	$otherOrg = Organization::factory()->create();

	Sanctum::actingAs($user);

	$response = $this->getJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/organizations");

	$response->assertStatus(200)
		->assertJsonCount(2)
		->assertJsonFragment(['id' => $org1->id])
		->assertJsonFragment(['id' => $org2->id])
		->assertJsonMissing(['id' => $otherOrg->id]);
});

it('creates an organization with terms for name and website', function () {
	Bus::fake();

	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	Sanctum::actingAs($user);

	$campaign = \App\Models\Campaign::factory()->for($team)->create();

	$response = $this->postJson("/api/teams/{$team->id}/campaigns/{$campaign->id}/organizations", [
		'name' => 'Acme',
		'website' => 'acme.com',
		'is_competitor' => true,
	]);

	$response->assertStatus(201)
		->assertJson([
			'name' => 'Acme',
			'website' => 'acme.com',
			'team_id' => $team->id,
			'campaign_id' => $campaign->id,
		]);

	$organizationId = $response->json('id');

	expect(Organization::find($organizationId))->not->toBeNull();
	expect(Term::where('organization_id', $organizationId)->count())->toBe(2);
	expect(JobStatus::where('team_id', $team->id)->where('trackable_type', Term::class)->count())->toBe(2);
});

it('shows an organization belonging to the team', function () {
	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	$organization = Organization::factory()->for($team)->create();

	Sanctum::actingAs($user);

	$response = $this->getJson("/api/organizations/{$organization->id}");

	$response->assertStatus(200)
		->assertJson(['id' => $organization->id]);
});

it('returns not found when viewing another team\'s organization', function () {
	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	$otherOrganization = Organization::factory()->create();

	Sanctum::actingAs($user);

	$response = $this->getJson("/api/organizations/{$otherOrganization->id}");

	$response->assertStatus(404);
});

it('updates an organization belonging to the team', function () {
	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	$organization = Organization::factory()->for($team)->create(['name' => 'Old']);

	Sanctum::actingAs($user);

	$response = $this->putJson("/api/organizations/{$organization->id}", [
		'name' => 'Updated',
	]);

	$response->assertStatus(200)
		->assertJson(['name' => 'Updated']);

	expect($organization->refresh()->name)->toBe('Updated');
});

it('deletes a competitor organization', function () {
	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	$organization = Organization::factory()->for($team)->create();

	Sanctum::actingAs($user);

	$response = $this->deleteJson("/api/organizations/{$organization->id}");

	$response->assertStatus(204);

	expect(Organization::find($organization->id))->toBeNull();
});

it('does not delete the default organization', function () {
	$user = User::factory()->create();
	$team = Team::factory()->for($user, 'owner')->create();
	$user->current_team_id = $team->id;
	$user->save();
	$organization = Organization::factory()->for($team)->owned()->create();

	Sanctum::actingAs($user);

	$response = $this->deleteJson("/api/organizations/{$organization->id}");

	$response->assertStatus(422);

	expect(Organization::find($organization->id))->not->toBeNull();
});
