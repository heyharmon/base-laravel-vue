<?php

namespace App\Services;

use OpenAI\Client;
use OpenAI;
use Illuminate\Support\Facades\Log;
use App\Models\Conversation;
use App\Models\Article;
use App\Models\Prompt;
use App\Models\Response;
use App\Events\ArticleUpdated;

class ChatService
{
	protected Client $client;
	protected Article $article;
	protected ?Prompt $prompt = null;
	// Removed the unused $conversation property (was causing bugs when not set).

	public function __construct()
	{
		$this->client = OpenAI::client(config('services.openai.api_key'));
	}

	public function withArticle(Article $article): self
	{
		$this->article = $article;
		return $this;
	}

	public function withPrompt(Prompt $prompt): self
	{
		$this->prompt = $prompt;
		return $this;
	}

	/**
	 * Generate an AI response for a conversation.
	 */
	public function generateResponse(Conversation $conversation, string $userMessage): array
	{
		// Store the user message in the conversation history
		$userChat = $conversation->chats()->create([
			'role'    => 'user',
			'content' => $userMessage,
		]);

		// Get AI response (which may involve multiple tool calls)
		$finalResponse = $this->getAiResponse($conversation, $userMessage);

		// Log the final AI response for debugging
		Log::info('AI Final Response: ' . json_encode($finalResponse));

		// Parse the final response to extract assistant message content and any annotations (citations)
		$responseContent = '';
		$annotations = null;
		$responseData = $finalResponse->toArray();
		$outputs = $responseData['output'] ?? [];
		$messageOutput = null;
		foreach ($outputs as $outputItem) {
			if ($outputItem['type'] === 'message' && ($outputItem['role'] ?? '') === 'assistant') {
				$messageOutput = $outputItem;
				break;
			}
		}

		if ($messageOutput && isset($messageOutput['content'][0]['text'])) {
			$responseContent = $messageOutput['content'][0]['text'];
			// Capture annotations (e.g., web search citations) if present
			if (!empty($messageOutput['content'][0]['annotations'])) {
				$annotations = $messageOutput['content'][0]['annotations'];
			}
		} else {
			// In case no assistant message was found (which shouldn’t happen), provide a fallback
			$responseContent = "I'm sorry, I wasn't able to generate a response.";
		}

		// Store the AI's assistant message in the database with metadata
		$aiChat = $conversation->chats()->create([
			'role'       => 'assistant',
			'content'    => $responseContent,
			'metadata'   => [
				'model'        => 'gpt-4.1',
				'provider'     => 'openai',
				'response_id'  => $responseData['id'] ?? null,  // store the final response ID for context
			],
			'annotations' => $annotations,
		]);

		// Return the formatted response data
		return [
			'id'          => $aiChat->id,
			'role'        => $aiChat->role,
			'content'     => $aiChat->content,
			'created_at'  => $aiChat->created_at,
			'annotations' => $aiChat->annotations,
		];
	}

