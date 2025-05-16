<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Website $website)
    {
        $pages = $website->pages()->latest()->get();
        
        return response()->json($pages);
    }

    public function store(Request $request, Website $website)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|string',
            'summary' => 'required|string',
            'llm_text' => 'nullable|string',
        ]);
        
        $page = $website->pages()->create($validated);
        
        return response()->json($page);
    }

    public function show(Website $website, Page $page)
    {
        return response()->json($page);
    }

    public function update(Request $request, Website $website, Page $page)
    {        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'url' => 'sometimes|required|string',
            'summary' => 'sometimes|required|string',
            'llm_text' => 'nullable|string',
        ]);
        
        $page->update($validated);
        
        return response()->json($page);
    }

    public function destroy(Website $website, Page $page)
    {   
        $page->delete();
        
        return response()->json($page);
    }
}
