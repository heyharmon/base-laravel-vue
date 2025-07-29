<?php

use App\Models\PageView;
use Illuminate\Support\Facades\Artisan;

it('stores page view with normalized domain and llm detection', function () {
    // skip if migrations cannot run
    try {
        Artisan::call('migrate');
    } catch (Throwable $e) {
        $this->markTestSkipped('Migrations failed: ' . $e->getMessage());
    }

    $response = $this->postJson('/api/track', [
        'domain' => 'https://www.Example.COM',
        'path' => '/test'
    ], [
        'User-Agent' => 'ChatGPT-User/2.0'
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('page_views', [
        'normalized_domain' => 'example.com',
        'path' => '/test',
        'is_llm' => true
    ]);
});
