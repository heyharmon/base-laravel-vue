<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Article;

class ArticleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;

        $articles = Article::where('team_id', $teamId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($articles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'nullable|exists:organizations,id',
            'prompt_id' => 'nullable|exists:prompts,id',
            'conversation_id' => 'nullable|string',
            'title' => 'required|string|max:255',
            'outline' => 'nullable|string',
            'content' => 'nullable|string',
        ]);

        $article = request()->user()->currentTeam->articles()->create([
            ...$validated,
            'team_id' => request()->user()->currentTeam->id,
        ]);

        return response()->json($article, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Article $article): JsonResponse
    {
        // Ensure the article belongs to the current team
        if ($article->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($article);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Article $article): JsonResponse
    {
        // Ensure the article belongs to the current team
        if ($article->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'organization_id' => 'sometimes|nullable|exists:organizations,id',
            'prompt_id' => 'sometimes|nullable|exists:prompts,id',
            'conversation_id' => 'sometimes|nullable|string',
            'title' => 'sometimes|required|string|max:255',
            'outline' => 'sometimes|nullable|string',
            'content' => 'sometimes|nullable|string',
        ]);

        $article->update($validated);

        return response()->json($article);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Article $article): JsonResponse
    {
        // Ensure the article belongs to the current team
        if ($article->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $article->delete();

        return response()->json(null, 204);
    }
}
