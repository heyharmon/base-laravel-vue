<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('generates a password reset URL for a team member when requested by owner', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$member = User::factory()->create();
	$team->users()->attach($member->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	Sanctum::actingAs($owner);

	$response = $this->postJson("/api/teams/{$team->id}/members/{$member->id}/password-reset");

	$response->assertStatus(200)
		->assertJsonStructure([
			'reset_url',
			'expires_at'
		]);

	// Verify the reset URL contains the expected parameters
	$resetUrl = $response->json('reset_url');
	expect($resetUrl)->toContain('/reset-password');
	expect($resetUrl)->toContain('token=');
	expect($resetUrl)->toContain('email=' . urlencode($member->email));

	// Verify a token was created in the database
	$this->assertDatabaseHas('password_reset_tokens', [
		'email' => $member->email,
	]);
});

it('generates a password reset URL for a team member when requested by admin', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$admin = User::factory()->create();
	$team->users()->attach($admin->id, [
		'role' => 'admin',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	$member = User::factory()->create();
	$team->users()->attach($member->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	Sanctum::actingAs($admin);

	$response = $this->postJson("/api/teams/{$team->id}/members/{$member->id}/password-reset");

	$response->assertStatus(200)
		->assertJsonStructure([
			'reset_url',
			'expires_at'
		]);

	// Verify a token was created in the database
	$this->assertDatabaseHas('password_reset_tokens', [
		'email' => $member->email,
	]);
});

it('prevents regular members from generating password reset URLs', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$member1 = User::factory()->create();
	$team->users()->attach($member1->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	$member2 = User::factory()->create();
	$team->users()->attach($member2->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	Sanctum::actingAs($member1);

	$response = $this->postJson("/api/teams/{$team->id}/members/{$member2->id}/password-reset");

	$response->assertStatus(403)
		->assertJson(['message' => 'Unauthorized']);

	// Verify no token was created
	$this->assertDatabaseMissing('password_reset_tokens', [
		'email' => $member2->email,
	]);
});

it('prevents generating password reset URLs for non-team members', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$nonMember = User::factory()->create();

	Sanctum::actingAs($owner);

	$response = $this->postJson("/api/teams/{$team->id}/members/{$nonMember->id}/password-reset");

	$response->assertStatus(404)
		->assertJson(['message' => 'User is not a member of this team']);

	// Verify no token was created
	$this->assertDatabaseMissing('password_reset_tokens', [
		'email' => $nonMember->email,
	]);
});

it('updates existing password reset token when generating a new one', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$member = User::factory()->create();
	$team->users()->attach($member->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	// Create an existing token
	DB::table('password_reset_tokens')->insert([
		'email' => $member->email,
		'token' => 'old-token',
		'created_at' => now()->subHours(2),
	]);

	Sanctum::actingAs($owner);

	$response = $this->postJson("/api/teams/{$team->id}/members/{$member->id}/password-reset");

	$response->assertStatus(200);

	// Verify the old token was replaced
	$tokenRecord = DB::table('password_reset_tokens')
		->where('email', $member->email)
		->first();

	expect($tokenRecord->token)->not->toBe('old-token');
	expect($tokenRecord->created_at)->toBeGreaterThan(now()->subMinutes(1)->toDateTimeString());
});

it('allows owner to generate their own password reset URL', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	Sanctum::actingAs($owner);

	$response = $this->postJson("/api/teams/{$team->id}/members/{$owner->id}/password-reset");

	$response->assertStatus(200)
		->assertJsonStructure([
			'reset_url',
			'expires_at'
		]);

	// Verify a token was created in the database
	$this->assertDatabaseHas('password_reset_tokens', [
		'email' => $owner->email,
	]);
});

it('requires authentication to generate password reset URLs', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	$member = User::factory()->create();
	$team->users()->attach($member->id, [
		'role' => 'member',
		'invitation_accepted' => true,
		'joined_at' => now(),
	]);

	$response = $this->postJson("/api/teams/{$team->id}/members/{$member->id}/password-reset");

	$response->assertStatus(401);
});

it('returns 404 for non-existent team', function () {
	$user = User::factory()->create();
	$member = User::factory()->create();

	Sanctum::actingAs($user);

	$response = $this->postJson("/api/teams/999/members/{$member->id}/password-reset");

	$response->assertStatus(404);
});

it('returns 404 for non-existent user', function () {
	$owner = User::factory()->create();
	$team = Team::factory()->for($owner, 'owner')->create();

	Sanctum::actingAs($owner);

	$response = $this->postJson("/api/teams/{$team->id}/members/999/password-reset");

	$response->assertStatus(404);
});
