<?php

namespace App\Services;

use OpenAI\Laravel\Facades\OpenAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Prompt;
use App\Models\Conversation;
use App\Models\Article;
use App\Events\ArticleUpdated;
use App\Events\ArticleChatAgentFinished;

class OpenAIService
{
	/**
	 * The team ID for scoping tool operations.
	 *
	 * @var int|null
	 */
	protected $teamId;

	protected $tools = [
		[
			'type' => 'function',
			'name' => 'list_articles',
			'description' => 'List articles in the database with pagination support',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'page' => [
						'type' => 'integer',
						'description' => 'Page number (default: 1)',
						'minimum' => 1
					],
					'per_page' => [
						'type' => 'integer',
						'description' => 'Number of articles per page (default: 20, max: 100)',
						'minimum' => 1,
						'maximum' => 100
					]
				],
				'required' => []
			]
		],
		[
			'type' => 'function',
			'name' => 'view_article',
			'description' => 'View article content before edits. Use this to confirm selected_content approximate location, then proceed to edit article without outputting content.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'article_id' => [
						'type' => 'integer',
						'description' => 'The ID of the article to view'
					]
				],
				'required' => ['article_id']
			]
		],
		[
			'type' => 'function',
			'name' => 'find_content',
			'description' => 'Find the approximate position of content in article using fuzzy matching. Use this BEFORE replace_content or insert_content to locate the users selected content.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'article_id' => [
						'type' => 'integer',
						'description' => 'The ID of the article to search in'
					],
					'search_text' => [
						'type' => 'string',
						'description' => 'Text to find (will use fuzzy matching)'
					],
					'occurrence' => [
						'type' => 'integer',
						'description' => 'Which occurrence to find (1 = first, 2 = second, etc.)',
						'default' => 1
					]
				],
				'required' => ['article_id', 'search_text']
			]
		],
		[
			'type' => 'function',
			'name' => 'create_article',
			'description' => 'Create a new article',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'title' => [
						'type' => 'string',
						'description' => 'The title of the article'
					],
				],
				'required' => ['title']
			]
		],
		[
			'type' => 'function',
			'name' => 'edit_article_title',
			'description' => 'Edit the title of an existing article',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'article_id' => [
						'type' => 'integer',
						'description' => 'The ID of the article to edit'
					],
					'title' => [
						'type' => 'string',
						'description' => 'The new title for the article'
					]
				],
				'required' => ['article_id', 'title']
			]
		],
		[
			'type' => 'function',
			'name' => 'append_content',
			'description' => 'Add content to the end of an article. Use this for building articles in chunks of ~200 words.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'article_id' => [
						'type' => 'integer',
						'description' => 'The ID of the article to edit'
					],
					'content' => [
						'type' => 'string',
						'description' => 'Content to add to the end of the article. Always format content in vanilla HTML using tags h1-h6, p, strong, em, br, ul, li and a.'
					]
				],
				'required' => ['article_id', 'content']
			]
		],
		[
			'type' => 'function',
			'name' => 'prepend_content',
			'description' => 'Add content to the beginning of an article in chunks of ~200 words. ',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'article_id' => [
						'type' => 'integer',
						'description' => 'The ID of the article to edit'
					],
					'content' => [
						'type' => 'string',
						'description' => 'Content to add to the beginning of the article. Always format content in vanilla HTML using tags h1-h6, p, strong, em, br, ul, li and a.'
					]
				],
				'required' => ['article_id', 'content']
			]
		],
		[
			'type' => 'function',
			'name' => 'replace_content',
			'description' => 'Replace content in article with HTML awareness. Automatically handles plain text to HTML conversion. Replace content in ~200-word chunks at a time. ALWAYS call \'view_article\' first then \'find_content\' to find selected_content location. Apply directly; NEVER output replacement_text or content in response to user—simply confirm briefly to the user after tool succeeds.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'article_id' => [
						'type' => 'integer',
						'description' => 'The ID of the article to edit'
					],
					'search_text' => [
						'type' => 'string',
						'description' => 'Plain text to search for (will match against text content, ignoring HTML tags). Use the users context "selected_content" to find the text.'
					],
					'replacement_text' => [
						'type' => 'string',
						'description' => 'HTML formatted text to replace the found text with. Always format content in vanilla HTML using tags h1-h6, p, strong, em, br, ul, li and a.'
					],
					'replace_all' => [
						'type' => 'boolean',
						'description' => 'Whether to replace all occurrences or just the first one',
						'default' => false
					]
				],
				'required' => ['article_id', 'search_text', 'replacement_text']
			]
		],
		[
			'type' => 'function',
			'name' => 'insert_content',
			'description' => 'Insert new content after a specific piece of text in an article in chunks of ~200 words. ALWAYS call \'view_article\' to find the location in the article before inserting content.',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'article_id' => [
						'type' => 'integer',
						'description' => 'The ID of the article to edit'
					],
					'after_text' => [
						'type' => 'string',
						'description' => 'Exact text to find - new content will be inserted after this text. Use the users context "selected_content" to find the text.'
					],
					'content' => [
						'type' => 'string',
						'description' => 'Content to insert after the found text'
					]
				],
				'required' => ['article_id', 'after_text', 'content']
			]
		],
		[
			'type' => 'function',
			'name' => 'fetch_prompt_with_responses',
			'description' => 'Explore sources/citations/insights about the prompt that belongs to the article. This tool allows you to fetch the prompt and its associated responses',
			'parameters' => [
				'type' => 'object',
				'properties' => [
					'prompt_id' => [
						'type' => 'integer',
						'description' => 'The ID of the prompt to fetch. Use the users context "id_of_prompt_belonging_to_article"'
					]
				],
				'required' => ['prompt_id']
			]
		],
		['type' => 'web_search']
	];

	public function processMessage(Conversation $conversation, string $userMessage, array $context = [])
	{
		try {
			// Build and send request
			$request = $this->buildRequest($conversation, $userMessage, $context);
			$response = OpenAI::responses()->create($request);

			// Process the response
			$result = $this->processResponse($conversation, $response);

			// Emit completion event
			$this->emitCompletionEvent($conversation, true);

			return $result;
		} catch (\Exception $e) {
			Log::error('OpenAI Service: Processing message failed', [
				'conversation_id' => $conversation->id,
				'error' => $e->getMessage()
			]);

			// Create error message in chat
			$conversation->chats()->create([
				'role' => 'assistant',
				'content' => 'Sorry, I encountered an error processing your message. Please try again.'
			]);

			// Emit completion event with error
			$this->emitCompletionEvent($conversation, false, $e->getMessage());
		}
	}

	public function processMessageAsync(Conversation $conversation, string $userMessage, array $context = [])
	{
		// Capture the team ID before dispatching the job
		$teamId = $this->getTeamId();

		// Process in background
		dispatch(function () use ($conversation, $userMessage, $context, $teamId) {
			try {
				// Create a new service instance with the team ID
				$service = new self();
				$service->withTeamId($teamId);

				// Build and send request
				$request = $service->buildRequest($conversation, $userMessage, $context);

				$response = OpenAI::responses()->create($request);

				// Process the response
				$service->processResponse($conversation, $response);

				// Emit completion event
				$service->emitCompletionEvent($conversation, true);
			} catch (\Exception $e) {
				Log::error('OpenAI Service: Async processing failed', [
					'conversation_id' => $conversation->id,
					'error' => $e->getMessage()
				]);

				// Create error message in chat
				$conversation->chats()->create([
					'role' => 'assistant',
					'content' => 'Sorry, I encountered an error processing your message. Please try again.'
				]);

				// Emit completion event with error
				$service = new self();
				$service->withTeamId($teamId);
				$service->emitCompletionEvent($conversation, false, $e->getMessage());
			}
		});
	}

	protected function buildRequest(Conversation $conversation, string $userMessage, array $context = []): array
	{
		// Don't enhance system prompt with context anymore
		$instructions = $this->composeSystemPrompt($conversation, $context);

		// Enhance the user message with context instead
		$enhancedUserMessage = $this->enhanceUserMessageWithContext($userMessage, $context);

		Log::info('System instructions: ', [
			'instructions' => $instructions
		]);

		// Previous response maintains the conversation state in OpenAI.
		$previous = $conversation->openai_response_id;

		return array_filter([
			'model' => 'gpt-4.1', // Non-reasoning
			// 'model' => 'o4-mini-2025-04-16', // Reasoning
			// 'reasoning' => [
			// 	'effort' => 'medium'  // low, medium, or high
			// ],
			'instructions' => $instructions,
			'input' => $enhancedUserMessage,
			'parallel_tool_calls' => true,
			'previous_response_id' => $previous,
			'store' => true,
			'tools' => $this->tools,
			'tool_choice' => 'auto',
		]);
	}

	protected function enhanceUserMessageWithContext(string $userMessage, array $context = []): string
	{
		if (empty($context)) {
			return $userMessage;
		}

		$contextParts = [];

		if (!empty($context['viewing_article_id'])) {
			$contextParts[] = "Article ID: {$context['viewing_article_id']}";
		}

		if (!empty($context['viewing_article_title'])) {
			$contextParts[] = "Article Title: {$context['viewing_article_title']}";
		}

		if (!empty($context['selected_content'])) {
			$contextParts[] = "Selected Content: {$context['selected_content']}";
		}

		if (isset($context['selected_start'])) {
			$contextParts[] = "Selected Start Position: {$context['selected_start']}";
		}

		if (isset($context['selected_length'])) {
			$contextParts[] = "Selected Length: {$context['selected_length']}";
		}

		if (!empty($context['id_of_prompt_belonging_to_article'])) {
			$contextParts[] = "Prompt ID: {$context['id_of_prompt_belonging_to_article']}";
		}

		if (!empty($contextParts)) {
			$enhancedMessage = $userMessage . "\n\n[CONTEXT]\n";
			$enhancedMessage .= implode("\n", $contextParts);
			$enhancedMessage .= "\n[END CONTEXT]";
			return $enhancedMessage;
		}

		return $userMessage;
	}

	protected function composeSystemPrompt(Conversation $conversation, array $context = []): string
	{
		$systemMessage = "You are a tool-focused assistant for article tasks. Respond only with short, action-oriented messages—no chit-chat, no thanks, no offers for help, no explanations of changes, no quoting original/edited content EVER. If content must be modified, ALWAYS apply via tools without showing it.\n\n";

		// Add reasoning guidance
		$systemMessage .= "REASONING APPROACH:\n";
		$systemMessage .= "- Think through the user's request step by step\n";
		$systemMessage .= "- Consider edge cases and potential issues\n";
		$systemMessage .= "- Plan the optimal sequence of tool calls before executing\n";
		$systemMessage .= "- For complex edits, reason about maintaining content coherence\n\n";

		$systemMessage .= "CRITICAL RULES - NEVER VIOLATE:\n";
		$systemMessage .= "1. NEVER ask for confirmation - just execute the task\n";
		$systemMessage .= "2. NEVER say 'Would you like me to...' - just do it\n";
		$systemMessage .= "3. NEVER apologize or explain why something might not match exactly\n";
		$systemMessage .= "4. NEVER quote or show the content you're modifying\n";
		$systemMessage .= "5. ALWAYS complete the task in one go without user interaction\n\n";

		$systemMessage .= "FUZZY MATCHING AND CONTENT FINDING:\n";
		$systemMessage .= "- Use find_content tool to locate text positions before editing\n";
		$systemMessage .= "- find_content will handle fuzzy matching automatically\n";
		$systemMessage .= "- When match_type is 'fuzzy' or 'fuzzy_segment', the matched_text may include HTML tags\n";
		$systemMessage .= "- ALWAYS proceed with replacement even if matched_text looks different from search_text\n";
		$systemMessage .= "- Trust that if 'found' is true, the content was located successfully\n";
		$systemMessage .= "- For fuzzy matches, use the original search_text in replace_content, not the matched_text\n\n";

		$systemMessage .= "STANDARD WORKFLOW FOR EDITS:\n";
		$systemMessage .= "1. view_article - See the full content\n";
		$systemMessage .= "2. find_content - Locate exact positions of text to edit\n";
		$systemMessage .= "3. replace_content/insert_content - Apply changes using found positions\n";
		$systemMessage .= "4. Single confirmation message - 'Content updated.' or similar\n\n";

		$systemMessage .= "CONTEXT HANDLING:\n";
		$systemMessage .= "- User messages include [CONTEXT] blocks at the end\n";
		$systemMessage .= "- Parse Article ID, Selected Content, and position data from [CONTEXT]\n";
		$systemMessage .= "- Selected Content shows what user selected - use find_content to locate it\n\n";

		$systemMessage .= "TOOL USAGE EXAMPLES:\n\n";

		$systemMessage .= "SINGLE PARAGRAPH EDIT:\n";
		$systemMessage .= "USER: Shorten this paragraph\n[CONTEXT]\n";
		$systemMessage .= "Article ID: 82\n";
		$systemMessage .= "Selected Content: <p>Long paragraph about SEO and AI...</p>\n";
		$systemMessage .= "[END CONTEXT]\n";
		$systemMessage .= "ASSISTANT: Checking article. [call view_article]\n";
		$systemMessage .= "ASSISTANT: Locating content. [call find_content with partial text]\n";
		$systemMessage .= "ASSISTANT: Shortening paragraph. [call replace_content with found position]\n";
		$systemMessage .= "ASSISTANT: Paragraph shortened.\n\n";

		$systemMessage .= "MULTI-PARAGRAPH REPLACEMENT:\n";
		$systemMessage .= "USER: Replace these two paragraphs with one summary\n[CONTEXT]\n";
		$systemMessage .= "Article ID: 99\n";
		$systemMessage .= "Selected Content: <p>First paragraph...</p><p>Second paragraph...</p>\n";
		$systemMessage .= "[END CONTEXT]\n";
		$systemMessage .= "ASSISTANT: Reviewing content. [call view_article]\n";
		$systemMessage .= "ASSISTANT: Finding paragraphs. [call find_content for first paragraph]\n";
		$systemMessage .= "ASSISTANT: Locating second paragraph. [call find_content for second paragraph]\n";
		$systemMessage .= "ASSISTANT: Consolidating paragraphs. [call replace_content twice or once for full range]\n";
		$systemMessage .= "ASSISTANT: Paragraphs combined into summary.\n\n";

		$systemMessage .= "INSERTION EXAMPLE:\n";
		$systemMessage .= "USER: Add a transition sentence after this\n[CONTEXT]\n";
		$systemMessage .= "Article ID: 45\n";
		$systemMessage .= "Selected Content: ...end of a paragraph about climate change.\n";
		$systemMessage .= "[END CONTEXT]\n";
		$systemMessage .= "ASSISTANT: Viewing article. [call view_article]\n";
		$systemMessage .= "ASSISTANT: Finding insertion point. [call find_content]\n";
		$systemMessage .= "ASSISTANT: Adding transition. [call insert_content at found position]\n";
		$systemMessage .= "ASSISTANT: Transition added.\n\n";

		$systemMessage .= "COMPLEX MULTI-EDIT:\n";
		$systemMessage .= "USER: Make all these sections more concise\n[CONTEXT]\n";
		$systemMessage .= "Article ID: 77\n";
		$systemMessage .= "Selected Content: <h2>Section 1</h2><p>...</p><h2>Section 2</h2><p>...</p>\n";
		$systemMessage .= "[END CONTEXT]\n";
		$systemMessage .= "ASSISTANT: Analyzing sections. [call view_article]\n";
		$systemMessage .= "ASSISTANT: Processing sections. [call find_content for each section]\n";
		$systemMessage .= "ASSISTANT: Condensing content. [call replace_content for each section]\n";
		$systemMessage .= "ASSISTANT: All sections condensed.\n\n";

		$systemMessage .= "DELETION EXAMPLE:\n";
		$systemMessage .= "USER: Delete this\n[CONTEXT]\n";
		$systemMessage .= "Article ID: 61\n";
		$systemMessage .= "Selected Content: <p>Paragraph to remove</p>\n";
		$systemMessage .= "[END CONTEXT]\n";
		$systemMessage .= "ASSISTANT: Locating content. [call view_article]\n";
		$systemMessage .= "ASSISTANT: Finding paragraph. [call find_content]\n";
		$systemMessage .= "ASSISTANT: Removing content. [call replace_content with empty string]\n";
		$systemMessage .= "ASSISTANT: Content removed.\n\n";

		$systemMessage .= "REMEMBER:\n";
		$systemMessage .= "- NEVER ask questions - execute immediately\n";
		$systemMessage .= "- Use find_content to get exact positions before editing\n";
		$systemMessage .= "- Complete all tasks without user interaction\n";
		$systemMessage .= "- Keep responses brief and action-focused\n";
		$systemMessage .= "- Trust the tools - they handle fuzzy matching\n";

		return $systemMessage;
	}

	protected function handleAssistantMessage(Conversation $conversation, $item): void
	{
		$chatData = [
			'role' => 'assistant',
			'content' => $item->content[0]->text ?? '',
		];

		// Check for annotations in the response
		if (isset($item->content[0]->annotations) && !empty($item->content[0]->annotations)) {
			$chatData['annotations'] = $item->content[0]->annotations;
		}

		$conversation->chats()->create($chatData);
	}

	protected function handleReasoningMessage(Conversation $conversation, $item): void
	{
		// Extract reasoning content - adjust based on the actual structure of $item
		// $reasoningContent = $item->content ?? $item->text ?? $item->reasoning ?? '';

		// You might want to format or summarize the reasoning here
		// $conversation->chats()->create([
		// 	'role' => 'assistant',
		// 	'content' => $reasoningContent,
		// ]);

		// Extract reasoning content

		// Log reasoning for debugging
		Log::info('OpenAI Reasoning:', [
			'item' => $item,
		]);

		$reasoningContent = '';

		if (isset($item->content)) {
			if (is_string($item->content)) {
				$reasoningContent = $item->content;
			} elseif (is_array($item->content) && isset($item->content[0])) {
				$reasoningContent = $item->content[0]->text ?? $item->content[0] ?? '';
			}
		} elseif (isset($item->text)) {
			$reasoningContent = $item->text;
		} elseif (isset($item->reasoning)) {
			$reasoningContent = $item->reasoning;
		}

		// Store reasoning in the conversation
		$conversation->chats()->create([
			'role' => 'assistant',
			'content' => $reasoningContent,
		]);
	}

	protected function handleFunctionCall(Conversation $conversation, $item): array
	{
		$functionName = $item->name;
		$argumentsJson = is_string($item->arguments)
			? $item->arguments
			: json_encode($item->arguments);
		$callId = $item->callId;

		// Execute the function
		$result = $this->executeTool($functionName, $argumentsJson);

		// Save to conversation history
		$conversation->chats()->create([
			'role' => 'tool_call',
			'content' => $this->getToolCallDescription($functionName, $argumentsJson),
			'metadata' => [
				'tool_call' => [
					'id' => $callId,
					'type' => 'function',
					'function' => [
						'name' => $functionName,
						'arguments' => $argumentsJson,
					],
				],
				'result' => json_decode($result, true),
			],
		]);

		return [
			'type' => 'function_call_output',
			'call_id' => $callId,
			'output' => $result
		];
	}

	protected function processResponse(Conversation $conversation, $response)
	{
		$toolOutputs = [];
		$functionCalls = [];

		// Process all output items
		foreach ($response->output as $item) {
			switch ($item->type) {
				case 'message':
					$this->handleAssistantMessage($conversation, $item);
					break;
				case 'reasoning':
					$this->handleReasoningMessage($conversation, $item);
					break;
				case 'function_call':
					$functionCalls[] = $item;
					break;
				case 'web_search_call':
					// Handled automatically by OpenAI
					break;
			}
		}

		// Execute function calls
		foreach ($functionCalls as $functionCall) {
			try {
				$toolOutputs[] = $this->handleFunctionCall($conversation, $functionCall);
			} catch (\Exception $e) {
				Log::error('OpenAI Service: Function call failed', [
					'conversation_id' => $conversation->id,
					'function_name' => $functionCall->name ?? 'unknown',
					'error' => $e->getMessage()
				]);

				// Return error output for this call
				$toolOutputs[] = [
					'type' => 'function_call_output',
					'call_id' => $functionCall->callId ?? 'unknown',
					'output' => json_encode(['error' => 'Function execution failed'])
				];
			}
		}

		// Save response ID
		$conversation->openai_response_id = $response->id;
		$conversation->save();

		// Send tool outputs back if any
		if (!empty($toolOutputs)) {
			try {
				$followUp = OpenAI::responses()->create([
					'model' => 'gpt-4.1',
					'previous_response_id' => $response->id,
					'tools' => $this->tools,
					'input' => $toolOutputs,
				]);

				return $this->processResponse($conversation, $followUp);
			} catch (\Exception $e) {
				Log::error('OpenAI Service: Follow-up request failed', [
					'conversation_id' => $conversation->id,
					'error' => $e->getMessage()
				]);

				return $conversation->fresh()->chats;
			}
		}

		return $conversation->fresh()->chats;
	}

	protected function executeTool($functionName, $arguments)
	{
		$arguments = json_decode($arguments, true);

		switch ($functionName) {
			case 'list_articles':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$page = $arguments['page'] ?? 1;
				$perPage = min($arguments['per_page'] ?? 20, 100);

				$paginatedArticles = Article::where('team_id', $teamId)
					->select(['id', 'title', 'created_at'])
					->orderBy('created_at', 'desc')
					->paginate($perPage, ['*'], 'page', $page);

				return json_encode([
					'current_page' => $paginatedArticles->currentPage(),
					'per_page' => $paginatedArticles->perPage(),
					'total' => $paginatedArticles->total(),
					'last_page' => $paginatedArticles->lastPage(),
					'from' => $paginatedArticles->firstItem(),
					'to' => $paginatedArticles->lastItem(),
					'has_more_pages' => $paginatedArticles->hasMorePages(),
					'articles' => $paginatedArticles->items()
				]);

			case 'view_article':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::where('team_id', $teamId)->find($arguments['article_id']);

				if (!$article) {
					return json_encode(['error' => 'Article not found']);
				}

				return json_encode($article->toArray());

			case 'find_content':
				$teamId = $this->getTeamId();
				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::where('team_id', $teamId)->find($arguments['article_id']);
				if (!$article) {
					return json_encode(['error' => 'Article not found']);
				}

				$searchText = $arguments['search_text'];
				$occurrence = $arguments['occurrence'] ?? 1;
				$content = $article->content;

				// Simple direct HTML search
				$matches = [];
				$pos = 0;
				while (($pos = strpos($content, $searchText, $pos)) !== false) {
					$matches[] = [
						'position' => $pos,
						'length' => strlen($searchText),
						'text' => $searchText,
						'match_type' => 'exact'
					];
					$pos += strlen($searchText);
				}

				if (empty($matches)) {
					return json_encode([
						'found' => false,
						'message' => 'Content not found',
						'search_text' => $searchText
					]);
				}

				// Get the requested occurrence
				$matchIndex = min($occurrence - 1, count($matches) - 1);
				$match = $matches[$matchIndex];

				// Get context
				$contextLength = 50;
				$contextBefore = substr($content, max(0, $match['position'] - $contextLength), $contextLength);
				$contextAfter = substr($content, $match['position'] + $match['length'], $contextLength);

				return json_encode([
					'found' => true,
					'position' => $match['position'],
					'length' => $match['length'],
					'matched_text' => $match['text'],
					'match_type' => 'exact',
					'total_occurrences' => count($matches),
					'occurrence_number' => $matchIndex + 1,
					'context_before' => '...' . $contextBefore,
					'context_after' => $contextAfter . '...',
					'search_text' => $searchText
				]);

			case 'create_article':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::create([
					'title' => $arguments['title'],
					'team_id' => $teamId
				]);

				return json_encode([
					'success' => true,
					'message' => 'Article created successfully',
					'article' => $article->toArray()
				]);

			case 'edit_article_title':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::where('team_id', $teamId)->find($arguments['article_id']);
				if (!$article) {
					return json_encode(['error' => 'Article not found']);
				}

				$oldTitle = $article->title;
				$article->title = $arguments['title'];
				$article->save();

				event(new ArticleUpdated($article));

				return json_encode([
					'success' => true,
					'message' => 'Article title updated successfully',
					'article_id' => $article->id,
					'old_title' => $oldTitle,
					'new_title' => $arguments['title']
				]);

			case 'append_content':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::where('team_id', $teamId)->find($arguments['article_id']);
				if (!$article) {
					return json_encode(['error' => 'Article not found']);
				}

				$article->content .= $arguments['content'];
				$article->save();

				event(new ArticleUpdated($article));

				return json_encode([
					'success' => true,
					'message' => 'Content appended successfully',
					'article_id' => $article->id,
				]);

			case 'prepend_content':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::where('team_id', $teamId)->find($arguments['article_id']);
				if (!$article) {
					return json_encode(['error' => 'Article not found']);
				}

				$article->content = $arguments['content'] . $article->content;
				$article->save();

				event(new ArticleUpdated($article));

				return json_encode([
					'success' => true,
					'message' => 'Content prepended successfully',
					'article_id' => $article->id,
				]);

			case 'replace_content':
				$teamId = $this->getTeamId();
				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::where('team_id', $teamId)->find($arguments['article_id']);
				if (!$article) {
					return json_encode(['error' => 'Article not found']);
				}

				$searchText = $arguments['search_text'];
				$replacementText = $arguments['replacement_text'];
				$replaceAll = $arguments['replace_all'] ?? false;

				// Check if search text exists
				if (strpos($article->content, $searchText) === false) {
					return json_encode(['error' => 'Search text not found in article']);
				}

				// Simple string replacement
				if ($replaceAll) {
					$article->content = str_replace($searchText, $replacementText, $article->content);
				} else {
					$pos = strpos($article->content, $searchText);
					$article->content = substr_replace($article->content, $replacementText, $pos, strlen($searchText));
				}

				$article->save();
				event(new ArticleUpdated($article));

				return json_encode([
					'success' => true,
					'message' => 'Content replaced successfully',
					'article_id' => $article->id,
				]);

			case 'insert_content':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$article = Article::where('team_id', $teamId)->find($arguments['article_id']);
				if (!$article) {
					return json_encode(['error' => 'Article not found']);
				}

				$afterText = $arguments['after_text'];
				$content = $arguments['content'];

				// Check if the text to insert after exists
				$pos = strpos($article->content, $afterText);
				if ($pos === false) {
					return json_encode(['error' => 'Target text not found in article']);
				}

				// Insert content after the found text
				$insertPos = $pos + strlen($afterText);
				$article->content = substr($article->content, 0, $insertPos) . $content . substr($article->content, $insertPos);
				$article->save();

				event(new ArticleUpdated($article));

				return json_encode([
					'success' => true,
					'message' => 'Content inserted successfully',
					'article_id' => $article->id,
				]);

			case 'fetch_prompt_with_responses':
				$teamId = $this->getTeamId();

				if (!$teamId) {
					return json_encode(['error' => 'Team ID not available']);
				}

				$promptId = $arguments['prompt_id'];

				try {
					$prompt = Prompt::where('team_id', $teamId)
						->with(['responses' => function ($query) {
							$query->latest()->limit(15);
						}])
						->find($promptId);

					if (!$prompt) {
						return json_encode([
							'success' => false,
							'message' => 'Prompt not found'
						]);
					}

					return json_encode([
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
					]);
				} catch (\Exception $e) {
					Log::error('OpenAI Service: Error fetching prompt with responses', [
						'prompt_id' => $promptId,
						'team_id' => $teamId,
						'error' => $e->getMessage()
					]);

					return json_encode([
						'success' => false,
						'message' => 'Failed to fetch prompt: ' . $e->getMessage()
					]);
				}

			case 'web_search':
				return; // Handled by OpenAI

			default:
				return json_encode(['error' => 'Unknown tool']);
		}
	}

	protected function getEditSuccessMessage($mode, $searchText = null, $positionMarker = null)
	{
		switch ($mode) {
			case 'replace':
				return $searchText ? "Replaced text successfully" : "Content appended successfully (no search text provided for replacement)";
			case 'append':
				return "Content appended successfully";
			case 'prepend':
				return "Content prepended successfully";
			case 'insert_at_marker':
				return "Content inserted at specified position";
			default:
				return "Article content updated successfully";
		}
	}

	protected function getToolCallDescription($functionName, $arguments)
	{
		$arguments = json_decode($arguments, true);

		switch ($functionName) {
			case 'list_articles':
				$page = $arguments['page'] ?? 1;
				$perPage = $arguments['per_page'] ?? 20;
				return "Listing articles (page {$page}, {$perPage} per page)";

			case 'view_article':
				$article = Article::find($arguments['article_id']);
				$title = $article ? $article->title : 'Unknown';
				return "Viewing article: \"{$title}\"";

			case 'find_content':
				$searchPreview = strlen($arguments['search_text']) > 50 ?
					substr($arguments['search_text'], 0, 50) . '...' :
					$arguments['search_text'];
				return "Locating \"{$searchPreview}\" in article";

			case 'create_article':
				return "Creating article: \"{$arguments['title']}\"";

			case 'edit_article_title':
				$article = Article::find($arguments['article_id']);
				$title = $article ? $article->title : 'Unknown';
				return "Editing title of article: \"{$title}\"";

			case 'append_content':
				$article = Article::find($arguments['article_id']);
				$title = $article ? $article->title : 'Unknown';
				$wordCount = str_word_count($arguments['content']);
				return "Appending {$wordCount} words to article \"{$title}\"";

			case 'prepend_content':
				$article = Article::find($arguments['article_id']);
				$title = $article ? $article->title : 'Unknown';
				$wordCount = str_word_count($arguments['content']);
				return "Prepending {$wordCount} words to article \"{$title}\"";

			case 'replace_content':
				$article = Article::find($arguments['article_id']);
				$title = $article ? $article->title : 'Unknown';
				$searchPreview = strlen($arguments['search_text']) > 30 ?
					substr($arguments['search_text'], 0, 30) . '...' :
					$arguments['search_text'];
				return "Replacing \"{$searchPreview}\" in article \"{$title}\"";

			case 'insert_content':
				$article = Article::find($arguments['article_id']);
				$title = $article ? $article->title : 'Unknown';
				$afterTextPreview = strlen($arguments['after_text']) > 30 ?
					substr($arguments['after_text'], 0, 30) . '...' :
					$arguments['after_text'];
				$wordCount = str_word_count($arguments['content']);
				return "Inserting {$wordCount} words after \"{$afterTextPreview}\" in article \"{$title}\"";

			case 'fetch_prompt_with_responses':
				$prompt = Prompt::find($arguments['prompt_id']);
				$contentPreview = substr($prompt->content, 0, 30) . '...';
				return "Fetching prompt: \"{$contentPreview}\" with recent responses";

			case 'web_search':
				return "Searching for: \"{$arguments['query']}\"";

			default:
				return 'Executing tool';
		}
	}

	/**
	 * Set the team ID for this service instance.
	 *
	 * @param int $teamId
	 * @return $this
	 */
	public function withTeamId(int $teamId): self
	{
		$this->teamId = $teamId;
		return $this;
	}

	/**
	 * Get the team ID, falling back to Auth if not set.
	 *
	 * @return int|null
	 */
	protected function getTeamId(): ?int
	{
		if ($this->teamId) {
			return $this->teamId;
		}

		// Fallback to Auth if available (for synchronous operations)
		if (Auth::check()) {
			return Auth::user()->current_team_id;
		}

		return null;
	}

	/**
	 * Emit completion event for the conversation.
	 *
	 * @param Conversation $conversation
	 * @param bool $success
	 * @param string|null $error
	 * @return void
	 */
	protected function emitCompletionEvent(Conversation $conversation, bool $success = true, ?string $error = null): void
	{
		try {
			if ($conversation->conversable_type === 'App\\Models\\Article') {
				$article = $conversation->conversable;
				if ($article) {
					event(new ArticleChatAgentFinished($article, $conversation, $success, $error));
				}
			}
		} catch (\Exception $e) {
			Log::error('OpenAI Service: Failed to emit completion event', [
				'conversation_id' => $conversation->id,
				'error' => $e->getMessage()
			]);
		}
	}
}