	/**
	 * Orchestrate the OpenAI API call(s) to get a response, handling tool usage in a loop.
	 */
	private function getAiResponse(Conversation $conversation, string $userMessage): object
	{
		// Prepare the list of tools (functions and web search) available to the model
		$tools = [
			['type' => 'web_search'],
			[
				'type' => 'function',
				'name' => 'fetch_article',
				'description' => 'Fetch the current article content by ID',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'article_id' => [
							'type'        => 'integer',
							'description' => 'The ID of the article to fetch. If not provided, uses the current article ID.'
						]
					],
					'required' => ['article_id']
				]
			],
			[
				'type' => 'function',
				'name' => 'edit_article_content',
				'description' => 'Edit the content of the current article',
				'parameters' => [
					'type' => 'object',
					'properties' => [
						'content' => [
							'type'        => 'string',
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
							'type'        => 'integer',
							'description' => 'The ID of the prompt to fetch. If not provided, uses the current article\'s prompt_id.'
						]
					]
					// 'required' is not strictly needed here since prompt_id is optional
				]
			]
		];

		// Build the initial request payload for the OpenAI Responses API
		$requestData = [
			'model' => 'gpt-4o',  // using GPT-4 with tool support (Responses API)
			'tools' => $tools,
		];

		// If continuing an existing conversation, include previous response ID for context
		$lastAssistant = $conversation->chats()
			->where('role', 'assistant')
			->whereNotNull('metadata->response_id')
			->orderBy('created_at', 'desc')
			->first();
		if ($lastAssistant && isset($lastAssistant->metadata['response_id'])) {
			// Continue the conversation by providing the new user query and reference to the last AI response
			$requestData['input'] = $userMessage;
			$requestData['previous_response_id'] = $lastAssistant->metadata['response_id'];
		} else {
			// Start of a new conversation: include a system message with instructions and context
			$systemMessage = (string) view('prompts.system');  // Base system prompt from a view/template

			// **Add conversational tool-use instructions to the system prompt**
			$systemMessage .= "\n\nYou are a conversational AI assistant that can use tools (functions) to help the user. "
				. "Whenever you use a tool, you **must** explain the result to the user in your next message in a helpful manner.\n"
				. "- If you edit the article content, summarize the changes you made and suggest further improvements, then ask the user if they want additional changes.\n"
				. "- If you fetch prompt details, describe what the prompt is about (and any insights from its recent responses) to the user. If the user's request is to improve the article using this prompt, use the prompt information to guide your edits.\n"
				. "- Always respond with a friendly, conversational tone and ensure the user is informed of any actions you took.";

			// Include current article context if available
			if ($this->article) {
				$systemMessage .= "\n\nThe current article being edited has ID {$this->article->id} and title '{$this->article->title}'.";
				// (We avoid dumping full article content here to save token space; the agent can fetch it via the tool if needed)
			}
			// Include prompt context if available (note: content may be large, so using only ID or summary)
			if ($this->prompt) {
				$systemMessage .= "\n\nThis article is based on prompt ID {$this->prompt->id} ('{$this->prompt->name}').";
				// (We avoid dumping full prompt content here to save token space; the agent can fetch it via the tool if needed)
			}

			// Set up the conversation start with system and user messages
			$requestData['input'] = [
				['role' => 'system', 'content' => $systemMessage],
				['role' => 'user',   'content' => $userMessage]
			];
		}

		Log::info('Initial Request Data: ' . json_encode($requestData));

		// Send the first request to OpenAI
		$response = $this->client->responses()->create($requestData);
		Log::info('Initial Response: ' . json_encode($response));

		// Process the response, handling any function calls in a loop
		$responseData = $response->toArray();
		$outputs = $responseData['output'] ?? [];
		$currentResponse = $response;
		$currentOutputs = $outputs;
		$prevResponseId = $responseData['id'] ?? null;
		$loopCount = 0;

		while (true) {
			$loopCount++;
			if ($loopCount > 5) {
				Log::warning('Too many function call loops – something might be wrong. Breaking out.');
				break;
			}

			// Find any function call outputs in the current outputs
			$functionCalls = array_filter($currentOutputs, function ($item) {
				return isset($item['type']) && $item['type'] === 'function_call';
			});

			if (empty($functionCalls)) {
				// No function call requested, so we assume we have a final answer from the model
				break;
			}

			// Prepare the function call results to send back to the model
			$functionCallOutputs = [];
			foreach ($functionCalls as $call) {
				$funcName = $call['name'] ?? '';
				$callId   = $call['call_id'] ?? null;
				$args     = isset($call['arguments']) ? json_decode($call['arguments'], true) : [];

				Log::info("Handling function call: {$funcName} with args: " . json_encode($args));
				$toolResult = $this->handleToolCall($funcName, $args);
				Log::info("Tool result for {$funcName}: " . json_encode($toolResult));

				if ($callId) {
					// Append the function result as a function_call_output message for the model
					$functionCallOutputs[] = [
						'type'    => 'function_call_output',
						'call_id' => $callId,
						'output'  => json_encode($toolResult)
					];
				} else {
					Log::warning("Missing call_id for function {$funcName}, skipping sending output back to model.");
				}
			}

			if (!empty($functionCallOutputs)) {
				// Send the function outputs back to the model to continue the conversation
				$followUpRequest = [
					'model'               => 'gpt-4o',
					'tools'               => $tools,
					'input'               => $functionCallOutputs,
					'previous_response_id' => $prevResponseId,
				];
				Log::info('Follow-up Request (with function outputs): ' . json_encode($followUpRequest));
				$currentResponse = $this->client->responses()->create($followUpRequest);
				Log::info('Follow-up Response: ' . json_encode($currentResponse));

				// Prepare for next loop iteration
				$responseData = $currentResponse->toArray();
				$currentOutputs = $responseData['output'] ?? [];
				$prevResponseId = $responseData['id'] ?? $prevResponseId;
			} else {
				// No outputs to send (perhaps an error occurred), break to avoid infinite loop
				break;
			}

			// Loop continues if the model makes another function_call in the follow-up response
		}

		// After resolving all tool calls, $currentResponse should contain the final AI answer
		return $currentResponse;
	}

	/**
	 * Handle a tool function call by executing the corresponding action.
	 * Returns the result that will be passed back to the AI model.
	 */
	private function handleToolCall(string $name, array $arguments)
	{
		switch ($name) {
			case 'edit_article_content':
				$content = $arguments['content'] ?? '';
				Log::info("Editing article content via tool. New content length: " . strlen($content));
				return $this->editArticleContent($content);

			case 'fetch_article':
				$articleId = $arguments['article_id']
					?? ($this->article ? $this->article->id : null);
				Log::info("Fetching article (ID: {$articleId}) via tool.");
				return $this->fetchArticle($articleId);

			case 'fetch_prompt_with_responses':
				$promptId = $arguments['prompt_id']
					?? ($this->article ? $this->article->prompt_id : null);
				Log::info("Fetching prompt (ID: {$promptId}) via tool.");
				return $this->fetchPromptWithResponses($promptId);

			default:
				Log::warning("Unknown tool call: {$name}");
				return [
					'success' => false,
					'message' => "Tool {$name} not recognized by ChatService"
				];
		}
	}

	/**
	 * Fetch an article by ID.
	 * This allows the agent to get the most up-to-date version of the article from the database.
	 */
	private function fetchArticle(?int $articleId): array
	{
		// If no article ID provided, try to use the current article context
		if (!$articleId) {
			if (!$this->article) {
				Log::error('Cannot fetch article: No article ID provided and no article context available.');
				return [
					'success' => false,
					'message' => 'No article ID provided to fetch.'
				];
			}
			$articleId = $this->article->id;
		}

		try {
			// Fetch the article from the database to ensure we have the latest version
			$article = Article::find($articleId);

			if (!$article) {
				return [
					'success' => false,
					'message' => 'Article not found.'
				];
			}

			return [
				'success' => true,
				'article' => [
					'id'         => $article->id,
					'title'      => $article->title,
					'content'    => $article->content,
					'prompt_id'  => $article->prompt_id,
					'created_at' => $article->created_at->toDateTimeString(),
					'updated_at' => $article->updated_at->toDateTimeString(),
				]
			];
		} catch (\Exception $e) {
			Log::error('Error fetching article: ' . $e->getMessage());
			return [
				'success' => false,
				'message' => 'Failed to fetch article: ' . $e->getMessage()
			];
		}
	}

	/**
	 * Edit the content of the current article in the database.
	 */
	private function editArticleContent(string $content): array
	{
		if (!$this->article) {
			Log::error('Cannot edit article content: No article context provided.');
			return [
				'success' => false,
				'message' => 'No article context available to edit.'
			];
		}

		try {
			$this->article->content = $content;
			$this->article->save();

			ArticleUpdated::class::dispatch($this->article);

			return [
				'success'   => true,
				'message'   => 'Article content updated successfully.',
				'article_id' => $this->article->id,
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
	 * Fetch a prompt with its associated recent responses.
	 */
	private function fetchPromptWithResponses(?int $promptId): array
	{
		if (!$promptId) {
			Log::error('Cannot fetch prompt: No prompt ID provided.');
			return [
				'success' => false,
				'message' => 'No prompt ID provided to fetch.'
			];
		}

		try {
			$prompt = Prompt::with(['responses' => function ($query) {
				$query->latest()->limit(5);
			}])->find($promptId);

			if (!$prompt) {
				return [
					'success' => false,
					'message' => 'Prompt not found.'
				];
			}

			return [
				'success'    => true,
				'prompt'     => [
					'id'          => $prompt->id,
					'name'        => $prompt->name,
					'content'     => $prompt->content,
					'description' => $prompt->description,
					'responses'   => $prompt->responses->map(function ($response) {
						return [
							'id'        => $response->id,
							'content'   => $response->content,
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
