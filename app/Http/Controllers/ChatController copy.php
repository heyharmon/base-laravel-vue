<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

class ChatController extends Controller
{
    public function index(Conversation $conversation)
    {
        return response()->json($conversation->chats()->get());
    }

    public function store(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);
        
        // Store the user message
        $userChat = $conversation->chats()->create([
            'role' => 'user',
            'content' => $validated['content'],
        ]);
        
        // Fetch all previous chats in the conversation
        $previousChats = $conversation->chats()->orderBy('created_at', 'asc')->get();
        
        // Build messages array for conversation history
        $messages = [];
        
        // Add system message
        $messages[] = new SystemMessage((string)view('prompts.system'));
        
        // Add all previous messages as context
        foreach ($previousChats as $chat) {
            if ($chat->role === 'user') {
                $messages[] = new UserMessage($chat->content);
            } elseif ($chat->role === 'assistant') {
                $messages[] = new AssistantMessage($chat->content);
            }
        }
        
        // Add the current user message
        $messages[] = new UserMessage($validated['content']);
        
        // Generate AI response using Prism with full conversation context
        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o')
            ->withMessages($messages)
            ->asText();
        
        // Store the AI response
        $aiChat = $conversation->chats()->create([
            'role' => 'assistant',
            'content' => $response->text,
            'metadata' => [
                'model' => 'gpt-4o',
                'provider' => 'openai',
            ],
        ]);
        
        return response()->json([
            'id' => $aiChat->id,
            'role' => $aiChat->role,
            'content' => $aiChat->content,
            'created_at' => $aiChat->created_at,
        ]);
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
