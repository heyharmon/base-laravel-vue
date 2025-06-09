<?php

namespace App\Tools;

use App\Models\Prompt;

class GetPromptTool implements ChatTool
{
    public function __construct(protected int $teamId)
    {
    }

    public function name(): string
    {
        return 'get_prompt';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Fetch a prompt by id for the current team.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt_id' => [
                            'type' => 'integer',
                            'description' => 'ID of the prompt to fetch',
                        ],
                    ],
                    'required' => ['prompt_id'],
                ],
            ],
        ];
    }

    public function run(array $arguments)
    {
        $id = $arguments['prompt_id'] ?? null;
        if (!$id) {
            return json_encode(['error' => 'prompt_id missing']);
        }

        $prompt = Prompt::where('team_id', $this->teamId)->find($id);
        if (!$prompt) {
            return json_encode(['error' => 'prompt not found']);
        }

        return $prompt->toJson();
    }
}
