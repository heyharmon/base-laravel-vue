<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Response;
use App\Models\Term;
use App\Models\Team;
use Illuminate\Http\Request;

class SuperAdminOrganizationController extends Controller
{
	/**
	 * Get all organizations with visibility metrics for super admin.
	 */
	public function index(Request $request)
	{
		// Validate request parameters
		$request->validate([
			'industry_id' => 'nullable|exists:organization_industries,id',
			'team_id' => 'nullable|exists:teams,id',
			'is_competitor' => 'nullable|in:owned,competitor,all',
			'search' => 'nullable|string|max:255',
			'sort_by' => 'nullable|in:name,team_name,visibility,industry_name,website',
			'sort_order' => 'nullable|in:asc,desc',
			'page' => 'nullable|integer|min:1',
			'per_page' => 'nullable|integer|min:1|max:100',
		]);

		// Build base query with relationships
		$query = Organization::with(['team', 'industry']);

		// Apply filters
		if ($request->has('industry_id') && $request->industry_id) {
			$query->where('industry_id', $request->industry_id);
		}

		if ($request->has('team_id') && $request->team_id) {
			$query->where('team_id', $request->team_id);
		}

		if ($request->has('is_competitor') && $request->is_competitor !== 'all') {
			$isCompetitor = $request->is_competitor === 'competitor';
			$query->where('is_competitor', $isCompetitor);
		}

		if ($request->has('search') && $request->search) {
			$query->where('name', 'like', '%' . $request->search . '%');
		}

		// Get organizations with basic info
		$organizations = $query->get();

		// Calculate visibility for each organization
		$results = [];
		foreach ($organizations as $organization) {
			// Get all terms for this organization
			$termIds = Term::where('organization_id', $organization->id)
				->pluck('id')
				->toArray();

			// Calculate total responses for the team
			$totalResponses = Response::whereHas('prompt', function ($q) use ($organization) {
				$q->where('team_id', $organization->team_id);
			})->count();

			// Calculate mentions
			$totalMentions = 0;
			if (!empty($termIds) && $totalResponses > 0) {
				$totalMentions = Response::whereHas('prompt', function ($q) use ($organization) {
					$q->where('team_id', $organization->team_id);
				})->whereHas('terms', function ($q) use ($termIds) {
					$q->whereIn('terms.id', $termIds);
				})->count();
			}

			// Calculate visibility percentage
			$visibility = $totalResponses > 0
				? round(($totalMentions / $totalResponses) * 100, 2)
				: 0;

			$results[] = [
				'id' => $organization->id,
				'name' => $organization->name,
				'website' => $organization->website,
				'is_competitor' => $organization->is_competitor,
				'team_id' => $organization->team_id,
				'team_name' => $organization->team ? $organization->team->name : null,
				'industry_id' => $organization->industry_id,
				'industry_name' => $organization->industry ? $organization->industry->name : null,
				'visibility' => $visibility,
				'total_mentions' => $totalMentions,
				'total_responses' => $totalResponses,
				'created_at' => $organization->created_at,
				'updated_at' => $organization->updated_at,
			];
		}

		// Apply sorting
		$sortBy = $request->get('sort_by', 'name');
		$sortOrder = $request->get('sort_order', 'asc');

		usort($results, function ($a, $b) use ($sortBy, $sortOrder) {
			$compareValue = 0;

			switch ($sortBy) {
				case 'name':
					$compareValue = strcasecmp($a['name'] ?? '', $b['name'] ?? '');
					break;
				case 'team_name':
					$compareValue = strcasecmp($a['team_name'] ?? '', $b['team_name'] ?? '');
					break;
				case 'visibility':
					$compareValue = $a['visibility'] <=> $b['visibility'];
					break;
				case 'industry_name':
					$compareValue = strcasecmp($a['industry_name'] ?? '', $b['industry_name'] ?? '');
					break;
				case 'website':
					$compareValue = strcasecmp($a['website'] ?? '', $b['website'] ?? '');
					break;
			}

			return $sortOrder === 'desc' ? -$compareValue : $compareValue;
		});

		// Apply pagination if requested
		$page = $request->get('page', 1);
		$perPage = $request->get('per_page', 50);
		$total = count($results);

		$paginatedResults = array_slice($results, ($page - 1) * $perPage, $perPage);

		return response()->json([
			'data' => $paginatedResults,
			'meta' => [
				'total' => $total,
				'per_page' => $perPage,
				'current_page' => $page,
				'last_page' => ceil($total / $perPage),
			]
		]);
	}

	/**
	 * Get summary statistics for organizations.
	 */
	public function stats(Request $request)
	{
		// TODO: Add proper authorization check for super admin

		$stats = [
			'total_organizations' => Organization::count(),
			'owned_organizations' => Organization::where('is_competitor', false)->count(),
			'competitor_organizations' => Organization::where('is_competitor', true)->count(),
			'total_teams' => Organization::distinct('team_id')->count('team_id'),
		];

		return response()->json($stats);
	}

	/**
	 * Get all teams for filtering dropdown.
	 */
	public function teams(Request $request)
	{
		// TODO: Add proper authorization check for super admin

		$teams = Team::select('id', 'name')
			->orderBy('name')
			->get();

		return response()->json($teams);
	}
}
