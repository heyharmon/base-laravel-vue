<?php

use App\Jobs\RunPromptJob;
use App\Models\Team;
use App\Models\Organization;
use App\Models\Term;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('attaches found terms to response and prompt', function () {
    $team = Team::factory()->create();
    $org = Organization::factory()->owned()->for($team)->create();

    $term = Term::factory()->for($team)->for($org)->create(['name' => 'Acme']);
    $missing = Term::factory()->for($team)->for($org)->create(['name' => 'Missing']);

    $prompt = Prompt::factory()->for($team)->create();
    $response = Response::factory()->for($prompt)->create(['content' => 'Acme is great']);

    $job = new RunPromptJob($prompt, [], $team->id);

    $method = new ReflectionMethod(RunPromptJob::class, 'checkForTerms');
    $method->setAccessible(true);
    $method->invoke($job, $response, $prompt);

    $this->assertDatabaseHas('term_prompt', [
        'term_id' => $term->id,
        'prompt_id' => $prompt->id,
        'count' => 1,
    ]);

    $this->assertDatabaseMissing('term_prompt', [
        'term_id' => $missing->id,
        'prompt_id' => $prompt->id,
    ]);

    $this->assertDatabaseHas('term_response', [
        'term_id' => $term->id,
        'response_id' => $response->id,
    ]);
});

it('increments existing term prompt counts', function () {
    $team = Team::factory()->create();
    $org = Organization::factory()->owned()->for($team)->create();

    $term = Term::factory()->for($team)->for($org)->create(['name' => 'Acme']);
    $prompt = Prompt::factory()->for($team)->create();
    $prompt->terms()->attach($term->id, ['count' => 1, 'last_found_at' => now()]);

    $response = Response::factory()->for($prompt)->create(['content' => 'ACME again']);

    $job = new RunPromptJob($prompt, [], $team->id);

    $method = new ReflectionMethod(RunPromptJob::class, 'checkForTerms');
    $method->setAccessible(true);
    $method->invoke($job, $response, $prompt);

    $this->assertDatabaseHas('term_prompt', [
        'term_id' => $term->id,
        'prompt_id' => $prompt->id,
        'count' => 2,
    ]);
});
