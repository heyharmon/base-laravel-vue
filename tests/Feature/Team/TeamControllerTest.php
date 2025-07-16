<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('creates a team and sets current team for the owner', function () {
	$user = User::factory()->create();
	Sanctum::actingAs($user);

	$response = $this->postJson('/api/teams', [
		'name' => 'My Team',
	]);

	$response->assertStatus(201)
		->assertJson([
			'name' => 'My Team',
			'owner_id' => $user->id,
		]);

	$teamId = $response->json('id');
	$team = Team::find($teamId);

	expect($team)->not->toBeNull();
	expect($team->users()->where('user_id', $user->id)->where('role', 'admin')->where('invitation_accepted', true)->exists())->toBeTrue();
	expect($user->refresh()->current_team_id)->toBe($teamId);
});

it('lists owned, joined and pending teams for a user', function () {
	$user = User::factory()->create();
	Sanctum::actingAs($user);

	$ownedTeam = Team::factory()->for($user, 'owner')->create(['name' => 'Owned Team']);

	$joinedTeam = Team::factory()->create();
	$joinedTeam->users()->attach($user->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	$pendingTeam = Team::factory()->create();
	$pendingTeam->users()->attach($user->id, [
		'role' => 'member',
		'invitation_accepted' => false,
		'invitation_sent_at' => now(),
	]);

	$response = $this->getJson('/api/teams');

	$response->assertStatus(200)
		->assertJsonCount(1, 'ownedTeams')
		->assertJsonCount(2, 'joinedTeams')
		->assertJsonCount(1, 'pendingInvitations')
		->assertJsonPath('ownedTeams.0.id', $ownedTeam->id)
		->assertJsonPath('pendingInvitations.0.id', $pendingTeam->id);

	// Verify that joinedTeams contains the joinedTeam
	$joinedTeamIds = collect($response->json('joinedTeams'))->pluck('id')->toArray();
	expect($joinedTeamIds)->toContain($joinedTeam->id);
});

it('invites an existing user to a team', function () {
	$owner = User::factory()->create();
	Sanctum::actingAs($owner);
	$team = Team::factory()->for($owner, 'owner')->create();
	$invitee = User::factory()->create();

	$response = $this->postJson("/api/teams/{$team->id}/invite", [
		'email' => $invitee->email,
		'role' => 'member',
	]);

	$response->assertStatus(200)
		->assertJson(['message' => 'Invitation sent successfully']);

	$this->assertDatabaseHas('team_user', [
		'team_id' => $team->id,
		'user_id' => $invitee->id,
		'role' => 'member',
		'invitation_accepted' => false,
	]);
});

it('allows a user to accept a team invitation', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();
	$invitee = User::factory()->create();
	$team->users()->attach($invitee->id, [
		'role' => 'member',
		'invitation_accepted' => false,
		'invitation_sent_at' => now(),
	]);

	Sanctum::actingAs($invitee);

	$response = $this->postJson("/api/teams/{$team->id}/accept-invitation");

	$response->assertStatus(200)
		->assertJson(['message' => 'You have joined the team']);

	$this->assertDatabaseHas('team_user', [
		'team_id' => $team->id,
		'user_id' => $invitee->id,
		'invitation_accepted' => true,
	]);
});

