<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use App\Services\PromptRunnerService;
use Illuminate\Console\Command;

class RunDailyPrompts extends Command
{
    protected $signature = 'prompts:run-daily';
    protected $description = 'Run all prompts against LLM providers and track keyword occurrences';

    protected PromptRunnerService $promptRunnerService;

    public function __construct(PromptRunnerService $promptRunnerService)
    {
        parent::__construct();
        $this->promptRunnerService = $promptRunnerService;
    }

    public function handle()
    {
        $prompts = Prompt::all();
        $count = $prompts->count();
        
        $this->info("Running {$count} prompts against LLM providers...");
        
        $bar = $this->output->createProgressBar($count);
        $bar->start();
        
        foreach ($prompts as $prompt) {
            $this->promptRunnerService->runPrompt($prompt);
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('All prompts have been run successfully.');
        
        return Command::SUCCESS;
    }
}
