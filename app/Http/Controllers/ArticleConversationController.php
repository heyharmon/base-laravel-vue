<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArticleConversationController extends Controller
{
	/**
	 * Get conversations for an article
	 *
	 * @param Article $article
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index(Article $article)
	{
		// Get all conversations for this article
		$conversations = $article->conversations()->orderBy('created_at', 'desc')->get();

		return response()->json($conversations);
	}

	/**
	 * Create a new conversation for an article
	 *
	 * @param Request $request
	 * @param Article $article
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function store(Request $request, Article $article)
	{
		$request->validate([
			'title' => 'nullable|string|max:255',
		]);

		try {
			// Create a new conversation for this article
			$conversation = $article->conversations()->create([
				'team_id' => $article->team_id,
				'title' => $request->title ?? 'Chat for article: ' . now()->format('F j, Y \a\t g:i A')
			]);

			return response()->json($conversation);
		} catch (\Exception $e) {
			Log::error('Error creating article conversation: ' . $e->getMessage());
			return response()->json(['error' => 'Failed to create conversation'], 500);
		}
	}
}
