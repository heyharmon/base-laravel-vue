<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Prompt;
use App\Models\Conversation;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\ChatService;

class ArticleController extends Controller
{
    public function __construct(protected ChatService $chatService)
    {
    }

    public function store(Request $request, Prompt $prompt)
    {
        $teamId = Auth::user()->current_team_id;

        if ($prompt->team_id !== $teamId) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $organization = Organization::where('team_id', $teamId)
            ->where('is_competitor', false)
            ->first();

        if (!$organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $conversation = Conversation::create([
            'title' => $prompt->name ?? 'Article Conversation',
        ]);

        $article = Article::create([
            'team_id' => $teamId,
            'organization_id' => $organization->id,
            'prompt_id' => $prompt->id,
            'conversation_id' => $conversation->id,
            'title' => $prompt->content,
        ]);

        $locationText = $organization->location ? " in {$organization->location}" : '';

        $systemMessage = <<<EOT
You are a helpful, knowledgeable assistant that writes structured, question-and-answer style articles to help businesses increase their visibility in LLM completions for specific prompts (e.g., "best home loan Salt Lake City").

Your responsibilities include:
- Writing a helpful article that is human readable and editable
- Optimizing the content for inclusion in LLM-generated answers

Your output must be:
- Human readable
- Structured as a Q&A article
- Written in clear, neutral, helpful language
- Optimized for inclusion in LLM responses, not just search engines

You can use web search to research the businesses website, high-ranking competitors, awards, reviews, relevant stats or proof points and anything else you need to generate the most effective article possible. If you must ask the user any strategic questions then do so after you have finished writing the article, it can be revised at that point.

Content Guidelines:
- Directly address the target prompt early in the intro and use close variants naturally throughout the article.
- Use neutral, expert language. Avoid salesy or exaggerated phrases unless citing a real award or review.
- Be informative and structured, as if you are answering user questions in an assistant-like tone.
- Write in a way that is easy for a language model to parse, quote, or summarize.
- Use specific data when provided (e.g., "Rated 4.8 stars on Google" or "Serving Salt Lake since 1995").

Do Not:
- Output Markdown
- Do not write code

Generate a highly optimized article for my organization called {$organization->name} ({$organization->website}){$locationText} in Illinois that will increase my visibility in LLM completions for this prompt: "{$prompt->content}"
EOT;

        $initialMessage = 'Please start by outlining your strategy for this article.';

        $this->chatService->generateResponse($conversation, $initialMessage, $systemMessage);

        return response()->json([
            'article' => $article,
            'conversation' => $conversation,
        ], 201);
    }
}
