<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\ArticleVersion;
use Illuminate\Http\JsonResponse;

class ArticleVersionController extends Controller
{
    /**
     * Revert an article to a specific version.
     */
    public function revert(Article $article, ArticleVersion $version): JsonResponse
    {
        // Ensure the article belongs to the current team
        if ($article->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        // Ensure the version belongs to this article
        if ($version->article_id !== $article->id) {
            return response()->json(['message' => 'Version does not belong to this article'], 403);
        }
        
        $article->revertToVersion($version);
        
        return response()->json($article);
    }
}
