<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\KeywordResponsesController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamController;
use App\Http\Middleware\EnsureHasTeam;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/invitations/verify', [InvitationController::class, 'verify']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Analytics endpoints
    Route::get('analytics/keywords', [AnalyticsController::class, 'keywordStats']);
    Route::get('analytics/prompts', [AnalyticsController::class, 'promptStats']);
    Route::get('analytics/timeseries', [AnalyticsController::class, 'timeSeriesData']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    Route::resource('conversations', ConversationController::class);
    Route::resource('conversations/{conversation}/chats', ChatController::class);
    Route::resource('websites', WebsiteController::class);

    // Core resources
    Route::middleware(EnsureHasTeam::class)->group(function () {
        Route::resource('keywords', KeywordController::class);
        Route::resource('prompts', PromptController::class);
    });
    Route::resource('runs', RunController::class);

    // Keyword responses
    Route::get('keywords/{keyword}/prompts/{prompt}/responses', [KeywordResponsesController::class, 'index']);

    // Running prompts
    Route::post('prompts/{prompt}/run', [PromptRunController::class, 'store']);

    // Run responses TODO: May never use this
    Route::resource('runs/{run}/responses', ResponseController::class);

    Route::resource('websites/{website}/pages', PageController::class);
    
    // Team routes
    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/{team}/switch', [TeamController::class, 'switchTeam']);
    Route::post('teams/{team}/accept-invitation', [TeamController::class, 'acceptInvitation']);
    Route::post('teams/{team}/decline-invitation', [TeamController::class, 'declineInvitation']);
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::put('teams/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);
});