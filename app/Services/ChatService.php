<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Conversation;
use OpenAI\Client;
use OpenAI;

class ChatService
{
    protected Client $client;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.api_key'));
    }

    /**
     * Generate an AI response for a conversation
     *
     * @param Conversation $conversation
     * @param string $userMessage
     * @return array
     */
    public function generateResponse(Conversation $conversation, string $userMessage): array
    {
        // Store the user message
        $userChat = $conversation->chats()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);
        
        // Get AI response
        $response = $this->getAiResponse($conversation, $userMessage);
        
        // Store the AI response
        $aiChat = $conversation->chats()->create([
            'role' => 'assistant',
            'content' => $response,
            'metadata' => [
                'model' => 'gpt-4o',
                'provider' => 'openai',
            ],
        ]);
        
        return [
            'id' => $aiChat->id,
            'role' => $aiChat->role,
            'content' => $aiChat->content,
            'created_at' => $aiChat->created_at,
        ];
    }
    
    /**
     * Get AI response using OpenAI client
     *
     * @param Conversation $conversation
     * @param string $userMessage
     * @return string
     */
    private function getAiResponse(Conversation $conversation, string $userMessage): string
    {
        // Fetch all previous chats in the conversation
        $previousChats = $conversation->chats()->orderBy('created_at', 'asc')->get();

        // Build messages array for conversation history
        $messages = [];

        // Add system message
        $messages[] = ['role' => 'system', 'content' => (string) view('prompts.system')];

        // Add all previous messages as context
        foreach ($previousChats as $chat) {
            $messages[] = ['role' => $chat->role, 'content' => $chat->content];
        }

        // Add the current user message
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        // Generate AI response using OpenAI with full conversation context
        $result = $this->client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => $messages,
        ]);

        return $result->choices[0]->message->content ?? '';
    }
}
