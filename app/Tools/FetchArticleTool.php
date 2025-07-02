<?php

namespace App\Tools;

use App\Models\Article;
use Illuminate\Support\Facades\Log;

class FetchArticleTool
{
    /**
     * Get the schema definition for the fetch_article tool.
     */
    public static function getSchema(): array
    {
        return [
            'type' => 'function',
            'name' => 'fetch_article',
            'description' => 'Fetch the current article content by ID',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'article_id' => [
                        'type'        => 'integer',
                        'description' => 'The ID of the article to fetch. If not provided, uses the current article ID.'
                    ]
                ],
                'required' => ['article_id']
            ]
        ];
    }

    /**
     * Execute the fetch_article tool.
     * This allows the agent to get the most up-to-date version of the article from the database.
     */
    public function execute(array $arguments, ?Article $currentArticle = null): array
    {
        $articleId = $arguments['article_id'] ?? ($currentArticle ? $currentArticle->id : null);

        // If no article ID provided, try to use the current article context
        if (!$articleId) {
            Log::error('Cannot fetch article: No article ID provided and no article context available.');
            return [
                'success' => false,
                'message' => 'No article ID provided to fetch.'
            ];
        }

        try {
            // Fetch the article from the database to ensure we have the latest version
            $article = Article::find($articleId);

            if (!$article) {
                return [
                    'success' => false,
                    'message' => 'Article not found.'
                ];
            }

            return [
                'success' => true,
                'article' => [
                    'id'         => $article->id,
                    'title'      => $article->title,
                    'content'    => $article->content,
                    'prompt_id'  => $article->prompt_id,
                ]
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching article: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to fetch article: ' . $e->getMessage()
            ];
        }
    }
}
