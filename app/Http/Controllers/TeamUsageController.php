<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Team;
use Carbon\Carbon;

class TeamUsageController extends Controller
{
    public function show(Request $request, Team $team): JsonResponse
    {
        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $startDate = $start ? Carbon::parse($start) : now()->startOfMonth();
        $endDate = $end ? Carbon::parse($end) : now()->endOfMonth();

        return response()->json([
            'responses_used' => $team->responsesUsed($startDate, $endDate),
            'responses_limit' => $team->responses_limit,
            'responses_remaining' => $team->responsesRemaining($startDate, $endDate),
            'articles_used' => $team->articlesUsed($startDate, $endDate),
            'articles_limit' => $team->articles_limit,
            'articles_remaining' => $team->articlesRemaining($startDate, $endDate),
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
        ]);
    }
}
