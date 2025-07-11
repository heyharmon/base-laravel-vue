<?php

namespace App\Http\Controllers;

use App\Models\Term;
use App\Models\Organization;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationVisibilityController extends Controller
{
	/**
	 * Get visibility metrics for organizations within a date range.
	 * OPTIMIZED VERSION - Single query approach
	 */
	public function index(Request $request)
	{
		$teamId = $request->user()->currentTeam->id;

		// Validate request parameters
		$request->validate([
			'start_date' => 'nullable|date',
			'end_date' => 'nullable|date|after_or_equal:start_date',
		]);

		$startDate = $request->input('start_date');
		$endDate = $request->input('end_date');

		// Get total responses count with date filtering
		$totalResponsesQuery = DB::table('responses')
			->join('prompts', 'responses.prompt_id', '=', 'prompts.id')
			->where('prompts.team_id', $teamId);

		if ($startDate) {
			$totalResponsesQuery->where('responses.created_at', '>=', $startDate);
		}
		if ($endDate) {
			$totalResponsesQuery->where('responses.created_at', '<=', $endDate);
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
                INNER JOIN term_response tr ON tr.term_id = t.id
                INNER JOIN responses r ON r.id = tr.response_id
                INNER JOIN prompts p ON p.id = r.prompt_id
                WHERE p.team_id = ?
                ' . ($startDate ? 'AND r.created_at >= ?' : '') . '
                ' . ($endDate ? 'AND r.created_at <= ?' : '') . '
                GROUP BY t.organization_id
            ) as mention_data'), 'organizations.id', '=', 'mention_data.organization_id')
			->where('organizations.team_id', $teamId);

		// Bind parameters for the subquery
		$bindings = [$teamId];
		if ($startDate) $bindings[] = $startDate;
		if ($endDate) $bindings[] = $endDate;

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

	/**
	 * Alternative implementation using Eloquent with optimized queries
	 * MODERATE OPTIMIZATION - Better than original but uses Eloquent
	 */
	public function indexAlternative(Request $request)
	{
		$teamId = $request->user()->currentTeam->id;

		$request->validate([
			'start_date' => 'nullable|date',
			'end_date' => 'nullable|date|after_or_equal:start_date',
		]);

		$startDate = $request->input('start_date');
		$endDate = $request->input('end_date');

		// Get total responses count once
		$responsesQuery = Response::whereHas('prompt', function ($query) use ($teamId) {
			$query->where('team_id', $teamId);
		});

		if ($startDate) $responsesQuery->where('created_at', '>=', $startDate);
		if ($endDate) $responsesQuery->where('created_at', '<=', $endDate);

		$totalResponses = $responsesQuery->count();

		// Pre-fetch all organizations with their terms
		$organizations = Organization::where('team_id', $teamId)
			->with('terms:id,organization_id')
			->get();

		// Get all term IDs for this team
		$allTermIds = $organizations->pluck('terms.*.id')->flatten()->filter()->toArray();

		if (empty($allTermIds)) {
			// No terms found, return zero visibility for all organizations
			$results = $organizations->map(function ($org) use ($totalResponses) {
				return [
					...$org->toArray(),
					'total_mentions' => 0,
					'total_responses' => $totalResponses,
					'visibility' => 0,
					'visibility_rank' => 1,
				];
			});
			return response()->json($results);
		}

		// Get mention counts for all organizations in a single query
		$mentionCounts = DB::table('term_response')
			->select('terms.organization_id', DB::raw('COUNT(DISTINCT term_response.response_id) as mention_count'))
			->join('terms', 'terms.id', '=', 'term_response.term_id')
			->join('responses', 'responses.id', '=', 'term_response.response_id')
			->join('prompts', 'prompts.id', '=', 'responses.prompt_id')
			->where('prompts.team_id', $teamId)
			->whereIn('terms.id', $allTermIds);

		if ($startDate) $mentionCounts->where('responses.created_at', '>=', $startDate);
		if ($endDate) $mentionCounts->where('responses.created_at', '<=', $endDate);

		$mentionCounts = $mentionCounts->groupBy('terms.organization_id')
			->pluck('mention_count', 'organization_id');

		// Build results
		$results = $organizations->map(function ($organization) use ($totalResponses, $mentionCounts) {
			$totalMentions = $mentionCounts->get($organization->id, 0);
			$visibility = $totalResponses > 0 ? round(($totalMentions / $totalResponses) * 100, 2) : 0;

			return [
				...$organization->toArray(),
				'total_mentions' => $totalMentions,
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

	/**
	 * Cached version for high-traffic scenarios
	 * AGGRESSIVE OPTIMIZATION - Adds caching layer
	 */
	public function indexCached(Request $request)
	{
		$teamId = $request->user()->currentTeam->id;

		$request->validate([
			'start_date' => 'nullable|date',
			'end_date' => 'nullable|date|after_or_equal:start_date',
		]);

		$startDate = $request->input('start_date');
		$endDate = $request->input('end_date');

		// Create cache key based on parameters
		$cacheKey = "visibility_metrics_{$teamId}_{$startDate}_{$endDate}";

		// Try to get from cache first (cache for 5 minutes)
		$results = cache()->remember($cacheKey, 300, function () use ($teamId, $startDate, $endDate) {
			return $this->calculateVisibilityMetrics($teamId, $startDate, $endDate);
		});

		return response()->json($results);
	}

	/**
	 * Extract the calculation logic for reuse
	 */
	private function calculateVisibilityMetrics($teamId, $startDate = null, $endDate = null)
	{
		// Use the optimized query from the main index method
		// This is the same logic as the first method but extracted for caching

		$totalResponsesQuery = DB::table('responses')
			->join('prompts', 'responses.prompt_id', '=', 'prompts.id')
			->where('prompts.team_id', $teamId);

		if ($startDate) $totalResponsesQuery->where('responses.created_at', '>=', $startDate);
		if ($endDate) $totalResponsesQuery->where('responses.created_at', '<=', $endDate);

		$totalResponses = $totalResponsesQuery->count();

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
                INNER JOIN term_response tr ON tr.term_id = t.id
                INNER JOIN responses r ON r.id = tr.response_id
                INNER JOIN prompts p ON p.id = r.prompt_id
                WHERE p.team_id = ?
                ' . ($startDate ? 'AND r.created_at >= ?' : '') . '
                ' . ($endDate ? 'AND r.created_at <= ?' : '') . '
                GROUP BY t.organization_id
            ) as mention_data'), 'organizations.id', '=', 'mention_data.organization_id')
			->where('organizations.team_id', $teamId);

		$bindings = [$teamId];
		if ($startDate) $bindings[] = $startDate;
		if ($endDate) $bindings[] = $endDate;

		$organizations = $organizationsWithMentions->addBinding($bindings, 'join')->get();

		return $organizations->map(function ($org) use ($totalResponses) {
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
	}
}
