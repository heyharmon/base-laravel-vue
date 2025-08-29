<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\TeamUsageService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SuperAdminTeamUsageController extends Controller
{
    public function index(TeamUsageService $service)
    {
        $teams = Team::all()->map(function ($team) use ($service) {
            $usage = $service->currentMonthCost($team);
            return [
                'id' => $team->id,
                'name' => $team->name,
                'usage_cost' => $usage,
                'limit_cost' => $team->token_limit_cost,
                'remaining_cost' => is_null($team->token_limit_cost)
                    ? null
                    : max($team->token_limit_cost - $usage, 0),
            ];
        });

        return response()->json($teams);
    }

    public function show(Request $request, Team $team, TeamUsageService $service)
    {
        $month = $request->query('month');
        $start = $month
            ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
            : Carbon::now()->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $usage = $service->usageForPeriod($team, $start, $end);

        // Days until reset should always be based on the current month
        // and return a non-negative integer number of days remaining.
        $daysUntilReset = $start->isSameMonth(Carbon::now())
            ? Carbon::now()->startOfDay()->diffInDays(Carbon::now()->endOfMonth()->addDay()->startOfDay())
            : 0;

        return response()->json([
            'team' => ['id' => $team->id, 'name' => $team->name],
            'period' => [
                'start' => $start->toDateString(),
                'end' => $end->toDateString(),
                'days_until_reset' => $daysUntilReset,
            ],
            'usage' => $usage,
            'limit_cost' => $team->token_limit_cost,
        ]);
    }

    public function update(Request $request, Team $team)
    {
        $data = $request->validate([
            'token_limit_cost' => 'nullable|numeric',
            'token_limit_price' => 'nullable|numeric',
        ]);

        $team->update($data);

        return response()->json($team->fresh());
    }
}
