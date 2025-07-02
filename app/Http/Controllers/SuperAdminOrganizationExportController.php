<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminOrganizationExportController extends Controller
{
	/**
	 * Export selected organizations with their prompt response data.
	 */
	public function export(Request $request)
	{
		// TODO: Add proper authorization check for super admin
		// if (!$request->user()->isSuperAdmin()) {
		//     return response()->json(['message' => 'Unauthorized'], 403);
		// }

		$request->validate([
			'organization_ids' => 'required|array',
			'organization_ids.*' => 'exists:organizations,id'
		]);

		$organizationIds = $request->input('organization_ids');
		$exportData = [];

		foreach ($organizationIds as $orgId) {
			$organization = Organization::with(['industry', 'terms'])->find($orgId);

			if (!$organization) {
				continue;
			}

			// Get visibility data
			$termIds = $organization->terms->pluck('id')->toArray();
			$totalResponses = 0;
			$totalMentions = 0;
			$visibility = 0;

			if (!empty($termIds)) {
				// Calculate total responses for the team
				$totalResponses = Response::whereHas('prompt', function ($q) use ($organization) {
					$q->where('team_id', $organization->team_id);
				})->count();

				// Calculate mentions
				if ($totalResponses > 0) {
					$totalMentions = Response::whereHas('prompt', function ($q) use ($organization) {
						$q->where('team_id', $organization->team_id);
					})->whereHas('terms', function ($q) use ($termIds) {
						$q->whereIn('terms.id', $termIds);
					})->count();

					$visibility = round(($totalMentions / $totalResponses) * 100, 2);
				}
			}

			// Get all prompts where this organization's terms were mentioned
			$prompts = [];
			if (!empty($termIds)) {
				$promptsQuery = DB::table('prompts')
					->distinct()
					->join('term_prompt', 'prompts.id', '=', 'term_prompt.prompt_id')
					->whereIn('term_prompt.term_id', $termIds)
					->where('prompts.team_id', $organization->team_id)
					->select('prompts.*')
					->get();

				foreach ($promptsQuery as $promptData) {
					// Get all responses for this prompt
					$responses = Response::where('prompt_id', $promptData->id)
						->select('id', 'provider', 'model', 'content', 'created_at')
						->get()
						->map(function ($response) {
							return [
								'id' => $response->id,
								'provider' => $response->provider,
								'model' => $response->model,
								'content' => $response->content,
								'created_at' => $response->created_at->toIso8601String()
							];
						});

					$prompts[] = [
						'id' => $promptData->id,
						'content' => $promptData->content,
						'name' => $promptData->name,
						'description' => $promptData->description,
						'created_at' => $promptData->created_at,
						'responses' => $responses
					];
				}
			}

			$exportData[] = [
				'id' => $organization->id,
				'name' => $organization->name,
				'visibility' => $visibility,
				'industry' => $organization->industry ? $organization->industry->name : null,
				'website' => $organization->website,
				'total_mentions' => $totalMentions,
				'total_responses' => $totalResponses,
				'prompts' => $prompts
			];
		}

		return response()->json([
			'export_date' => now()->toIso8601String(),
			'organizations_count' => count($exportData),
			'organizations' => $exportData
		]);
	}
}
