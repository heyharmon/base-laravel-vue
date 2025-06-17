<?php

namespace App\Tools;

use App\Models\Article;
use App\Events\ArticleUpdated;
use Illuminate\Support\Facades\Log;

class EditArticleContentTool
{
    /**
     * Get the schema definition for the edit_article_content tool.
     */
    public static function getSchema(): array
    {
        return [
            'type' => 'function',
            'name' => 'edit_article_content',
            'description' => 'Edit the content of the current article',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'content' => [
                        'type'        => 'string',
                        'description' => 'The new content for the article'
                    ]
                ],
                'required' => ['content']
            ]
        ];
    }

    /**
     * Execute the edit_article_content tool.
     * Updates the content of the current article in the database.
     */
    public function execute(array $arguments, ?Article $currentArticle = null): array
    {
        if (!$currentArticle) {
            Log::error('Cannot edit article content: No article context provided.');
            return [
                'success' => false,
                'message' => 'No article context available to edit.'
            ];
        }

        try {
            $content = $arguments['content'] ?? null;
            
            if (!$content) {
                return [
                    'success' => false,
                    'message' => 'No content provided for article update.'
                ];
            }

            $currentArticle->content = $content;
            $currentArticle->save();

            ArticleUpdated::dispatch($currentArticle);

            return [
                'success'   => true,
                'message'   => 'Article content updated successfully.',
                'article_id' => $currentArticle->id,
            ];
        } catch (\Exception $e) {
            Log::error('Error updating article content: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update article content: ' . $e->getMessage()
            ];
        }
    }
}
