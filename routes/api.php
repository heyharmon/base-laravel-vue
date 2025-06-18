<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\TermResponsesController;
use App\Http\Controllers\TermRecommendationsController;
use App\Http\Controllers\TermGeneratorController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\PromptRunBatchController;
use App\Http\Controllers\PromptResponsesController;
use App\Http\Controllers\PromptGeneratorController;
use App\Http\Controllers\PromptExportController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\OrganizationVisibilityController;
use App\Http\Controllers\OrganizationSearchController;
use App\Http\Controllers\OrganizationOnboardController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationCompetitorController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ArticleGeneratorController;
use App\Http\Controllers\ArticleConversationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleChatController;
use App\Http\Controllers\ArticleVersionController;
use App\Http\Controllers\AnalyticsController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthPasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthPasswordController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
	// Auth
	Route::get('/user', function (Request $request) {
		return $request->user();
	});

	Route::post('/logout', [AuthController::class, 'logout']);

	// Analytics endpoints
	Route::get('analytics/terms', [AnalyticsController::class, 'termStats']);
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


	// Terms
	Route::resource('organizations/{organization}/terms', TermController::class);
	Route::post('generate-terms', [TermGeneratorController::class, 'generate']);
	Route::get('terms/{term}/prompts/{prompt}/responses', [TermResponsesController::class, 'index']);

	// Term Recommendations
	Route::get('organizations/{organization}/term-recommendations', [TermRecommendationsController::class, 'index']);
	Route::put('organizations/{organization}/term-recommendations/{id}/accept', [TermRecommendationsController::class, 'accept']);
	Route::delete('organizations/{organization}/term-recommendations/{id}/deny', [TermRecommendationsController::class, 'deny']);

	// Prompts
	Route::resource('prompts', PromptController::class);
	Route::get('prompts/{prompt}/responses', [PromptResponsesController::class, 'index']);
	Route::post('organizations/{organization}/generate-prompts', [PromptGeneratorController::class, 'generate']);
	Route::post('prompts/{prompt}/generate-article', [ArticleGeneratorController::class, 'generate']);
	Route::get('prompts/{prompt}/export', [PromptExportController::class, 'show']);

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

	// Articles
	Route::resource('articles', ArticleController::class);
	Route::get('articles/{article}/perplexity-response', [ArticleController::class, 'getPerplexityResponse']);

	// Article Versions
	Route::post('articles/{article}/versions/{version}/revert', [ArticleVersionController::class, 'revert']);

	// Article Chat
	Route::get('articles/{article}/chats', [ArticleChatController::class, 'index']);
	Route::post('articles/{article}/chats', [ArticleChatController::class, 'store']);

	// Article Conversations
	Route::get('articles/{article}/conversations', [ArticleConversationController::class, 'index']);
	Route::post('articles/{article}/conversations', [ArticleConversationController::class, 'store']);
});
