<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Models\Organization;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Team;
use App\Models\Campaign;
use Illuminate\Support\Carbon;

class OrganizationVisibilityController extends Controller
{
	/**
	 * Get visibility metrics for organizations within a date range.
	 * OPTIMIZED VERSION - Single query approach
	 */
	public function index(Request $request, Team $team, Campaign $campaign)
	{
		$teamId = $team->id;
		$campaignId = $campaign->id;

		// Validate request parameters
                $request->validate([
                        'start_date' => 'nullable|date',
                        'end_date' => 'nullable|date|after_or_equal:start_date',
                        'timezone' => 'nullable|timezone',
                ]);

                $timezone = $request->input('timezone', 'UTC');

                $startDateUtc = $request->start_date
                        ? Carbon::parse($request->start_date, $timezone)->setTimezone('UTC')
                        : null;
                $endDateUtc = $request->end_date
                        ? Carbon::parse($request->end_date, $timezone)->endOfDay()->setTimezone('UTC')
                        : null;

		// Get total responses count with date filtering
		$totalResponsesQuery = DB::table('responses')
			->join('prompts', 'responses.prompt_id', '=', 'prompts.id')
			->where('prompts.team_id', $teamId)
			->where('prompts.campaign_id', $campaignId);

                if ($startDateUtc) {
                        $totalResponsesQuery->where('responses.created_at', '>=', $startDateUtc);
                }
                if ($endDateUtc) {
                        $totalResponsesQuery->where('responses.created_at', '<=', $endDateUtc);
                }

		$totalResponses = $totalResponsesQuery->count();

		// Get all organizations with their mention counts in a single optimized query
		$organizationsWithMentions = DB::table('organizations')
			->select([
				'organizations.*',
				DB::raw('COALESCE(mention_data.mention_count, 0) as total_mentions')
			])
			->leftJoin(DB::raw('(
               SELECT
                   t.organization_id,
                   COUNT(DISTINCT r.id) as mention_count
               FROM terms t
               INNER JOIN organizations org ON t.organization_id = org.id
               INNER JOIN term_response tr ON tr.term_id = t.id
               INNER JOIN responses r ON r.id = tr.response_id
               INNER JOIN prompts p ON p.id = r.prompt_id
               WHERE p.team_id = ?
               AND p.campaign_id = ?
               AND org.team_id = ?
               AND (org.campaign_id = ? OR (org.campaign_id IS NULL AND org.is_competitor = 0))
               ' . ($startDateUtc ? 'AND r.created_at >= ?' : '') . '
               ' . ($endDateUtc ? 'AND r.created_at <= ?' : '') . '
               GROUP BY t.organization_id
           ) as mention_data'), 'organizations.id', '=', 'mention_data.organization_id')
			->where('organizations.team_id', $teamId)
			->where(function ($query) use ($campaignId) {
				$query->where('organizations.campaign_id', $campaignId)
					->orWhere('organizations.is_competitor', false);
			});

		// Bind parameters for the subquery
                $bindings = [$teamId, $campaignId, $teamId, $campaignId];
                if ($startDateUtc) $bindings[] = $startDateUtc->toDateTimeString();
                if ($endDateUtc) $bindings[] = $endDateUtc->toDateTimeString();

		$organizations = $organizationsWithMentions->addBinding($bindings, 'join')->get();

		// Calculate visibility and format results
		$results = $organizations->map(function ($org) use ($totalResponses) {
			$visibility = $totalResponses > 0 ? round(($org->total_mentions / $totalResponses) * 100, 2) : 0;

			// Convert stdClass to array and add our calculated fields
			$orgArray = (array) $org;

			return [
				...$orgArray,
				'is_competitor' => (bool) $org->is_competitor, // Ensure boolean casting
				'total_mentions' => (int) $org->total_mentions,
				'total_responses' => $totalResponses,
				'visibility' => $visibility,
			];
		})
			->sortByDesc('visibility')
			->values()
			->map(function ($result, $index) {
				$result['visibility_rank'] = $index + 1;
				return $result;
			});

		return response()->json($results);
	}
}
