<?php

namespace App\Http\Controllers;

use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WebsiteController extends Controller
{
    use AuthorizesRequests;

    public function index()
    {
        $websites = Website::orderBy('domain')->get();
        
        return response()->json($websites);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
        ]);
        
        $website = Website::create($validated);
        
        return response()->json($website);
    }

    public function show(Website $website)
    {
        return response()->json($website);
    }

    public function update(Request $request, Website $website)
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'description' => 'nullable|string',
            'outline' => 'nullable|string',
        ]);
        
        $website->update($validated);
        
        return response()->json($website);
    }

    public function destroy(Website $website)
    {
        $website->delete();
        
        return response()->json($website);
    }
}
