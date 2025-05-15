<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Models\Prompt;
use App\Models\Run;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function keywordStats(Request $request): JsonResponse
    {
        $period = $request->input('period', 'all');
        $keywordId = $request->input('keyword_id');
        
        $query = Run::query();
        
        // Apply date filters based on period
        switch ($period) {
            case 'yesterday':
                $query->whereDate('run_date', now()->subDay()->toDateString());
                break;
            case 'last7days':
                $query->where('run_date', '>=', now()->subDays(7));
                break;
            case 'last28days':
                $query->where('run_date', '>=', now()->subDays(28));
                break;
        }
        
        if ($keywordId) {
            // Get stats for a specific keyword
            $keyword = Keyword::findOrFail($keywordId);
            
            $stats = [
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
                            'name' => $prompt->name,
                            'count' => $prompt->pivot->count,
                            'last_found_at' => $prompt->pivot->last_found_at,
                        ];
                    }),
            ];
            
            if ($period !== 'all') {
                // Add period-specific data
                $runIds = $query->pluck('id');
                $stats['period_occurrences'] = DB::table('keyword_run')
                    ->whereIn('run_id', $runIds)
                    ->where('keyword_id', $keywordId)
                    ->count();
            }
        } else {
            // Get stats for all keywords
            $stats = Keyword::withCount('prompts')
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
        
        $query = Run::query();
        
        // Apply date filters based on period
        switch ($period) {
            case 'yesterday':
                $query->whereDate('run_date', now()->subDay()->toDateString());
                break;
            case 'last7days':
                $query->where('run_date', '>=', now()->subDays(7));
                break;
            case 'last28days':
                $query->where('run_date', '>=', now()->subDays(28));
                break;
        }
        
        if ($promptId) {
            // Get stats for a specific prompt
            $prompt = Prompt::findOrFail($promptId);
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
                'runs' => $query->with('responses', 'keywords')->get(),
            ];
        } else {
            // Get stats for all prompts
            $stats = Prompt::withCount('keywords')
                ->get()
                ->map(function ($prompt) {
                    return [
                        'id' => $prompt->id,
                        'name' => $prompt->name,
                        'keyword_count' => $prompt->keywords_count,
                        'run_count' => $prompt->runs()->count(),
                        'last_run' => $prompt->runs()->latest('run_date')->first()?->run_date,
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
        
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();
        
        // Create a date series for the requested period
        $dates = [];
        $currentDate = clone $startDate;
        
        while ($currentDate <= $endDate) {
            $dates[$currentDate->format('Y-m-d')] = 0;
            $currentDate->addDay();
        }
        
        $query = Run::where('run_date', '>=', $startDate)
            ->where('run_date', '<=', $endDate);
            
        if ($promptId) {
            $query->where('prompt_id', $promptId);
        }
        
        $runs = $query->get();
        
        if ($keywordId) {
            // Get time series data for a specific keyword
            foreach ($runs as $run) {
                $date = $run->run_date->format('Y-m-d');
                
                if (isset($dates[$date]) && $run->keywords()->where('keyword_id', $keywordId)->exists()) {
                    $dates[$date]++;
                }
            }
            
            $series = [
                'name' => Keyword::find($keywordId)->name ?? 'Unknown',
                'data' => array_values($dates),
                'labels' => array_keys($dates),
            ];
        } else {
            // Get time series data for all runs
            foreach ($runs as $run) {
                $date = $run->run_date->format('Y-m-d');
                
                if (isset($dates[$date])) {
                    $dates[$date]++;
                }
            }
            
            $series = [
                'name' => 'Runs',
                'data' => array_values($dates),
                'labels' => array_keys($dates),
            ];
        }
        
        return response()->json($series);
    }
}
