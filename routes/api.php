<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/invitations/verify', [InvitationController::class, 'verify']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Team routes
    Route::resource('teams', TeamController::class);
    Route::post('teams/{team}/invite', [TeamController::class, 'invite']);
    Route::post('teams/{team}/accept-invitation', [TeamController::class, 'acceptInvitation']);
    Route::post('teams/{team}/decline-invitation', [TeamController::class, 'declineInvitation']);
    Route::delete('teams/{team}/members/{user}', [TeamController::class, 'removeMember']);
    Route::put('teams/{team}/members/{user}/role', [TeamController::class, 'updateMemberRole']);

    // Website bot accessibility routes
    Route::get('websites', [\App\Http\Controllers\Api\WebsiteController::class, 'index']);
    Route::post('websites', [\App\Http\Controllers\Api\WebsiteController::class, 'store']);
    Route::get('websites/{website}', [\App\Http\Controllers\Api\WebsiteController::class, 'show']);
    Route::post('websites/{website}/check-bots', [\App\Http\Controllers\Api\WebsiteController::class, 'checkBots']);
    Route::get('websites/{website}/results', [\App\Http\Controllers\Api\WebsiteController::class, 'results']);
});
