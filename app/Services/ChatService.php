<?php

namespace App\Services;

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

		// Extract the text content from the response
		$responseContent = '';
		if (isset($response->output[0]->content[0]->text)) {
			$responseContent = $response->output[0]->content[0]->text;
		}

		// Store the AI response
		$aiChat = $conversation->chats()->create([
			'role' => 'assistant',
			'content' => $responseContent,
			'metadata' => [
				'model' => 'gpt-4.1',
				'provider' => 'openai',
				'response_id' => $response->id,
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
	 * @return object
	 */
	private function getAiResponse(Conversation $conversation, string $userMessage): object
	{
		// Get the last chat that has a response_id in its metadata (if any)
		$lastResponseChat = $conversation->chats()
			->where('role', 'assistant')
			->whereNotNull('metadata->response_id')
			->orderBy('created_at', 'desc')
			->first();

		$requestData = [
			'model' => 'gpt-4.1',
			'input' => $userMessage,
		];

		// If we have a previous response ID, use it to maintain conversation state
		if ($lastResponseChat && isset($lastResponseChat->metadata['response_id'])) {
			$requestData['previous_response_id'] = $lastResponseChat->metadata['response_id'];
		} else {
			// For new conversations or if no previous response_id exists,
			// we need to include the system message
			$systemMessage = (string) view('prompts.system');
			$requestData['input'] = [
				['role' => 'system', 'content' => $systemMessage],
				['role' => 'user', 'content' => $userMessage]
			];
		}

		// Generate AI response using OpenAI Responses API
		$response = $this->client->responses()->create($requestData);

		return $response;
	}
}
