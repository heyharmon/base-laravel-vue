<?php

use App\Services\BrandFetchService;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns search results from the brand fetch service', function () {
    $mock = Mockery::mock(BrandFetchService::class);
    $mock->shouldReceive('searchBrands')->with('acme')->once()->andReturn([
        ['name' => 'Acme Inc'],
    ]);
    $this->app->instance(BrandFetchService::class, $mock);

    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/organization-search?query=acme');

    $response->assertStatus(200)
        ->assertJson(['results' => [['name' => 'Acme Inc']]]);
});

it('returns empty results when the query is missing', function () {
    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/organization-search');

    $response->assertStatus(200)
        ->assertJson(['results' => []]);
});

it('returns brand details using the brand fetch service', function () {
    $mock = Mockery::mock(BrandFetchService::class);
    $mock->shouldReceive('getBrandDetails')->with('acme.com')->once()->andReturn([
        'domain' => 'acme.com',
    ]);
    $this->app->instance(BrandFetchService::class, $mock);

    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/brand-details?identifier=acme.com');

    $response->assertStatus(200)
        ->assertJson(['details' => ['domain' => 'acme.com']]);
});

it('requires identifier when requesting brand details', function () {
    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/brand-details');

    $response->assertStatus(422);
});

