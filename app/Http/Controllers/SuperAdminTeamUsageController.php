<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Team;
use Carbon\Carbon;

class SuperAdminTeamUsageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $startDate = $start ? Carbon::parse($start) : now()->startOfMonth();
        $endDate = $end ? Carbon::parse($end) : now()->endOfMonth();

        $teams = Team::all()->map(function ($team) use ($startDate, $endDate) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'responses_used' => $team->responsesUsed($startDate, $endDate),
                'responses_limit' => $team->responses_limit,
                'articles_used' => $team->articlesUsed($startDate, $endDate),
                'articles_limit' => $team->articles_limit,
            ];
        });

        return response()->json($teams);
    }

    public function show(Request $request, Team $team): JsonResponse
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $startDate = $start ? Carbon::parse($start) : now()->startOfMonth();
        $endDate = $end ? Carbon::parse($end) : now()->endOfMonth();

        return response()->json([
            'team' => $team,
            'usage' => [
                'responses_used' => $team->responsesUsed($startDate, $endDate),
                'responses_limit' => $team->responses_limit,
                'responses_remaining' => $team->responsesRemaining($startDate, $endDate),
                'articles_used' => $team->articlesUsed($startDate, $endDate),
                'articles_limit' => $team->articles_limit,
                'articles_remaining' => $team->articlesRemaining($startDate, $endDate),
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
            ]
        ]);
    }

    public function update(Request $request, Team $team): JsonResponse
    {
        $validated = $request->validate([
            'responses_limit' => 'nullable|integer|min:0',
            'articles_limit' => 'nullable|integer|min:0',
        ]);

        $team->update($validated);

        return response()->json($team);
    }
}
