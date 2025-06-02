<?php

use App\Jobs\RunPromptJob;
use App\Models\Team;
use App\Models\Organization;
use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('attaches found keywords to response and prompt', function () {
    $team = Team::factory()->create();
    $org = Organization::factory()->owned()->for($team)->create();

    $keyword = Keyword::factory()->for($team)->for($org)->create(['name' => 'Acme']);
    $missing = Keyword::factory()->for($team)->for($org)->create(['name' => 'Missing']);

    $prompt = Prompt::factory()->for($team)->create();
    $response = Response::factory()->for($prompt)->create(['content' => 'Acme is great']);

    $job = new RunPromptJob($prompt, [], $team->id);

    $method = new ReflectionMethod(RunPromptJob::class, 'checkForKeywords');
    $method->setAccessible(true);
    $method->invoke($job, $response, $prompt);

    $this->assertDatabaseHas('keyword_prompt', [
        'keyword_id' => $keyword->id,
        'prompt_id' => $prompt->id,
        'count' => 1,
    ]);

    $this->assertDatabaseMissing('keyword_prompt', [
        'keyword_id' => $missing->id,
        'prompt_id' => $prompt->id,
    ]);

    $this->assertDatabaseHas('keyword_response', [
        'keyword_id' => $keyword->id,
        'response_id' => $response->id,
    ]);
});

it('increments existing keyword prompt counts', function () {
    $team = Team::factory()->create();
    $org = Organization::factory()->owned()->for($team)->create();

    $keyword = Keyword::factory()->for($team)->for($org)->create(['name' => 'Acme']);
    $prompt = Prompt::factory()->for($team)->create();
    $prompt->keywords()->attach($keyword->id, ['count' => 1, 'last_found_at' => now()]);

    $response = Response::factory()->for($prompt)->create(['content' => 'ACME again']);

    $job = new RunPromptJob($prompt, [], $team->id);

    $method = new ReflectionMethod(RunPromptJob::class, 'checkForKeywords');
    $method->setAccessible(true);
    $method->invoke($job, $response, $prompt);

    $this->assertDatabaseHas('keyword_prompt', [
        'keyword_id' => $keyword->id,
        'prompt_id' => $prompt->id,
        'count' => 2,
    ]);
});