it('switches the current team for a member', function () {
	$user = User::factory()->create();
	$team = Team::factory()->create();
	$team->users()->attach($user->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	Sanctum::actingAs($user);

	$response = $this->postJson("/api/teams/{$team->id}/switch");

	$response->assertStatus(200)
		->assertJson(['message' => 'Current team updated successfully']);

	expect($user->refresh()->current_team_id)->toBe($team->id);
});

it('updates a member\'s role when requested by an admin', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$member = User::factory()->create();
	$team->users()->attach($member->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	$admin = User::factory()->create();
	$team->users()->attach($admin->id, [
		'role' => 'admin',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	Sanctum::actingAs($admin);

	$response = $this->putJson("/api/teams/{$team->id}/members/{$member->id}/role", [
		'role' => 'admin',
	]);

	$response->assertStatus(200)
		->assertJson(['message' => 'Member role updated successfully']);

	$this->assertDatabaseHas('team_user', [
		'team_id' => $team->id,
		'user_id' => $member->id,
		'role' => 'admin',
	]);
});

it('removes a member from a team', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$member = User::factory()->create();
	$team->users()->attach($member->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	Sanctum::actingAs($owner);

	$response = $this->deleteJson("/api/teams/{$team->id}/members/{$member->id}");

	$response->assertStatus(200)
		->assertJson(['message' => 'Member removed successfully']);

	$this->assertDatabaseMissing('team_user', [
		'team_id' => $team->id,
		'user_id' => $member->id,
	]);
});

it('removes invitation token when canceling invitation', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	// Create a user with pending invitation
	$invitee = User::factory()->create();
	$team->users()->attach($invitee->id, [
		'role' => 'member',
		'invitation_accepted' => false,
		'invitation_sent_at' => now(),
	]);

	// Create an invitation token
	$token = \App\Models\InvitationToken::create([
		'email' => $invitee->email,
		'token' => 'test-token',
		'team_id' => $team->id,
		'expires_at' => now()->addDays(7),
	]);

	Sanctum::actingAs($owner);

	$response = $this->deleteJson("/api/teams/{$team->id}/members/{$invitee->id}");

	$response->assertStatus(200)
		->assertJson(['message' => 'Member removed successfully']);

	// Verify team_user record is removed
	$this->assertDatabaseMissing('team_user', [
		'team_id' => $team->id,
		'user_id' => $invitee->id,
	]);

	// Verify invitation token is removed
	$this->assertDatabaseMissing('invitation_tokens', [
		'email' => $invitee->email,
		'team_id' => $team->id,
	]);
});

it('deletes user when canceling invitation if user has no other team memberships', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	// Create a user with only this pending invitation
	$invitee = User::factory()->create();
	$team->users()->attach($invitee->id, [
		'role' => 'member',
		'invitation_accepted' => false,
		'invitation_sent_at' => now(),
	]);

	Sanctum::actingAs($owner);

	$response = $this->deleteJson("/api/teams/{$team->id}/members/{$invitee->id}");

	$response->assertStatus(200)
		->assertJson(['message' => 'Member removed successfully']);

	// Verify user record is deleted
	$this->assertDatabaseMissing('users', [
		'id' => $invitee->id,
	]);
});

it('does not delete user when canceling invitation if user has other team memberships', function () {
	$owner = User::factory()->create();
	$team1 = Team::factory()->for($owner, 'owner')->create();
	$team2 = Team::factory()->create();

	// Create a user with membership in another team
	$invitee = User::factory()->create();

	// Add to first team as pending invitation
	$team1->users()->attach($invitee->id, [
		'role' => 'member',
		'invitation_accepted' => false,
		'invitation_sent_at' => now(),
	]);

	// Add to second team as accepted member
	$team2->users()->attach($invitee->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	Sanctum::actingAs($owner);

	$response = $this->deleteJson("/api/teams/{$team1->id}/members/{$invitee->id}");

	$response->assertStatus(200)
		->assertJson(['message' => 'Member removed successfully']);

	// Verify user record still exists
	$this->assertDatabaseHas('users', [
		'id' => $invitee->id,
	]);

	// Verify they're still in the other team
	$this->assertDatabaseHas('team_user', [
		'team_id' => $team2->id,
		'user_id' => $invitee->id,
	]);
});

it('allows a user to decline a team invitation', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();
	$invitee = User::factory()->create();

	$team->users()->attach($invitee->id, [
		'role' => 'member',
		'invitation_accepted' => false,
		'invitation_sent_at' => now(),
	]);

	// Create an invitation token
	\App\Models\InvitationToken::create([
		'email' => $invitee->email,
		'token' => 'test-token',
		'team_id' => $team->id,
		'expires_at' => now()->addDays(7),
	]);

	Sanctum::actingAs($invitee);

	$response = $this->postJson("/api/teams/{$team->id}/decline-invitation");

	$response->assertStatus(200)
		->assertJson(['message' => 'Invitation declined']);

	// Verify team_user record is removed
	$this->assertDatabaseMissing('team_user', [
		'team_id' => $team->id,
		'user_id' => $invitee->id,
	]);

	// Verify invitation token is removed
	$this->assertDatabaseMissing('invitation_tokens', [
		'email' => $invitee->email,
		'team_id' => $team->id,
	]);

	// Verify user record still exists (authenticated users should not be deleted)
	$this->assertDatabaseHas('users', [
		'id' => $invitee->id,
	]);
});

it('sets invitation URL to null when token is expired', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	// Create a user with pending invitation and an expired token
	$invitee = User::factory()->create();
	$team->users()->attach($invitee->id, [
		'role' => 'member',
		'invitation_accepted' => false,
		'invitation_sent_at' => now(),
	]);

	// Create an expired token
	\App\Models\InvitationToken::create([
		'email' => $invitee->email,
		'token' => 'expired-token',
		'team_id' => $team->id,
		'expires_at' => now()->subDays(1), // Expired
	]);

	Sanctum::actingAs($owner);

	$response = $this->getJson("/api/teams/{$team->id}");

	$response->assertStatus(200);

	$pendingInvitations = $response->json('pendingInvitations');
	expect($pendingInvitations)->toHaveCount(1);

	$invitation = $pendingInvitations[0];

	// Verify invitation with expired token has null URL and correct expiration info
	expect($invitation['invitation_url'])->toBeNull();
	expect($invitation['token_expired'])->toBeTrue();
	expect($invitation['token_expires_at'])->not->toBeNull();
});
