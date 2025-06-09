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

                $tools = $this->buildTools($conversation);
                $toolDefinitions = array_map(fn(ChatTool $t) => $t->definition(), $tools);
                array_unshift($toolDefinitions, ['type' => 'web_search']);

                // Determine previous response id if conversation has history
                $lastResponseChat = $conversation->chats()
                        ->where('role', 'assistant')
                        ->whereNotNull('metadata->response_id')
                        ->orderByDesc('created_at')
                        ->first();

                $previousId = $lastResponseChat->metadata['response_id'] ?? null;
                if ($previousId) {
                        $input = $userMessage;
                } else {
                        $input = [
                                ['role' => 'system', 'content' => (string) view('prompts.system')],
                                ['role' => 'user', 'content' => $userMessage],
                        ];
                }

                $response = $this->client->responses()->create([
                        'model' => 'gpt-4.1',
                        'input' => $input,
                        'tools' => $toolDefinitions,
                        'tool_choice' => 'auto',
                        'previous_response_id' => $previousId,
                ]);

                $messageOutput = $this->extractMessage($response);

                while ($messageOutput && isset($messageOutput->tool_calls)) {
                        $toolMessages = [];
                        foreach ($messageOutput->tool_calls as $call) {
                                $tool = $this->findTool($call->function->name, $tools);
                                if (!$tool) {
                                        continue;
                                }
                                $args = json_decode($call->function->arguments, true) ?: [];
                                $result = $tool->run($args);

                                $toolMessages[] = [
                                        'role' => 'tool',
                                        'tool_call_id' => $call->id,
                                        'name' => $call->function->name,
                                        'content' => is_string($result) ? $result : json_encode($result),
                                ];
                        }

                        $response = $this->client->responses()->create([
                                'model' => 'gpt-4.1',
                                'input' => $toolMessages,
                                'tools' => $toolDefinitions,
                                'tool_choice' => 'auto',
                                'previous_response_id' => $response->id,
                        ]);

                        $messageOutput = $this->extractMessage($response);
                }

                $content = '';
                $annotations = null;
                if ($messageOutput && isset($messageOutput->content[0]->text)) {
                        $content = $messageOutput->content[0]->text;
                        if (isset($messageOutput->content[0]->annotations) &&
                                is_array($messageOutput->content[0]->annotations) &&
                                count($messageOutput->content[0]->annotations) > 0) {
                                $annotations = $messageOutput->content[0]->annotations;
                        }
                }

                $aiChat = $conversation->chats()->create([
                        'role' => 'assistant',
                        'content' => $content,
                        'metadata' => [
                                'model' => 'gpt-4.1',
                                'provider' => 'openai',
                                'response_id' => $response->id,
                        ],
                        'annotations' => $annotations,
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

        /**
         * Extract the assistant message from a responses API result.
         */
        protected function extractMessage(object $response): ?object
        {
                foreach ($response->output as $output) {
                        if ($output->type === 'message' && $output->role === 'assistant') {
                                return $output;
                        }
                }

                return null;
        }
}
