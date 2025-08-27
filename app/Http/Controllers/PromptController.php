<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\Team;
use App\Models\Campaign;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Carbon;

class PromptController extends Controller
{
    public function index(Request $request, Team $team, Campaign $campaign): JsonResponse
    {
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

        $prompts = Prompt::where('team_id', $team->id)
            ->where('campaign_id', $campaign->id)
            ->withCount([
                // Count terms that are not competitor terms
                'terms' => function ($query) {
                    $query->whereHas('organization', function ($q) {
                        $q->where('is_competitor', false);
                    });
                },
                // Count responses with date filtering
                'responses' => function ($query) use ($startDateUtc, $endDateUtc) {
                    if ($startDateUtc) {
                        $query->where('created_at', '>=', $startDateUtc);
                    }
                    if ($endDateUtc) {
                        $query->where('created_at', '<=', $endDateUtc);
                    }
                }
            ])
            ->latest()
            ->get();

        // Calculate mentions percentage for each prompt with date filtering
        $prompts->each(function ($prompt) use ($startDateUtc, $endDateUtc, $team) {
            $totalResponses = $prompt->responses_count;

            if ($totalResponses === 0) {
                $prompt->mentions_percentage = 0;
                return;
            }

            // Get the organization that belongs to the team and is not a competitor
            $organization = Organization::where('team_id', $team->id)
                ->where('is_competitor', false)
                ->first();

            if (!$organization) {
                $prompt->mentions_percentage = 0;
                return;
            }

            // Get all terms for this organization
            $termIds = $organization->terms()->pluck('id')->toArray();

            if (empty($termIds)) {
                $prompt->mentions_percentage = 0;
                return;
            }

            // Count responses that contain at least one term from the team's organization with date filtering
            $mentionsQuery = $prompt->responses()
                ->whereHas('terms', function ($query) use ($termIds) {
                    $query->whereIn('terms.id', $termIds);
                });

            if ($startDateUtc) {
                $mentionsQuery->where('created_at', '>=', $startDateUtc);
            }
            if ($endDateUtc) {
                $mentionsQuery->where('created_at', '<=', $endDateUtc);
            }

            $mentions = $mentionsQuery->count();
            $prompt->mentions_percentage = round(($mentions / $totalResponses) * 100);
        });

        return response()->json($prompts);
    }

    public function show(Prompt $prompt): JsonResponse
    {
        // Check if prompt belongs to user's current team
        // TODO: Change this if adding projects model
        if ($prompt->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $prompt->load(['terms', 'articles']);

        return response()->json($prompt);
    }

    public function store(Request $request, Team $team, Campaign $campaign): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'nullable|string',
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $validated['team_id'] = $team->id;
        $validated['campaign_id'] = $campaign->id;

        $prompt = Prompt::create($validated);

        return response()->json($prompt, 201);
    }

    public function update(Request $request, Prompt $prompt): JsonResponse
    {
        // Check if prompt belongs to user's current team
        // TODO: Change this if adding projects model
        if ($prompt->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'nullable|string',
            'content' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $prompt->update($validated);

        return response()->json($prompt);
    }

    public function destroy(Prompt $prompt): JsonResponse
    {
        // Check if prompt belongs to user's current team
        // TODO: Change this if adding projects model
        if ($prompt->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $prompt->delete();

        return response()->json(null, 204);
    }
}
