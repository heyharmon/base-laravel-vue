<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI;
use App\Models\Conversation;
use App\Tools\ChatTool;
use App\Tools\ListPromptsTool;
use App\Tools\GetPromptTool;
use App\Tools\CreateArticleTool;
use App\Tools\WriteArticleContentTool;

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
                $conversation->chats()->create([
                        'role' => 'user',
                        'content' => $userMessage,
                ]);

                // Build chat history including system prompt
                $messages = $conversation->chats()
                        ->orderBy('created_at')
                        ->get()
                        ->map(fn($chat) => [
                                'role' => $chat->role,
                                'content' => $chat->content,
                        ])
                        ->toArray();

                array_unshift($messages, [
                        'role' => 'system',
                        'content' => (string) view('prompts.system'),
                ]);

                $tools = $this->buildTools($conversation);
                $toolDefinitions = array_map(fn(ChatTool $t) => $t->definition(), $tools);

                $response = $this->client->chat()->create([
                        'model' => 'gpt-4o',
                        'messages' => $messages,
                        'tools' => $toolDefinitions,
                        'tool_choice' => 'auto',
                ]);

                $message = $response['choices'][0]['message'];
                while (isset($message['tool_calls'])) {
                        $messages[] = $message;
                        $toolMessages = [];

                        foreach ($message['tool_calls'] as $toolCall) {
                                $tool = $this->findTool($toolCall['function']['name'], $tools);
                                if (!$tool) {
                                        continue;
                                }
                                $args = json_decode($toolCall['function']['arguments'], true) ?: [];
                                $output = $tool->run($args);

                                $toolMessages[] = [
                                        'role' => 'tool',
                                        'tool_call_id' => $toolCall['id'],
                                        'name' => $toolCall['function']['name'],
                                        'content' => is_string($output) ? $output : json_encode($output),
                                ];
                        }

                        $messages = array_merge($messages, $toolMessages);

                        $response = $this->client->chat()->create([
                                'model' => 'gpt-4o',
                                'messages' => $messages,
                        ]);
                        $message = $response['choices'][0]['message'];
                }

                // Store the AI response
                $aiChat = $conversation->chats()->create([
                        'role' => 'assistant',
                        'content' => $message['content'] ?? '',
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
         * Build the tool instances for this conversation.
         */
        protected function buildTools(Conversation $conversation): array
        {
                $teamId = $conversation->team_id;

                return [
                        new ListPromptsTool($teamId),
                        new GetPromptTool($teamId),
                        new CreateArticleTool($teamId),
                        new WriteArticleContentTool($teamId),
                ];
        }

        /**
         * Find a tool by name from the provided array.
         */
        protected function findTool(string $name, array $tools): ?ChatTool
        {
                foreach ($tools as $tool) {
                        if ($tool->name() === $name) {
                                return $tool;
                        }
                }

                return null;
        }
}
