<?php

namespace App\Tools;

use App\Models\Prompt;

class ListPromptsTool implements ChatTool
{
    public function __construct(protected int $teamId)
    {
    }

    public function name(): string
    {
        return 'list_prompts';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'List recent prompts for the current team. Returns id and content.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new \stdClass(),
                ],
            ],
        ];
    }

    public function run(array $arguments)
    {
        $prompts = Prompt::where('team_id', $this->teamId)
            ->orderByDesc('created_at')
            ->get(['id', 'content']);

        return $prompts->toJson();
    }
}
