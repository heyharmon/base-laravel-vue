<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use App\Models\Article;
use App\Models\Prompt;
use App\Models\Response;

class ChatService
{
	protected Client $client;
	protected ?Article $article = null;
	protected ?Prompt $prompt = null;

	public function __construct()
	{
		$this->client = OpenAI::client(config('services.openai.api_key'));
	}

	/**
	 * Set the article context for the chat
	 *
	 * @param Article $article
	 * @return $this
	 */
	public function withArticle(Article $article): self
	{
		$this->article = $article;
		return $this;
	}

	/**
	 * Set the prompt context for the chat
	 *
	 * @param Prompt $prompt
	 * @return $this
	 */
	public function withPrompt(Prompt $prompt): self
	{
		$this->prompt = $prompt;
		return $this;
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
		Log::info('With Article: ' . $this->article);
		Log::info('With Prompt: ' . $this->prompt);

		// Store the user message
		$userChat = $conversation->chats()->create([
			'role' => 'user',
			'content' => $userMessage,
		]);

		// Get AI response
		$response = $this->getAiResponse($conversation, $userMessage);

		// Log the response
		Log::info('AI Response: ' . json_encode($response));

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

		Log::info('Last response chat: ' . json_encode($lastResponseChat));

		$requestData = [
			'model' => 'gpt-4o',
			'input' => $userMessage,
			// Add tools for the model to use
			'tools' => [
				[
					'type' => 'web_search'
				],
				[
					'type' => 'function',
					'name' => 'edit_article_content',
					'description' => 'Edit the content of the current article',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'content' => [
								'type' => 'string',
								'description' => 'The new content for the article in HTML format'
							]
						],
						'required' => ['content']
					]
				],
				[
					'type' => 'function',
					'name' => 'fetch_prompt_with_responses',
					'description' => 'Fetch a prompt record with its associated responses',
					'parameters' => [
						'type' => 'object',
						'properties' => [
							'prompt_id' => [
								'type' => 'integer',
								'description' => 'The ID of the prompt to fetch. If not provided, will use the current article\'s prompt_id.'
							]
						]
					]
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

			// Add article and prompt context if available
			if ($this->article) {
				$systemMessage .= "\n\nYou are currently editing an article with ID: {$this->article->id} and title: '{$this->article->title}'.";
				$systemMessage .= "\nCurrent article content: \n{$this->article->content}";
			}

			if ($this->prompt) {
				$systemMessage .= "\n\nThis article is based on the prompt: '{$this->prompt->name}'";
				$systemMessage .= "\nPrompt content: {$this->prompt->content}";
			}

			$requestData['input'] = [
				['role' => 'system', 'content' => $systemMessage],
				['role' => 'user', 'content' => $userMessage]
			];
		}

		Log::info('Request data: ' . json_encode($requestData));

		// Generate AI response using OpenAI Responses API
		$response = $this->client->responses()->create($requestData);
		$responseOutputs = $response->toArray()['output'] ?? [];

		Log::info('Response outputs: ' . json_encode($responseOutputs));

		// Handle tool calls if any
		foreach ($responseOutputs as $item) {
			if ($item['type'] === 'function_call') {
				// Model wants to use a tool
				$funcName = $item['name'];
				$funcArgs = json_decode($item['arguments'], true);

				// Handle the tool call
				$this->handleToolCall($funcName, $funcArgs);
			}
		}

		return $response;
	}

	/**
	 * Handle tool calls from the AI
	 *
	 * @param string $name
	 * @param array $arguments
	 * @return mixed
	 */
	private function handleToolCall(string $name, array $arguments)
	{
		switch ($name) {
			case 'edit_article_content':
				return $this->editArticleContent($arguments['content'] ?? '');

			case 'fetch_prompt_with_responses':
				$promptId = $arguments['prompt_id'] ?? ($this->article ? $this->article->prompt_id : null);
				return $this->fetchPromptWithResponses($promptId);

			default:
				Log::warning("Unknown tool call: {$name}");
				return null;
		}
	}

	/**
	 * Edit the content of the current article
	 *
	 * @param string $content
	 * @return array|null
	 */
	private function editArticleContent(string $content): ?array
	{
		if (!$this->article) {
			Log::error('Cannot edit article content: No article context provided');
			return null;
		}

		try {
			$this->article->content = $content;
			$this->article->save();

			return [
				'success' => true,
				'message' => 'Article content updated successfully',
				'article_id' => $this->article->id
			];
		} catch (\Exception $e) {
			Log::error('Error updating article content: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Failed to update article content: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Fetch a prompt with its associated responses
	 *
	 * @param int|null $promptId
	 * @return array|null
	 */
	private function fetchPromptWithResponses(?int $promptId): ?array
	{
		if (!$promptId) {
			Log::error('Cannot fetch prompt: No prompt ID provided');
			return null;
		}

		try {
			$prompt = Prompt::with(['responses' => function ($query) {
				$query->latest()->limit(5);
			}])->find($promptId);

			if (!$prompt) {
				return [
					'success' => false,
					'message' => 'Prompt not found'
				];
			}

			return [
				'success' => true,
				'prompt' => [
					'id' => $prompt->id,
					'name' => $prompt->name,
					'content' => $prompt->content,
					'description' => $prompt->description,
					'responses' => $prompt->responses->map(function ($response) {
						return [
							'id' => $response->id,
							'content' => $response->content,
							'created_at' => $response->created_at->toDateTimeString()
						];
					})->toArray()
				]
			];
		} catch (\Exception $e) {
			Log::error('Error fetching prompt with responses: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Failed to fetch prompt: ' . $e->getMessage()
			];
		}
	}
}
