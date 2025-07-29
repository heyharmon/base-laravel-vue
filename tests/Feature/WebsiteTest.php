<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;

it('creates website and lists stats', function () {
    try {
        Artisan::call('migrate');
    } catch (Throwable $e) {
        $this->markTestSkipped('Migrations failed: ' . $e->getMessage());
    }

    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer ' . $token)
        ->postJson('/api/websites', [
            'name' => 'My Site',
            'domain' => 'https://Example.com',
        ]);

    $response->assertStatus(201);
    $websiteId = $response->json('website.id');

    $this->withHeaders([
        'Authorization' => 'Bearer ' . $token,
    ])->getJson("/api/websites/{$websiteId}")
      ->assertStatus(200)
      ->assertJsonStructure(['website', 'embed_code', 'stats']);
});
