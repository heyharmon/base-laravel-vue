<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Prompt;
use App\Models\Organization;
use App\Jobs\FindCompetitorsInPastResponsesJob;

class CompetitorRecommendationsController extends Controller
{
	protected $jobDispatcher;

    public function __construct(JobDispatcherService $jobDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
    }

	public function generate(Request $request): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;

        // Get all prompts for the current team
        $prompts = Prompt::where('team_id', $teamId)->get();

        if ($prompts->isEmpty()) {
            return response()->json([
                'message' => 'No prompts found to analyze'
            ], 404);
        }

        // Create jobs for the latest response of each prompt
        $jobs = [];
        $promptsWithResponses = [];

        foreach ($prompts as $prompt) {
            // Get the latest response for this prompt
            $latestResponse = $prompt->responses()->latest()->first();

            // Skip prompts without responses
            if (!$latestResponse) {
                continue;
            }

            $jobs[] = new FindCompetitorsInPastResponsesJob($prompt, $latestResponse, $teamId);
            $promptsWithResponses[] = $prompt;
        }

        if (empty($jobs)) {
            return response()->json([
                'message' => 'No prompt responses found to analyze'
            ], 404);
        }

        // Dispatch as a single batch with tracking
        $batch = $this->jobDispatcher->dispatchBatch($promptsWithResponses, $jobs, [
            'name' => 'Searching for competitors in past responses',
            'allowFailures' => true
        ]);

        return response()->json([
            'message' => 'All prompt responses queued for competitor analysis',
            'batch' => $batch,
            'prompts_count' => count($promptsWithResponses),
            'total_jobs' => count($jobs)
        ]);
    }

    /**
     * Display a listing of the recommended organizations.
     */
    public function index(): JsonResponse
    {
        $teamId = Auth::user()->current_team_id;

        $organizations = Organization::where('team_id', $teamId)
            ->withRecommended()
            ->where('is_recommended', true)
            ->withCount('keywords')
            ->get();

        return response()->json($organizations);
    }

    /**
     * Remove the specified recommended organization from storage.
     */
    public function destroy($id): JsonResponse
    {
        // Find organization with the withRecommended scope to include recommended organizations
        $organization = Organization::withRecommended()->findOrFail($id);

        // Ensure the organization belongs to the current team
        if ($organization->team_id !== request()->user()->currentTeam->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Delete the organization
        $organization->delete();

        return response()->json(null, 204);
    }
}
