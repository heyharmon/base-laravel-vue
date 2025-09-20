<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\TermResponsesController;
use App\Http\Controllers\TermGeneratorController;
use App\Http\Controllers\TermController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\SuperAdminOrganizationExportController;
use App\Http\Controllers\SuperAdminOrganizationController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\PromptRunBatchController;
use App\Http\Controllers\PromptResponsesController;
use App\Http\Controllers\PromptGeneratorController;
use App\Http\Controllers\PromptExportController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptVisibilityChartController;
use App\Http\Controllers\OrganizationVisibilityController;
use App\Http\Controllers\OrganizationVisibilityChartController;
use App\Http\Controllers\OrganizationSearchController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationCompetitorController;
use App\Http\Controllers\JobStatusController;
use App\Http\Controllers\AuthPasswordController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ArticleVersionController;
use App\Http\Controllers\ArticleConversationController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ArticleChatController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\TeamUsageController;
use App\Http\Controllers\SuperAdminTeamUsageController;
use App\Http\Controllers\UserController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthPasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthPasswordController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Team password reset
    Route::post('/teams/{team}/members/{user}/password-reset', [App\Http\Controllers\TeamPasswordResetController::class, 'generateResetUrl']);

    // Auth
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/logout', [AuthController::class, 'logout']);

    // User
    Route::post('/user/acknowledge-individual-run-warning', [UserController::class, 'acknowledgeIndividualRunWarning']);

    // Organizations
    Route::get('teams/{team}/campaigns/{campaign}/organizations', [OrganizationController::class, 'index']);
    Route::post('teams/{team}/organizations', [OrganizationController::class, 'store']);
    Route::post('teams/{team}/campaigns/{campaign}/organizations', [OrganizationController::class, 'store']);
    Route::resource('organizations', OrganizationController::class)->except(['index', 'store']);

    // Organization Competitors
    Route::post('teams/{team}/campaigns/{campaign}/organizations-find-competitors', [OrganizationCompetitorController::class, 'find']);

    // Organization Visibility
    Route::get('teams/{team}/campaigns/{campaign}/organization-visibility', [OrganizationVisibilityController::class, 'index']);
    Route::get('teams/{team}/campaigns/{campaign}/organization-visibility/chart', [OrganizationVisibilityChartController::class, 'chartData']);

    // Organization Search
    Route::get('organization-search', [OrganizationSearchController::class, 'search']);
    Route::get('brand-details', [OrganizationSearchController::class, 'brandDetails']); // TODO: Maybe remove

    // Terms
    Route::get('teams/{team}/organizations/{organization}/terms', [TermController::class, 'index']);
    Route::post('teams/{team}/organizations/{organization}/terms', [TermController::class, 'store']);
    Route::get('teams/{team}/organizations/{organization}/terms/{term}', [TermController::class, 'show']);
    Route::delete('teams/{team}/organizations/{organization}/terms/{term}', [TermController::class, 'destroy']);
    Route::post('generate-terms', [TermGeneratorController::class, 'generate']);
    Route::get('terms/{term}/prompts/{prompt}/responses', [TermResponsesController::class, 'index']);

    // Campaigns
    Route::get('teams/{team}/campaigns', [CampaignController::class, 'index']);
    Route::post('teams/{team}/campaigns', [CampaignController::class, 'store']);
    Route::get('teams/{team}/campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::put('teams/{team}/campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::delete('teams/{team}/campaigns/{campaign}', [CampaignController::class, 'destroy']);
    Route::post('teams/{team}/campaigns/{campaign}/switch', [CampaignController::class, 'switch']);

    // Prompts
    Route::get('teams/{team}/campaigns/{campaign}/prompts', [PromptController::class, 'index']);
    Route::post('teams/{team}/campaigns/{campaign}/prompts', [PromptController::class, 'store']);
    Route::get('prompts/{prompt}', [PromptController::class, 'show']);
    Route::put('prompts/{prompt}', [PromptController::class, 'update']);
    Route::delete('prompts/{prompt}', [PromptController::class, 'destroy']);

    // Prompt generation
    Route::post('teams/{team}/campaigns/{campaign}/generate-prompts', [PromptGeneratorController::class, 'generate']);

    // Prompt responses
    Route::get('prompts/{prompt}/responses', [PromptResponsesController::class, 'index']);

    // Prompt export
    Route::get('prompts/{prompt}/export', [PromptExportController::class, 'show']);

    // Prompt visibility chart
    Route::get('prompts/{prompt}/visibility-chart', [PromptVisibilityChartController::class, 'chartData']);

    // Running prompts
    Route::post('prompts/{prompt}/run', [PromptRunController::class, 'store']);
    Route::post('teams/{team}/campaigns/{campaign}/prompt-run-batch', [PromptRunBatchController::class, 'store']);

    // Team routes
    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/{team}/switch', [TeamController::class, 'switchTeam']);
    Route::post('teams/{team}/accept-invitation', [TeamController::class, 'acceptInvitation']);
    Route::post('teams/{team}/decline-invitation', [TeamController::class, 'declineInvitation']);
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::put('teams/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);

    // Job status routes
    Route::get('teams/{team}/jobs', [JobStatusController::class, 'getTeamJobs']);
    Route::post('teams/{team}/jobs/cancel', [JobStatusController::class, 'cancelTeamJobs']);

    // Team usage
    Route::get('teams/{team}/usage', [TeamUsageController::class, 'show']);

    // Articles
    Route::get('teams/{team}/campaigns/{campaign}/articles', [ArticleController::class, 'index']);
    Route::post('teams/{team}/campaigns/{campaign}/articles', [ArticleController::class, 'store']);
    Route::resource('articles', ArticleController::class)->except(['index', 'store']);
    Route::get('articles/{article}/perplexity-response', [ArticleController::class, 'getPerplexityResponse']);

    // Article Versions
    Route::post('articles/{article}/versions/{version}/revert', [ArticleVersionController::class, 'revert']);

    // Article Chat
    Route::get('articles/{article}/chats', [ArticleChatController::class, 'index']);
    Route::post('articles/{article}/chats', [ArticleChatController::class, 'store']);

    // Article Conversations
    Route::get('articles/{article}/conversations', [ArticleConversationController::class, 'index']);
    Route::post('articles/{article}/conversations', [ArticleConversationController::class, 'store']);

    // Super Admin routes
    Route::prefix('super-admin')->middleware('superAdmin')->group(function () {
        Route::get('/organizations', [SuperAdminOrganizationController::class, 'index']);
        Route::get('/organizations/stats', [SuperAdminOrganizationController::class, 'stats']);
        Route::get('/organizations/teams', [SuperAdminOrganizationController::class, 'teams']);

        // Export organizations data
        Route::post('/organizations/export', [SuperAdminOrganizationExportController::class, 'export']);

        // Teams usage
        Route::get('/teams', [SuperAdminTeamUsageController::class, 'index']);
        Route::get('/teams/{team}', [SuperAdminTeamUsageController::class, 'show']);
        Route::put('/teams/{team}', [SuperAdminTeamUsageController::class, 'update']);
    });
});
