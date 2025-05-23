<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Mention;
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
        
        foreach ($organizations as $organization) {
            // Build query for mentions
            $mentionsQuery = Mention::where('team_id', $teamId)
                ->where('organization_id', $organization->id);
            
            // Build query for responses
            $responsesQuery = Response::whereHas('prompt', function ($query) use ($teamId) {
                $query->where('team_id', $teamId);
            });
            
            // Apply date filters if provided
            if ($startDate) {
                $mentionsQuery->where('created_at', '>=', $startDate);
                $responsesQuery->where('created_at', '>=', $startDate);
            }
            
            if ($endDate) {
                $mentionsQuery->where('created_at', '<=', $endDate);
                $responsesQuery->where('created_at', '<=', $endDate);
            }
            
            // Count mentions and responses
            $totalMentions = $mentionsQuery->count();
            $totalResponses = $responsesQuery->count();
            
            // Calculate visibility
            $visibility = $totalResponses > 0 ? round(($totalMentions / $totalResponses) * 100, 2) : 0;
            
            // Get keyword mentions breakdown
            $keywordMentions = DB::table('mentions')
                ->join('keywords', 'mentions.keyword_id', '=', 'keywords.id')
                ->select('keywords.name', DB::raw('count(*) as count'))
                ->where('mentions.organization_id', $organization->id)
                ->where('mentions.team_id', $teamId);
                
            // Apply date filters if provided
            if ($startDate) {
                $keywordMentions->where('mentions.created_at', '>=', $startDate);
            }
            
            if ($endDate) {
                $keywordMentions->where('mentions.created_at', '<=', $endDate);
            }
            
            $keywordMentions = $keywordMentions->groupBy('keywords.name')
                ->orderBy('count', 'desc')
                ->get();
            
            $results[] = [
                'id' => $organization->id,
                'name' => $organization->name,
                'is_competitor' => $organization->is_competitor,
                'total_mentions' => $totalMentions,
                'total_responses' => $totalResponses,
                'visibility' => $visibility,
                'keyword_mentions' => $keywordMentions,
            ];
        }
        
        return response()->json($results);
    }
}
