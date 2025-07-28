<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePageRequest;
use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        $pages = Page::orderByDesc('created_at')->get();

        return view('admin.pages.index', compact('pages'));
    }

    public function create(): View
    {
        return view('admin.pages.create');
    }

    public function store(StorePageRequest $request): RedirectResponse
    {
        $page = Page::create($request->validated());

        foreach ($request->input('content_blocks', []) as $index => $block) {
            $page->contentBlocks()->create([
                'block_type' => $block['type'],
                'block_data' => $block['data'],
                'sort_order' => $index,
            ]);
        }

        return redirect()->route('admin.pages.index')->with('success', 'Page created successfully.');
    }
}
