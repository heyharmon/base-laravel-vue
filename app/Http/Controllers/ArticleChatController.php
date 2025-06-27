<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use App\Services\OpenAIService;
use App\Models\Article;

class ArticleChatController extends Controller
{
	protected $openAIService;

	public function __construct(OpenAIService $openAIService)
	{
		$this->openAIService = $openAIService;
	}

	/**
	 * Get chats for an article
	 *
	 * @param Request $request
	 * @param Article $article
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function index(Request $request, Article $article)
	{
		// Check if a specific conversation ID was requested
		if ($request->has('conversation_id')) {
			$conversation = $article->conversations()->where('id', $request->conversation_id)->first();

			if (!$conversation) {
				return response()->json([]);
			}
		} else {
			// Get the first conversation for this article (default behavior)
			$conversation = $article->conversations()->first();

			if (!$conversation) {
				return response()->json([]);
			}
		}

		// Return the chats for this conversation
		$chats = $conversation->chats()->orderBy('created_at')->get();
		return response()->json($chats);
	}

	/**
	 * Send a message to the AI about an article
	 *
	 * @param Request $request
	 * @param Article $article
	 * @return \Illuminate\Http\JsonResponse
	 */
	public function store(Request $request, Article $article)
	{
		$request->validate([
			'content' => 'required|string',
			'context' => 'sometimes|array',
			'conversation_id' => 'nullable|integer|exists:conversations,id'
		]);

		try {
			// Get or create a conversation for this article
			$conversation = null;

			if ($request->has('conversation_id')) {
				// Use the specified conversation if it belongs to this article
				$conversation = $article->conversations()->where('id', $request->conversation_id)->first();

				if (!$conversation) {
					return response()->json(['error' => 'Conversation not found for this article'], 404);
				}
			} else {
				// Get or create a conversation
				$conversation = $article->conversations()->firstOrCreate([
					'team_id' => $article->team_id,
					'title' => 'Chat for article: ' . $article->title
				]);
			}

			// Store user message immediately
			$conversation->chats()->create([
				'role' => 'user',
				'content' => $request->input('content')
			]);

			// Process message asynchronously with context
			$this->openAIService->processMessage(
				$conversation,
				$request->input('content'),
				$request->input('context', [])
			);

			return response()->json([
				'conversation' => $conversation->fresh(),
				'chats' => $conversation->chats
			]);
		} catch (\Exception $e) {

			Log::error('Error generating article chat response: ' . $e->getMessage());
			return response()->json(['error' => 'Failed to generate response'], 500);
		}
	}
}
