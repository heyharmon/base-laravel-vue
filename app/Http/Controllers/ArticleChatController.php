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
     * @param Article $article
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Article $article)
    {
        // Get the conversation for this article
        $conversation = $article->conversations()->first();
        
        if (!$conversation) {
            return response()->json([]);
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
        ]);

        try {
            // Get the prompt if it exists
            $prompt = null;
            if ($article->prompt_id) {
                $prompt = Prompt::find($article->prompt_id);
            }

            // Set up the chat service with article and prompt context
            $this->chatService->withArticle($article);
            
            if ($prompt) {
                $this->chatService->withPrompt($prompt);
            }

            // Get or create a conversation for this article
            $conversation = $article->conversations()->firstOrCreate([
                'team_id' => $article->team_id,
                'title' => 'Chat for article: ' . $article->title
            ]);
            
            // Generate response
            $response = $this->chatService->generateResponse($conversation, $request->content);

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('Error generating article chat response: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to generate response'], 500);
        }
    }
}
