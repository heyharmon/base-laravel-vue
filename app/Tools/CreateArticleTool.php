<?php

namespace App\Tools;

use App\Models\Article;

class CreateArticleTool implements ChatTool
{
    public function __construct(protected int $teamId)
    {
    }

    public function name(): string
    {
        return 'create_article';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Create a new article for the given prompt id.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'prompt_id' => [
                            'type' => 'integer',
                            'description' => 'ID of the related prompt',
                        ],
                        'title' => [
                            'type' => 'string',
                            'description' => 'Optional title for the article',
                        ],
                    ],
                    'required' => ['prompt_id'],
                ],
            ],
        ];
    }

    public function run(array $arguments)
    {
        $promptId = $arguments['prompt_id'] ?? null;
        if (!$promptId) {
            return json_encode(['error' => 'prompt_id missing']);
        }

        $article = Article::create([
            'team_id' => $this->teamId,
            'prompt_id' => $promptId,
            'title' => $arguments['title'] ?? 'Untitled',
        ]);

        return $article->toJson();
    }
}
