<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function keywordStats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'all');
        $keywordId = $request->input('keyword_id');
        
        // Get the user's current team ID
        $teamId = Auth::user()->current_team_id;
        
        $query = Response::query();
        
        // Apply date filters based on period
        switch ($period) {
            case 'yesterday':
                $query->whereDate('created_at', now()->subDay()->toDateString());
                break;
            case 'last7days':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case 'last28days':
                $query->where('created_at', '>=', now()->subDays(28));
                break;
        }
        
        if ($keywordId) {
            // Get stats for a specific keyword
            $keyword = Keyword::where('id', $keywordId)
                ->where('team_id', $teamId)
                ->firstOrFail();
            
            $stats = [
                'id' => $keyword->id,
                'keyword' => $keyword->name,
                'total_occurrences' => $keyword->prompts()->sum('count'),
                'prompt_count' => $keyword->prompts()->count(),
                'prompts' => $keyword->prompts()
                    ->withPivot('count', 'last_found_at')
                    ->orderByDesc('pivot_count')
                    ->get()
                    ->map(function ($prompt) {
                        return [
                            'id' => $prompt->id,
                            'content' => $prompt->content,
                            'count' => $prompt->pivot->count,
                            'last_found_at' => $prompt->pivot->last_found_at,
                        ];
                    }),
            ];
            
            if ($period !== 'all') {
                // Add period-specific data
                $responseIds = $query->pluck('id');
                $stats['period_occurrences'] = DB::table('keyword_response')
                    ->whereIn('response_id', $responseIds)
                    ->where('keyword_id', $keywordId)
                    ->count();
            }
        } else {
            // Get stats for all keywords
            $stats = Keyword::withCount('prompts')
                ->where('team_id', $teamId)
                ->get()
                ->map(function ($keyword) {
                    return [
                        'id' => $keyword->id,
                        'name' => $keyword->name,
                        'prompt_count' => $keyword->prompts_count,
                        'total_occurrences' => $keyword->prompts()->sum('count'),
                    ];
                });
        }
        
        return response()->json($stats);
    }
    
    public function promptStats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'all');
        $promptId = $request->input('prompt_id');
        
        // Get the user's current team ID
        $teamId = Auth::user()->current_team_id;
        
        $query = Response::query()->whereHas('prompt', function($q) use ($teamId) {
            $q->where('team_id', $teamId);
        });
        
        // Apply date filters based on period
        switch ($period) {
            case 'yesterday':
                $query->whereDate('created_at', now()->subDay()->toDateString());
                break;
            case 'last7days':
                $query->where('created_at', '>=', now()->subDays(7));
                break;
            case 'last28days':
                $query->where('created_at', '>=', now()->subDays(28));
                break;
        }
        
        if ($promptId) {
            // Get stats for a specific prompt
            $prompt = Prompt::where('id', $promptId)
                ->where('team_id', $teamId)
                ->firstOrFail();
            $query->where('prompt_id', $promptId);
            
            $stats = [
                'prompt' => $prompt->name,
                'keyword_count' => $prompt->keywords()->count(),
                'keywords' => $prompt->keywords()
                    ->withPivot('count', 'last_found_at')
                    ->orderByDesc('pivot_count')
                    ->get()
                    ->map(function ($keyword) {
                        return [
                            'id' => $keyword->id,
                            'name' => $keyword->name,
                            'count' => $keyword->pivot->count,
                            'last_found_at' => $keyword->pivot->last_found_at,
                        ];
                    }),
                'responses' => $query->with('keywords')->get(),
            ];
        } else {
            // Get stats for all prompts
            $stats = Prompt::withCount('keywords')
                ->where('team_id', $teamId)
                ->get()
                ->map(function ($prompt) {
                    return [
                        'id' => $prompt->id,
                        'name' => $prompt->name,
                        'keyword_count' => $prompt->keywords_count,
                        'response_count' => $prompt->responses()->count(),
                        'last_response' => $prompt->responses()->latest('created_at')->first()?->created_at,
                    ];
                });
        }
        
        return response()->json($stats);
    }
    
    public function timeSeriesData(Request $request): JsonResponse
    {
        $keywordId = $request->input('keyword_id');
        $promptId = $request->input('prompt_id');
        $days = $request->input('days', 30);
        
        // Get the user's current team ID
        $teamId = Auth::user()->current_team_id;
        
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();
        
        // Create a date series for the requested period
        $dates = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $dates[$currentDate->format('Y-m-d')] = 0;
            $currentDate->addDay();
        }
        
        $query = Response::where('created_at', '>=', $startDate)
            ->where('created_at', '<=', $endDate)
            ->whereHas('prompt', function($q) use ($teamId) {
                $q->where('team_id', $teamId);
            });
            
        if ($promptId) {
            $query->where('prompt_id', $promptId);
        }
        
        $responses = $query->get();
        
        if ($keywordId) {
            // Verify keyword belongs to user's team
            $keyword = Keyword::where('id', $keywordId)
                ->where('team_id', $teamId)
                ->firstOrFail();
                
            // Get time series data for a specific keyword
            foreach ($responses as $response) {
                $date = $response->created_at->format('Y-m-d');
                
                if (isset($dates[$date]) && $response->keywords()->where('keyword_id', $keywordId)->exists()) {
                    $dates[$date]++;
                }
            }
            
            $series = [
                'name' => $keyword->name,
                'data' => array_values($dates),
                'labels' => array_keys($dates),
            ];
        } else {
            // Get time series data for all responses
            foreach ($responses as $response) {
                $date = $response->created_at->format('Y-m-d');
                
                if (isset($dates[$date])) {
                    $dates[$date]++;
                }
            }
            
            $series = [
                'name' => 'Responses',
                'data' => array_values($dates),
                'labels' => array_keys($dates),
            ];
        }
        
        return response()->json($series);
    }
}
