<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\JobDispatcherService;
use App\Models\Prompt;
use App\Models\Organization;
use App\Jobs\GenerateArticleJob;

class ArticleGeneratorController extends Controller
{
	protected $jobDispatcher;

	public function __construct(JobDispatcherService $jobDispatcher)
	{
		$this->jobDispatcher = $jobDispatcher;
	}

	/**
	 * Generate an article for a specific prompt using the team's owned organization.
	 */
	public function generate(Request $request, Prompt $prompt): JsonResponse
	{
		$teamId = $request->user()->currentTeam->id;

		// Get the owned organization for this team
		$organization = Organization::where('team_id', $teamId)
			->where('is_competitor', false)
			->first();

		// Dispatch the job to generate an article
		$job = new GenerateArticleJob($prompt, $organization, $teamId);
		$this->jobDispatcher->dispatch($prompt, $job);

		return response()->json([
			'message' => 'Article generation started',
			'prompt' => $prompt,
			'organization' => $organization
		]);
	}
}
