<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Organization;
use App\Models\Keyword;
use App\Jobs\CheckKeywordInPastResponsesJob;

class KeywordRecommendationsController extends Controller
{
    protected $jobDispatcher;

    public function __construct(JobDispatcherService $jobDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
    }

    /**
     * Display a listing of the recommended keywords for an organization.
     */
    public function index(Organization $organization): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $keywords = Keyword::where('organization_id', $organization->id)
            ->withRecommended()
            ->where('is_recommended', true)
            ->get();

        return response()->json($keywords);
    }

    /**
     * Update the specified resource in storage.
     */
    public function accept(Request $request, Organization $organization, $id): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $keyword = Keyword::withRecommended()
            ->where('organization_id', $organization->id)
            ->findOrFail($id);

        $keyword->update([
            'is_recommended' => false,
        ]);

		// Dispatch a job to check past responses for this keyword
        $job = new CheckKeywordInPastResponsesJob($keyword, $keyword->team_id);
        $this->jobDispatcher->dispatch($keyword, $job);

        return response()->json($keyword);
    }

    /**
     * Remove the specified recommended keyword from storage.
     */
    public function deny(Organization $organization, $id): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Find keyword with the withRecommended scope to include recommended keywords
        $keyword = Keyword::withRecommended()
            ->where('organization_id', $organization->id)
            ->findOrFail($id);

        // Delete the keyword
        $keyword->delete();

        return response()->json(null, 204);
    }
}
