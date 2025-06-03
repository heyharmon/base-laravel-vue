<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Organization;
use App\Models\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrganizationVisibilityController extends Controller
{
    /**
     * Get visibility metrics for organizations within a date range.
     */
    public function index(Request $request)
    {
        $teamId = $request->user()->currentTeam->id;

        // Validate request parameters
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        // Get date range parameters
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Get all organizations for the team
        $organizations = Organization::where('team_id', $teamId)->get();

        $results = [];

        // Build query for all responses
        $responsesQuery = Response::whereHas('prompt', function ($query) use ($teamId) {
            $query->where('team_id', $teamId);
        });

        // Apply date filters to responses if provided
        if ($startDate) {
            $responsesQuery->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $responsesQuery->where('created_at', '<=', $endDate);
        }

        // Get total responses count
        $totalResponses = $responsesQuery->count();

        foreach ($organizations as $organization) {
            // Get all keywords for this organization
            $keywordIds = Keyword::where('organization_id', $organization->id)
                ->pluck('id')
                ->toArray();

            // If organization has no keywords, set counts to 0
            if (empty($keywordIds)) {
                $totalMentions = 0;
            } else {
                // Find responses that contain at least one keyword from this organization
                $responseQuery = Response::whereHas('prompt', function ($query) use ($teamId) {
                    $query->where('team_id', $teamId);
                })->whereHas('keywords', function ($query) use ($keywordIds) {
                    $query->whereIn('keywords.id', $keywordIds);
                });

                // Apply date filters to responses if provided
                if ($startDate) {
                    $responseQuery->where('responses.created_at', '>=', $startDate);
                }

                if ($endDate) {
                    $responseQuery->where('responses.created_at', '<=', $endDate);
                }

                // Count responses with keywords from this organization
                $totalMentions = $responseQuery->count();
            }

            // Calculate visibility
            $visibility = $totalResponses > 0 ? round(($totalMentions / $totalResponses) * 100, 2) : 0;

            $results[] = [
                ...$organization->toArray(),
                'total_mentions' => $totalMentions,
                'total_responses' => $totalResponses,
                'visibility' => $visibility,
            ];
        }

        // Sort results by visibility in descending order
        usort($results, function ($a, $b) {
            return $b['visibility'] <=> $a['visibility'];
        });
        
        // Add visibility_rank property
        foreach ($results as $index => $result) {
            $results[$index]['visibility_rank'] = $index + 1;
        }

        return response()->json($results);
    }
}
