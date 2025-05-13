<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;

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
        
        // Generate AI response using Prism
        $response = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o')
            ->withSystemPrompt(view('prompts.system'))
            ->withPrompt($validated['content'])
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
