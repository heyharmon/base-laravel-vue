<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\TeamUsageService;
use Illuminate\Http\Request;

class TeamUsageController extends Controller
{
    public function show(Request $request, Team $team, TeamUsageService $service)
    {
        // Optional: ensure user belongs to team
        if (!$request->user()->teams()->where('team_id', $team->id)->exists()) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'usage_cost' => $service->currentMonthCost($team),
            'usage_price' => $service->currentMonthPrice($team),
            'limit_cost' => $team->token_limit_cost,
            'limit_price' => $team->token_limit_price,
        ]);
    }
}
