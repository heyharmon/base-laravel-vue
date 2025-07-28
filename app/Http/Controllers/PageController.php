<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Page $page): View
    {
        $page->load(['contentBlocks' => function ($query) {
            $query->orderBy('sort_order');
        }]);

        return view('pages.show', compact('page'));
    }
}
