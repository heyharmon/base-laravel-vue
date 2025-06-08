<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI;
use Illuminate\Support\Facades\Log;
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
		$userChat = $conversation->chats()->create([
			'role' => 'user',
			'content' => $userMessage,
		]);

		// Get AI response
		$response = $this->getAiResponse($conversation, $userMessage);

		// Log the response
		// Log::info('AI Response: ' . json_encode($response));

		// Extract the text content from the response
		$responseContent = '';
		$annotations = null;

		// Find the message output in the response
		// When web search is used, the message is in the second element of the output array
		// When web search is not used, the message is in the first element
		$messageOutput = null;
		foreach ($response->output as $output) {
			if ($output->type === 'message' && $output->role === 'assistant') {
				$messageOutput = $output;
				break;
			}
		}

		// Extract text content and annotations if message output was found
		if ($messageOutput && isset($messageOutput->content[0]->text)) {
			$responseContent = $messageOutput->content[0]->text;

			// Check if there are any annotations from web search results
			if (
				isset($messageOutput->content[0]->annotations) &&
				is_array($messageOutput->content[0]->annotations) &&
				count($messageOutput->content[0]->annotations) > 0
			) {
				$annotations = $messageOutput->content[0]->annotations;
			}
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
			'annotations' => $annotations,
		]);

		return [
			'id' => $aiChat->id,
			'role' => $aiChat->role,
			'content' => $aiChat->content,
			'created_at' => $aiChat->created_at,
			'annotations' => $aiChat->annotations,
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
			// Add web search tool to allow the model to search the web if needed
			'tools' => [
				[
					'type' => 'web_search'
				]
			]
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
