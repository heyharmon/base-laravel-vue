<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        protected ChatService $chatService
    ) {}
    
    public function index(Conversation $conversation)
    {
        return response()->json($conversation->chats()->get());
    }

    public function store(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);
        
        $response = $this->chatService->generateResponse($conversation, $validated['content']);
        
        return response()->json($response);
    }
    
    public function show(Conversation $conversation, Chat $chat)
    {
        return response()->json([
            'id' => $chat->id,
            'role' => $chat->role,
            'content' => $chat->content,
            'metadata' => $chat->metadata,
            'created_at' => $chat->created_at,
        ]);
    }
}
