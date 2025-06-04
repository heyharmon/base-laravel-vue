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

it('onboards a new organization and dispatches a generate phrases job', function () {
    Bus::fake();

    $user = User::factory()->create();
    $team = Team::factory()->for($user, 'owner')->create();
    $user->current_team_id = $team->id;
    $user->save();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/organizations-onboard', [
        'name' => 'Acme',
        'website' => 'acme.com',
    ]);

    $response->assertStatus(201)
        ->assertJson(['name' => 'Acme']);

    $organizationId = $response->json('id');

    expect(Organization::find($organizationId))->not->toBeNull();
    expect(Term::where('organization_id', $organizationId)->count())->toBe(2);
    expect(JobStatus::where('trackable_type', Organization::class)->count())->toBe(1);
});

