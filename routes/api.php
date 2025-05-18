<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\TeamController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    
    Route::resource('conversations', ConversationController::class);
    Route::resource('conversations/{conversation}/chats', ChatController::class);
    Route::resource('websites', WebsiteController::class);
    
    // Team routes
    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/{team}/accept-invitation', [TeamController::class, 'acceptInvitation']);
    Route::post('teams/{team}/decline-invitation', [TeamController::class, 'declineInvitation']);
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::put('teams/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);
});