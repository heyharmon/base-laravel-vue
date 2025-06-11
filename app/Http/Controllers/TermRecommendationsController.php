<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Organization;
use App\Models\Term;
use App\Jobs\CheckTermInPastResponsesJob;

class TermRecommendationsController extends Controller
{
    protected $jobDispatcher;

    public function __construct(JobDispatcherService $jobDispatcher)
    {
        $this->jobDispatcher = $jobDispatcher;
    }

    /**
     * Display a listing of the recommended terms for an organization.
     */
    public function index(Organization $organization): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $terms = Term::where('organization_id', $organization->id)
            ->withRecommended()
            ->where('is_recommended', true)
            ->get();

        return response()->json($terms);
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

        $term = Term::withRecommended()
            ->where('organization_id', $organization->id)
            ->findOrFail($id);

        $term->update([
            'is_recommended' => false,
        ]);

		// Dispatch a job to check past responses for this term
        $job = new CheckTermInPastResponsesJob($term, $term->team_id);
        $this->jobDispatcher->dispatch($term, $job);

        return response()->json($term);
    }

    /**
     * Remove the specified recommended term from storage.
     */
    public function deny(Organization $organization, $id): JsonResponse
    {
        // Ensure the organization belongs to the current team
        if ($organization->team_id !== Auth::user()->current_team_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Find term with the withRecommended scope to include recommended terms
        $term = Term::withRecommended()
            ->where('organization_id', $organization->id)
            ->findOrFail($id);

        // Delete the term
        $term->delete();

        return response()->json(null, 204);
    }
}
