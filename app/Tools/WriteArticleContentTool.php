<?php

namespace App\Tools;

use App\Models\Article;

class WriteArticleContentTool implements ChatTool
{
    public function __construct(protected int $teamId)
    {
    }

    public function name(): string
    {
        return 'write_article_content';
    }

    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => 'Write content into an article record.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'article_id' => [
                            'type' => 'integer',
                            'description' => 'ID of the article to update',
                        ],
                        'content' => [
                            'type' => 'string',
                            'description' => 'HTML content for the article',
                        ],
                    ],
                    'required' => ['article_id', 'content'],
                ],
            ],
        ];
    }

    public function run(array $arguments)
    {
        $id = $arguments['article_id'] ?? null;
        if (!$id) {
            return json_encode(['error' => 'article_id missing']);
        }
        $article = Article::where('team_id', $this->teamId)->find($id);
        if (!$article) {
            return json_encode(['error' => 'article not found']);
        }
        $article->update([
            'content' => $arguments['content'] ?? '',
        ]);

        return $article->toJson();
    }
}
