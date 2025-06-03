<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\PromptRunBatchController;
use App\Http\Controllers\PromptResponsesController;
use App\Http\Controllers\PromptGeneratorController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptOptimizeController;
use App\Http\Controllers\OrganizationVisibilityController;
use App\Http\Controllers\OrganizationSearchController;
use App\Http\Controllers\OrganizationOnboardController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationCompetitorController;
use App\Http\Controllers\KeywordResponsesController;
use App\Http\Controllers\KeywordRecommendationsController;
use App\Http\Controllers\KeywordGeneratorController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AnalyticsController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthPasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthPasswordController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
	// Auth
	Route::post('/logout', [AuthController::class, 'logout']);

	// Analytics endpoints
	Route::get('analytics/keywords', [AnalyticsController::class, 'keywordStats']);
	Route::get('analytics/prompts', [AnalyticsController::class, 'promptStats']);
	Route::get('analytics/timeseries', [AnalyticsController::class, 'timeSeriesData']);

	// Conversations
	Route::resource('conversations', ConversationController::class);
	Route::resource('conversations/{conversation}/chats', ChatController::class);

	// Organizations
	Route::resource('organizations', OrganizationController::class);
	Route::post('organizations-onboard', [OrganizationOnboardController::class, 'store']);

	// Organization Competitors
	Route::post('organizations-find-competitors', [OrganizationCompetitorController::class, 'find']);

	// Organization Visibility
	Route::get('organization-visibility', [OrganizationVisibilityController::class, 'index']);

	// Organization Search
	Route::get('organization-search', [OrganizationSearchController::class, 'search']);
	Route::get('brand-details', [OrganizationSearchController::class, 'brandDetails']); // TODO: Maybe remove


	// Keywords
	Route::resource('organizations/{organization}/keywords', KeywordController::class);
	Route::post('generate-keywords', [KeywordGeneratorController::class, 'generate']);
	Route::get('keywords/{keyword}/prompts/{prompt}/responses', [KeywordResponsesController::class, 'index']);

	// Keyword Recommendations
	Route::get('organizations/{organization}/keyword-recommendations', [KeywordRecommendationsController::class, 'index']);
	Route::put('organizations/{organization}/keyword-recommendations/{id}/accept', [KeywordRecommendationsController::class, 'accept']);
	Route::delete('organizations/{organization}/keyword-recommendations/{id}/deny', [KeywordRecommendationsController::class, 'deny']);

	// Prompts
	Route::resource('prompts', PromptController::class);
	Route::get('prompts/{prompt}/responses', [PromptResponsesController::class, 'index']);
	Route::get('prompts/{prompt}/optimize', PromptOptimizeController::class);
	Route::post('organizations/{organization}/generate-prompts', [PromptGeneratorController::class, 'generate']);

	// Running prompts
	Route::post('prompts/{prompt}/run', [PromptRunController::class, 'store']);
	Route::post('prompt-run-batch', [PromptRunBatchController::class, 'store']);

	// Team routes
	Route::resource('teams', TeamController::class);
	Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
	Route::post('teams/{team}/switch', [TeamController::class, 'switchTeam']);
	Route::post('teams/{team}/accept-invitation', [TeamController::class, 'acceptInvitation']);
	Route::post('teams/{team}/decline-invitation', [TeamController::class, 'declineInvitation']);
	Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
	Route::put('teams/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);

	// Job status routes
	Route::get('/team-jobs', [JobStatusController::class, 'getTeamJobs']);
	Route::post('/team-jobs/cancel', [JobStatusController::class, 'cancelTeamJobs']);
});
