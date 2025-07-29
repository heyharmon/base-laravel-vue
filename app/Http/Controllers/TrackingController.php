<?php

namespace App\Http\Controllers;

use App\Models\PageView;
use App\Services\AgentDetector;
use App\Services\DomainNormalizer;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function track(Request $request)
    {
        $data = $request->validate([
            'domain' => 'required|string',
            'path' => 'required|string',
        ]);

        $normalized = DomainNormalizer::normalize($data['domain']);
        $agent = $request->header('User-Agent');

        $view = PageView::create([
            'normalized_domain' => $normalized,
            'path' => $data['path'],
            'user_agent' => $agent ?? '',
            'is_llm' => AgentDetector::isLLMAgent($agent),
        ]);

        return response()->json(['id' => $view->id], 201);
    }
}
