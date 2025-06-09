<?php

namespace App\Services;

use stdClass;
use OpenAI\Client;
use OpenAI;
use Illuminate\Support\Facades\Log;
use App\Tools\WriteArticleContentTool;
use App\Tools\WebSearchTool;
use App\Tools\ListPromptsTool;
use App\Tools\GetPromptTool;
use App\Tools\CreateArticleTool;
use App\Tools\ChatTool;
use App\Models\Conversation;

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

		// Build tools and prepare definitions
		$tools = $this->buildTools($conversation);
		$toolDefinitions = [];

		foreach ($tools as $tool) {
			$toolDefinitions[] = $tool->definition();
		}

		// Prepare conversation history for the API call
		$messages = $this->prepareMessages($conversation, $userMessage);

		// Make the initial API call
		$response = $this->client->chat()->create([
			'model' => 'gpt-4-turbo',
			'messages' => $messages,
			'tools' => $toolDefinitions,
			'tool_choice' => 'auto',
		]);

		// Get the assistant's response
		$assistantMessage = $response->choices[0]->message;
		$toolCalls = $assistantMessage->tool_calls ?? [];

		// Log the assistants message and tool calls
		Log::info('Assistant message: ' . json_encode($assistantMessage));
		Log::info('Assistant tool calls: ' . json_encode($toolCalls));

		// Process tool calls if they exist
		while (!empty($toolCalls)) {
			// Add the assistant's message to the conversation
			$messages[] = [
				'role' => 'assistant',
				'content' => $assistantMessage->content ?? null,
				'tool_calls' => $toolCalls
			];

			// Process each tool call
			foreach ($toolCalls as $call) {
				$tool = $this->findTool($call->function->name, $tools);
				if (!$tool) {
					continue;
				}

				$args = json_decode($call->function->arguments, true) ?: [];
				$result = $tool->run($args);

				$messages[] = [
					'role' => 'tool',
					'tool_call_id' => $call->id,
					'name' => $call->function->name,
					'content' => is_string($result) ? $result : json_encode($result),
				];
			}

			// Log the messages after tool calls
			Log::info('Messages after tool calls: ' . $messages);

			// Make the next API call with tool results
			$response = $this->client->chat()->create([
				'model' => 'gpt-4-turbo',
				'messages' => $messages,
				'tools' => $toolDefinitions,
				'tool_choice' => 'auto',
			]);

			// Get the assistant's response for the next iteration
			$assistantMessage = $response->choices[0]->message;
			$toolCalls = $assistantMessage->tool_calls ?? [];
		}

		// Extract the final content
		$content = $assistantMessage->content ?? '';

		// Store the assistant's response
		$aiChat = $conversation->chats()->create([
			'role' => 'assistant',
			'content' => $content,
			'metadata' => [
				'model' => 'gpt-4-turbo',
				'provider' => 'openai',
				'response_id' => $response->id,
			],
			'annotations' => null,
		]);

		// Log the final ai chat
		Log::info('Final ai chat: ' . $aiChat);

		return [
			'id' => $aiChat->id,
			'role' => $aiChat->role,
			'content' => $aiChat->content,
			'created_at' => $aiChat->created_at,
		];
	}

	/**
	 * Prepare messages for the API call based on conversation history
	 */
	protected function prepareMessages(Conversation $conversation, string $userMessage): array
	{
		// Check if conversation has history
		$lastResponseChat = $conversation->chats()
			->where('role', 'assistant')
			->whereNotNull('metadata->response_id')
			->orderByDesc('created_at')
			->first();

		// If this is a new conversation, include the system prompt
		if (!$lastResponseChat) {
			return [
				['role' => 'system', 'content' => (string) view('prompts.system')],
				['role' => 'user', 'content' => $userMessage],
			];
		}

		// For existing conversations, just add the new user message
		return [['role' => 'user', 'content' => $userMessage]];
	}

	/**
	 * Build the tool instances for this conversation.
	 */
	protected function buildTools(Conversation $conversation): array
	{
		$teamId = $conversation->team_id;

		return [
			new WebSearchTool(),
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
