<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\PromptRunBatchController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\PromptResponsesController;
use App\Http\Controllers\KeywordResponsesController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\KeywordGeneratorController;
use App\Http\Controllers\PromptGeneratorController;
use App\Http\Controllers\AuthPasswordController;
use App\Http\Controllers\JobStatusController;
use App\Http\Middleware\EnsureHasTeam;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/invitations/verify', [InvitationController::class, 'verify']);
Route::post('/forgot-password', [AuthPasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthPasswordController::class, 'resetPassword']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    // Analytics endpoints
    Route::get('analytics/keywords', [AnalyticsController::class, 'keywordStats']);
    Route::get('analytics/prompts', [AnalyticsController::class, 'promptStats']);
    Route::get('analytics/timeseries', [AnalyticsController::class, 'timeSeriesData']);
    
    // Conversations
    Route::resource('conversations', ConversationController::class);
    Route::resource('conversations/{conversation}/chats', ChatController::class);

    // Core resources
    Route::middleware(EnsureHasTeam::class)->group(function () {
        Route::resource('keywords', KeywordController::class);
        Route::resource('prompts', PromptController::class);
        Route::post('generate-keywords', [KeywordGeneratorController::class, 'generate']);
        Route::post('generate-prompts', [PromptGeneratorController::class, 'generate']);
    });

    // Keyword responses
    Route::get('keywords/{keyword}/prompts/{prompt}/responses', [KeywordResponsesController::class, 'index']);
    
    // Prompt responses
    Route::get('prompts/{prompt}/responses', [PromptResponsesController::class, 'index']);

    // Running prompts
    Route::post('prompts/{prompt}/run', [PromptRunController::class, 'store']);
    Route::post('prompt-run-batch', [PromptRunBatchController::class, 'store']);

    // Prompt responses (detailed)
    Route::resource('prompts/{prompt}/responses', ResponseController::class);
    
    // Team routes
    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/{team}/switch', [TeamController::class, 'switchTeam']);
    Route::post('teams/{team}/accept-invitation', [TeamController::class, 'acceptInvitation']);
    Route::post('teams/{team}/decline-invitation', [TeamController::class, 'declineInvitation']);
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::put('teams/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);
    
    // Job status routes
    Route::get('/job-statuses', [JobStatusController::class, 'getModelJobStatuses']);
    Route::get('/job-status/{jobId}', [JobStatusController::class, 'getJobStatus']);
    Route::get('/active-jobs', [JobStatusController::class, 'getActiveJobs']);
    Route::get('/team-jobs', [JobStatusController::class, 'getTeamJobs']);
    Route::get('/job-batch/{batchId}', [JobStatusController::class, 'getBatchInfo']);
});