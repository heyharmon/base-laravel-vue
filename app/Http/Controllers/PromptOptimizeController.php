<?php

namespace App\Http\Controllers;

use App\Models\Prompt;
use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class PromptOptimizeController extends Controller
{
	public function __invoke(Prompt $prompt): JsonResponse
	{
		// Check if prompt belongs to user's current team
		if ($prompt->team_id !== Auth::user()->current_team_id) {
			return response()->json(['message' => 'Not found'], 404);
		}

		// Get the team ID from the authenticated user
		$teamId = Auth::user()->current_team_id;

		// Get the owned organization for this team
		$ownedOrganization = Organization::where('team_id', $teamId)
			->where('is_competitor', false)
			->first();

		if (!$ownedOrganization) {
			return response()->json(['message' => 'No owned organization found'], 404);
		}

		// Format the text as requested
		$locationText = $ownedOrganization->location ? " in {$ownedOrganization->location}" : "";
		$optimizedText = "Generate a highly optimized article for my business called \"{$ownedOrganization->name}\" ({$ownedOrganization->website}){$locationText} that will increase my visibility in LLM completions for this prompt:\n\n";
		$optimizedText .= "<prompt>\n";
		$optimizedText .= "{$prompt->content}\n";
		$optimizedText .= "</prompt>";

		return response()->json([
			'text' => $optimizedText
		]);
	}
}
