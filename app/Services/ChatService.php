<?php

namespace App\Services;

use App\Models\Chat;
use App\Models\Conversation;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Enums\ToolChoice;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;

class ChatService
{
    /**
     * Generate an AI response for a conversation
     *
     * @param Conversation $conversation
     * @param string $userMessage
     * @return array
     */
    public function generateResponse(Conversation $conversation, string $userMessage, ?string $systemMessage = null, array $tools = []): array
    {
        // Store the user message
        $userChat = $conversation->chats()->create([
            'role' => 'user',
            'content' => $userMessage,
        ]);
        
        // Get AI response
        $response = $this->getAiResponse($conversation, $userMessage, $systemMessage, $tools);
        
        // Store the AI response
        $aiChat = $conversation->chats()->create([
            'role' => 'assistant',
            'content' => $response->text,
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
     * Get AI response using Prism
     *
     * @param Conversation $conversation
     * @param string $userMessage
     * @return object
     */
    private function getAiResponse(Conversation $conversation, string $userMessage, ?string $systemMessage = null, array $tools = []): object
    {
        // Fetch all previous chats in the conversation
        $previousChats = $conversation->chats()->orderBy('created_at', 'asc')->get();
        
        // Build messages array for conversation history
        $messages = [];
        
        // Add system message
        $messages[] = new SystemMessage($systemMessage ?? (string)view('prompts.system'));
        
        // Add all previous messages as context
        foreach ($previousChats as $chat) {
            if ($chat->role === 'user') {
                $messages[] = new UserMessage($chat->content);
            } elseif ($chat->role === 'assistant') {
                $messages[] = new AssistantMessage($chat->content);
            }
        }
        
        // Add the current user message
        $messages[] = new UserMessage($userMessage);
        
        // Generate AI response using Prism with full conversation context
        $prism = Prism::text()
            ->using(Provider::OpenAI, 'gpt-4o')
            ->withMessages($messages);

        if (!empty($tools)) {
            $prism->withMaxSteps(10)->withTools($tools)->withToolChoice(ToolChoice::Auto);
        }

        return $prism->asText();
    }
}
