<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Response;
use App\Models\Term;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Models\Team;
use App\Models\Campaign;

class OrganizationVisibilityChartController extends Controller
{
	/**
	 * Get visibility data over time for charting
	 */
        public function chartData(Request $request, Team $team, Campaign $campaign)
        {
		$request->validate([
			'start_date' => 'nullable|date',
			'end_date' => 'nullable|date|after_or_equal:start_date',
			'interval' => 'nullable|in:daily,weekly,monthly',
			'organization_ids' => 'nullable|array',
			'organization_ids.*' => 'exists:organizations,id'
		]);

                $teamId = $team->id;
                $campaignId = $campaign->id;

		// Handle "all_time" case - if dates are not provided, get the date range from responses
		if (!$request->start_date || !$request->end_date) {
			$firstResponse = Response::whereHas('prompt', function ($query) use ($teamId, $campaignId) {
				$query->where('team_id', $teamId)->where('campaign_id', $campaignId);
			})->orderBy('created_at')->first();

			$lastResponse = Response::whereHas('prompt', function ($query) use ($teamId, $campaignId) {
				$query->where('team_id', $teamId)->where('campaign_id', $campaignId);
			})->orderBy('created_at', 'desc')->first();

			if (!$firstResponse || !$lastResponse) {
				return response()->json([
					'organizations' => [],
					'interval' => $request->input('interval', 'daily'),
					'start_date' => null,
					'end_date' => null
				]);
			}

			$startDate = Carbon::parse($firstResponse->created_at)->startOfDay();
			$endDate = Carbon::parse($lastResponse->created_at)->endOfDay();
		} else {
			$startDate = Carbon::parse($request->start_date);
			$endDate = Carbon::parse($request->end_date);
		}

		$interval = $request->input('interval', 'daily');
		$requestedOrgIds = $request->input('organization_ids', []);

		// Get organizations to track
		$organizationsQuery = Organization::where('team_id', $teamId)
			->forCampaign($campaignId);

		if (!empty($requestedOrgIds)) {
			$organizationsQuery->whereIn('id', $requestedOrgIds);
		} else {
			// By default, only show the owned organization
			$organizationsQuery->where('is_competitor', false);
		}

		$organizations = $organizationsQuery->get();

		// Generate date intervals
		$intervals = $this->generateIntervals($startDate, $endDate, $interval);

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

				// Calculate visibility for this interval
				$totalResponses = Response::whereHas('prompt', function ($query) use ($teamId, $campaignId) {
					$query->where('team_id', $teamId)->where('campaign_id', $campaignId);
				})
					->whereBetween('created_at', [$intervalStart, $intervalEnd])
					->count();

				$totalMentions = 0;
				if (!empty($termIds) && $totalResponses > 0) {
					$totalMentions = Response::whereHas('prompt', function ($query) use ($teamId, $campaignId) {
						$query->where('team_id', $teamId)->where('campaign_id', $campaignId);
					})
						->whereHas('terms', function ($query) use ($termIds) {
							$query->whereIn('terms.id', $termIds);
						})
						->whereBetween('created_at', [$intervalStart, $intervalEnd])
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
				'name' => $organization->name ?? ($organization->is_competitor ? 'Competitor' : 'Your Organization'),
				'is_competitor' => $organization->is_competitor,
				'color' => $organization->color ?? ($organization->is_competitor ? '#EF4444' : '#10B981'), // Red for competitors, green for owned
				'data' => $dataPoints
			];
		}

		return response()->json([
			'organizations' => $chartData,
			'interval' => $interval,
			'start_date' => $startDate->format('Y-m-d'),
			'end_date' => $endDate->format('Y-m-d')
		]);
	}

	/**
	 * Generate date intervals based on the selected interval type
	 */
	private function generateIntervals(Carbon $startDate, Carbon $endDate, string $interval): array
	{
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

			// Don't exceed the end date
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
