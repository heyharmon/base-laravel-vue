<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('registers a user', function () {
    $payload = [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
    ];

    $response = $this->postJson('/api/register', $payload);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'user' => [
                'id',
                'name',
                'email',
            ],
            'token',
        ]);

    $userId = $response->json('user.id');
    $user = User::find($userId);
    
    expect($user)->not->toBeNull();
});

it('issues a token on login', function () {
    $user = User::factory()->create([
        'password' => Hash::make('secret123'),
    ]);
    $team = Team::factory()->for($user, 'owner')->create();
    $user->current_team_id = $team->id;
    $user->save();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'secret123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'user',
            'token',
        ]);
});

it('retrieves the authenticated user', function () {
    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    $user->current_team_id = $team->id;
    $user->save();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/user');

    $response->assertStatus(200)
        ->assertJson([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

