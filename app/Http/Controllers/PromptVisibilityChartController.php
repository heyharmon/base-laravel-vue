<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\Organization;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PromptVisibilityChartController extends Controller
{
    /**
     * Get visibility data over time for a specific prompt
     */
    public function chartData(Request $request, Prompt $prompt)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'interval' => 'nullable|in:daily,weekly,monthly',
            'timezone' => 'nullable|timezone'
        ]);

        $teamId = $prompt->team_id;
        $campaignId = $prompt->campaign_id;

        $timezone = $request->input('timezone', 'UTC');

        // Handle date range
        if (!$request->start_date || !$request->end_date) {
            $firstResponse = $prompt->responses()->orderBy('created_at')->first();
            $lastResponse = $prompt->responses()->orderBy('created_at', 'desc')->first();

            if (!$firstResponse || !$lastResponse) {
                return response()->json([
                    'organizations' => [],
                    'interval' => $request->input('interval', 'daily'),
                    'start_date' => null,
                    'end_date' => null
                ]);
            }

            $startDateUser = Carbon::parse($firstResponse->created_at)->setTimezone($timezone)->startOfDay();
            $endDateUser = Carbon::parse($lastResponse->created_at)->setTimezone($timezone)->endOfDay();
        } else {
            $startDateUser = Carbon::parse($request->start_date, $timezone)->startOfDay();
            $endDateUser = Carbon::parse($request->end_date, $timezone)->endOfDay();
        }

        $startDate = $startDateUser->copy()->setTimezone('UTC');
        $endDate = $endDateUser->copy()->setTimezone('UTC');

        $interval = $request->input('interval', 'daily');

        // Get the owned organization
        $organizations = Organization::where('team_id', $teamId)
            ->forCampaign($campaignId)
            ->where('is_competitor', false)
            ->get();

        // Generate date intervals
        $intervals = $this->generateIntervals($startDateUser, $endDateUser, $interval);

        $chartData = [];

        foreach ($organizations as $organization) {
            $dataPoints = [];

            // Get all term IDs for this organization
            $termIds = Term::where('organization_id', $organization->id)
                ->pluck('id')
                ->toArray();

            foreach ($intervals as $intervalData) {
                $intervalStart = $intervalData['start'];
                $intervalEnd = $intervalData['end'];
                $intervalStartUtc = $intervalStart->copy()->setTimezone('UTC');
                $intervalEndUtc = $intervalEnd->copy()->setTimezone('UTC');

                // Calculate visibility for this interval FOR THIS SPECIFIC PROMPT
                $totalResponses = $prompt->responses()
                    ->whereBetween('created_at', [$intervalStartUtc, $intervalEndUtc])
                    ->count();

                $totalMentions = 0;
                if (!empty($termIds) && $totalResponses > 0) {
                    $totalMentions = $prompt->responses()
                        ->whereHas('terms', function ($query) use ($termIds) {
                            $query->whereIn('terms.id', $termIds);
                        })
                        ->whereBetween('created_at', [$intervalStartUtc, $intervalEndUtc])
                        ->count();
                }

                $visibility = $totalResponses > 0
                    ? round(($totalMentions / $totalResponses) * 100, 2)
                    : 0;

                $dataPoints[] = [
                    'date' => $intervalData['label'],
                    'visibility' => $visibility,
                    'mentions' => $totalMentions,
                    'responses' => $totalResponses
                ];
            }

            $chartData[] = [
                'id' => $organization->id,
                'name' => $organization->name ?? 'Your Organization',
                'is_competitor' => false,
                'color' => $organization->color ?? '#10B981',
                'data' => $dataPoints
            ];
        }

        return response()->json([
            'organizations' => $chartData,
            'interval' => $interval,
            'start_date' => $startDateUser->format('Y-m-d'),
            'end_date' => $endDateUser->format('Y-m-d')
        ]);
    }

    /**
     * Generate date intervals based on the selected interval type
     */
    private function generateIntervals(Carbon $startDate, Carbon $endDate, string $interval): array
    {
        // Copy the same logic from OrganizationVisibilityChartController
        $intervals = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            switch ($interval) {
                case 'daily':
                    $intervalEnd = $current->copy()->endOfDay();
                    $label = $current->format('M d');
                    $next = $current->copy()->addDay();
                    break;

                case 'weekly':
                    $intervalEnd = $current->copy()->endOfWeek();
                    $label = $current->format('M d') . ' - ' . $intervalEnd->format('M d');
                    $next = $current->copy()->addWeek();
                    break;

                case 'monthly':
                    $intervalEnd = $current->copy()->endOfMonth();
                    $label = $current->format('M Y');
                    $next = $current->copy()->addMonth()->startOfMonth();
                    break;

                default:
                    $intervalEnd = $current->copy()->endOfDay();
                    $label = $current->format('M d');
                    $next = $current->copy()->addDay();
            }

            if ($intervalEnd > $endDate) {
                $intervalEnd = $endDate->copy()->endOfDay();
            }

            $intervals[] = [
                'start' => $current->copy(),
                'end' => $intervalEnd,
                'label' => $label
            ];

            $current = $next;
        }

        return $intervals;
    }
}
