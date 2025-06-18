<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\PerplexityService;
use App\Models\Organization;
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
			->latest()
			->get();

		return response()->json($articles);
	}

	/**
	 * Store a newly created resource in storage.
	 */
	public function store(Request $request): JsonResponse
	{
		// Get the users team id
		$teamId = $request->user()->currentTeam->id;

		// Get the owned organization for this team
		$ownedOrganization = Organization::where('team_id', $teamId)
			->where('is_competitor', false)
			->first();

		$validated = $request->validate([
			'prompt_id' => 'nullable|exists:prompts,id',
			'title' => 'required|string|max:255',
			'meta_title' => 'nullable|string|max:255',
			'meta_description' => 'nullable|string',
			'schema' => 'nullable|string',
			'outline' => 'nullable|string',
			'content' => 'nullable|string',
		]);

		$article = request()->user()->currentTeam->articles()->create([
			...$validated,
			'team_id' => $teamId,
			'organization_id' => $ownedOrganization->id,
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

		$article->load('versions');

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
			'prompt_id' => 'sometimes|nullable|exists:prompts,id',
			'title' => 'sometimes|required|string|max:255',
			'meta_title' => 'sometimes|nullable|string|max:255',
			'meta_description' => 'sometimes|nullable|string',
			'schema' => 'sometimes|nullable|string',
			'outline' => 'sometimes|nullable|string',
			'content' => 'sometimes|nullable|string',
		]);

		$article->update($validated);

		$article->load('versions');

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

	/**
	 * Get the Perplexity completion response for the article.
	 */
	public function getPerplexityResponse(Article $article, PerplexityService $perplexityService): JsonResponse
	{
		// Ensure the article belongs to the current team
		if ($article->team_id !== request()->user()->currentTeam->id) {
			return response()->json(['message' => 'Unauthorized'], 403);
		}

		// Check if the article has a perplexity_request_id
		if (!$article->perplexity_request_id) {
			return response()->json(['message' => 'No Perplexity request found for this article'], 404);
		}

		try {
			// Get the completion status from the Perplexity API
			$response = $perplexityService->getAsyncChatCompletionStatus($article->perplexity_request_id);
			return response()->json($response);
		} catch (\Exception $e) {
			return response()->json(['message' => 'Failed to fetch Perplexity response: ' . $e->getMessage()], 500);
		}
	}
}
