<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function index(): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;
        
        $conversations = Conversation::where('team_id', $teamId)
            ->latest()
            ->get();
        
        return response()->json($conversations);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);
        
        $conversation = request()->user()->currentTeam->conversations()->create($validated);
        
        return response()->json($conversation, 201);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        // Ensure the conversation belongs to the current team
        if ($conversation->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        return response()->json($conversation);
    }

    public function update(Request $request, Conversation $conversation): JsonResponse
    {        
        // Ensure the conversation belongs to the current team
        if ($conversation->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);
        
        $conversation->update($validated);
        
        return response()->json($conversation);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {   
        // Ensure the conversation belongs to the current team
        if ($conversation->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $conversation->delete();
        
        return response()->json(null, 204);
    }
}
