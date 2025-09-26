<?php

use App\Models\Article;
use App\Models\Campaign;
use App\Models\Organization;
use App\Models\Prompt;
use App\Models\Response;
use App\Models\Team;
use App\Models\TeamUsageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('retains response usage after prompt deletion', function () {
    $team = Team::factory()->create([
        'responses_limit' => 5,
    ]);

    $prompt = Prompt::factory()->for($team)->create();

    $response = Response::factory()->for($prompt)->create([
        'status' => 'completed',
    ]);

    expect(TeamUsageEvent::where('team_id', $team->id)
        ->where('resource_type', TeamUsageEvent::TYPE_RESPONSE)
        ->where('resource_id', $response->id)
        ->exists())->toBeTrue();

    expect($team->fresh()->responsesRemaining())->toBe(4);

    $prompt->delete();

    expect(TeamUsageEvent::where('resource_type', TeamUsageEvent::TYPE_RESPONSE)
        ->where('resource_id', $response->id)
        ->count())->toBe(1);

    expect($team->fresh()->responsesRemaining())->toBe(4);
});

it('retains article usage after article deletion', function () {
    $team = Team::factory()->create([
        'articles_limit' => 3,
    ]);

    $campaign = Campaign::factory()->for($team)->create();
    $organization = Organization::factory()->for($team)->create([
        'is_competitor' => false,
    ]);

    $article = $team->articles()->create([
        'campaign_id' => $campaign->id,
        'organization_id' => $organization->id,
        'prompt_id' => null,
        'current_version' => 1,
        'title' => 'Example Article',
        'meta_title' => null,
        'meta_description' => null,
        'schema' => null,
        'outline' => null,
        'content' => 'Body',
        'perplexity_checks' => 0,
    ]);

    expect(TeamUsageEvent::where('team_id', $team->id)
        ->where('resource_type', TeamUsageEvent::TYPE_ARTICLE)
        ->where('resource_id', $article->id)
        ->exists())->toBeTrue();

    expect($team->fresh()->articlesRemaining())->toBe(2);

    $article->delete();

    expect(TeamUsageEvent::where('resource_type', TeamUsageEvent::TYPE_ARTICLE)
        ->where('resource_id', $article->id)
        ->count())->toBe(1);

    expect($team->fresh()->articlesRemaining())->toBe(2);
});
