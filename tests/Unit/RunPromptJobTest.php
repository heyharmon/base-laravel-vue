<?php

namespace Tests\Unit\Jobs;

use Tests\TestCase;
use App\Models\Prompt;
use App\Models\Response;
use App\Jobs\RunPromptJob;
use App\Jobs\ProcessCompletedResponseJob;
use App\Services\OpenAIService;
use App\Exceptions\OpenAIException;
use Illuminate\Support\Facades\Queue;
use Mockery;

class RunPromptJobTest extends TestCase
{
    public function test_successful_prompt_processing(): void
    {
        Queue::fake();

        $prompt = Prompt::factory()->create();
        $response = Response::factory()->create([
            'prompt_id' => $prompt->id,
            'status' => 'pending',
        ]);

        $mock = Mockery::mock(OpenAIService::class);
        $mock->shouldReceive('buildPayload')->once()->andReturn([]);
        $mock->shouldReceive('createCompletion')->once()->andReturn([
            'choices' => [
                ['message' => ['content' => 'Test response']]
            ],
        ]);
        $this->app->instance(OpenAIService::class, $mock);

        $job = new RunPromptJob($response);
        $job->handle($mock);

        Queue::assertPushed(ProcessCompletedResponseJob::class);
    }

    public function test_retry_on_resource_unavailable(): void
    {
        Queue::fake();

        $prompt = Prompt::factory()->create();
        $response = Response::factory()->create([
            'prompt_id' => $prompt->id,
            'status' => 'pending',
        ]);

        $mock = Mockery::mock(OpenAIService::class);
        $mock->shouldReceive('buildPayload')->once()->andReturn([]);
        $mock->shouldReceive('createCompletion')
            ->once()
            ->andThrow(new OpenAIException('Resources unavailable', 'resource_unavailable', 429));
        $this->app->instance(OpenAIService::class, $mock);

        $job = new RunPromptJob($response);
        $job->handle($mock);

        Queue::assertPushed(RunPromptJob::class);
        $this->assertGreaterThan(0, $response->fresh()->retry_count);
    }
}
