<?php

namespace App\Http\Controllers;

use App\Models\Website;
use App\Models\PageView;
use App\Services\DomainNormalizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class WebsiteController extends Controller
{
    public function index()
    {
        $websites = Auth::user()->websites()->get();
        return response()->json($websites);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'domain' => 'required|string',
        ]);
        $data['domain'] = DomainNormalizer::normalize($data['domain']);
        $website = Auth::user()->websites()->create($data);
        return response()->json(['website' => $website], 201);
    }

    public function show(Request $request, Website $website)
    {
        if ($website->user_id !== Auth::id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $start = Carbon::parse($request->query('start', now()->subDays(7)));
        $end = Carbon::parse($request->query('end', now()));

        $viewsQuery = PageView::where('normalized_domain', $website->domain)
            ->whereBetween('created_at', [$start, $end])
            ->where('is_llm', true);

        $total = $viewsQuery->count();
        $byAgent = $viewsQuery->select('user_agent', DB::raw('count(*) as count'))
            ->groupBy('user_agent')
            ->orderByDesc('count')
            ->get();

        $embedCode = sprintf('<script src="%s/llm-tracker.js" defer></script>', config('app.url'));

        return response()->json([
            'website' => $website,
            'embed_code' => $embedCode,
            'stats' => [
                'total' => $total,
                'by_agent' => $byAgent,
            ],
        ]);
    }
}
