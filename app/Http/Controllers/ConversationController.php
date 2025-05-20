<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function index()
    {
        $conversations = Conversation::latest()->get();
        
        return response()->json($conversations);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);
        
        $conversation = Conversation::create($validated);
        
        return response()->json($conversation);
    }

    public function show(Conversation $conversation)
    {
        return response()->json($conversation);
    }

    public function update(Request $request, Conversation $conversation)
    {        
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
        ]);
        
        $conversation->update($validated);
        
        return response()->json($conversation);
    }

    public function destroy(Conversation $conversation)
    {   
        $conversation->delete();
        
        return response()->json($conversation);
    }
}
