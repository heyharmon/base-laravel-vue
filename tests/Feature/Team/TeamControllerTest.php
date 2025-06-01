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
        ->assertJsonCount(1, 'joinedTeams')
        ->assertJsonCount(1, 'pendingInvitations')
        ->assertJsonPath('ownedTeams.0.id', $ownedTeam->id)
        ->assertJsonPath('joinedTeams.0.id', $joinedTeam->id)
        ->assertJsonPath('pendingInvitations.0.id', $pendingTeam->id);
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
