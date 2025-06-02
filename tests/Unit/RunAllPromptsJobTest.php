<?php

use App\Jobs\RunAllPromptsJob;
use App\Jobs\RunPromptJob;
use App\Models\Prompt;
use App\Models\Team;
use App\Services\JobDispatcherService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('dispatches a batch of prompt jobs', function () {
    $team = Team::factory()->create();
    $prompts = Prompt::factory()->count(2)->for($team)->create();

    $mock = Mockery::mock(JobDispatcherService::class);
    $mock->shouldReceive('dispatchBatch')
        ->once()
        ->with(
            Mockery::on(fn ($models) => $models->pluck('id')->sort()->values()->all() === $prompts->pluck('id')->sort()->values()->all()),
            Mockery::on(fn ($jobs) => count($jobs) === $prompts->count() * 2 && collect($jobs)->every(fn ($job) => $job instanceof RunPromptJob)),
            Mockery::type('array')
        )
        ->andReturn((object) ['id' => 'batch']);
    $this->app->instance(JobDispatcherService::class, $mock);

    $job = new RunAllPromptsJob($prompts->first(), $team->id, ['openai'], 2);
    $job->handle($mock);
});

it('completes when no prompts exist', function () {
    $team = Team::factory()->create();

    $mock = Mockery::mock(JobDispatcherService::class);
    $mock->shouldReceive('dispatchBatch')->never();
    $this->app->instance(JobDispatcherService::class, $mock);

    $job = new RunAllPromptsJob(new Prompt(), $team->id);
    $job->handle($mock);

    expect(true)->toBeTrue();
});
