<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Website;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function index(Website $website)
    {
        $pages = $website->pages()->get();
        
        return response()->json($pages);
    }

    public function store(Request $request, Website $website)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'outline' => 'nullable|string',
        ]);
        
        $page = $website->pages()->create($validated);
        
        return response()->json($page);
    }

    public function show(Website $website, Page $page)
    {   
        return response()->json($page);
    }

    public function update(Website $website, Page $page, Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'outline' => 'nullable|string',
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
