<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PromptController extends Controller
{
	public function index(Team $team): JsonResponse
	{
		// TODO: Change this if adding projects model
		$prompts = Prompt::where('team_id', $team->id)
			->withCount([
				// Count terms that are not competitor terms
				'terms' => function ($query) {
					$query->whereHas('organization', function ($q) {
						$q->where('is_competitor', false);
					});
				},
				'responses'
			])
			->latest()
			->get();

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

	public function store(Request $request, Team $team): JsonResponse
	{
		$validated = $request->validate([
			'name' => 'nullable|string',
			'content' => 'required|string',
			'description' => 'nullable|string',
		]);

		// Add the team_id to the validated data
		// TODO: Change this if adding projects model
		$validated['team_id'] = $team->id;

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
