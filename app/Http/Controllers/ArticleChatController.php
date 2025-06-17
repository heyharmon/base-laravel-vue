<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Prompt;
use App\Services\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ArticleChatController extends Controller
{
	protected ChatService $chatService;

	public function __construct(ChatService $chatService)
	{
		$this->chatService = $chatService;
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
			'conversation_id' => 'nullable|integer|exists:conversations,id'
		]);

		try {
			// Set up the chat service with article and prompt context
			$this->chatService->withArticle($article);
			$this->chatService->withOrganization($article->organization);

			// Get the prompt that belongs to the article, if that prompt exists
			if ($article->prompt_id) {
				$prompt = Prompt::find($article->prompt_id);
				$this->chatService->withPrompt($prompt);
			}

			// Get or create a conversation for this article
			$conversation = null;

			if ($request->has('conversation_id')) {
				// Use the specified conversation if it belongs to this article
				$conversation = $article->conversations()->where('id', $request->conversation_id)->first();

				if (!$conversation) {
					return response()->json(['error' => 'Conversation not found for this article'], 404);
				}
			} else {
				// Get or create a default conversation
				$conversation = $article->conversations()->firstOrCreate([
					'team_id' => $article->team_id,
					'title' => 'Chat for article: ' . $article->title
				]);
			}

			// Generate response
			$response = $this->chatService->generateResponse($conversation, $request->content);

			return response()->json($response);
		} catch (\Exception $e) {
			Log::error('Error generating article chat response: ' . $e->getMessage());
			return response()->json(['error' => 'Failed to generate response'], 500);
		}
	}
}
