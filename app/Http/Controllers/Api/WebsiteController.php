<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CheckBotAccessibilityJob;
use App\Models\Website;
use Illuminate\Http\Request;

class WebsiteController extends Controller
{
    public function index()
    {
        return Website::orderBy('created_at','desc')->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'domain' => 'required|string',
            'base_url' => 'required|url',
        ]);
        $data['protocol'] = parse_url($data['base_url'], PHP_URL_SCHEME) ?: 'https';
        $website = Website::create($data);
        return response()->json($website, 201);
    }

    public function checkBots(Website $website)
    {
        CheckBotAccessibilityJob::dispatch($website);
        return response()->json(['message'=>'Bot accessibility check initiated']);
    }

    public function results(Website $website)
    {
        return $website->accessibilityResults()->with('userAgent')->orderBy('checked_at','desc')->get();
    }
}
