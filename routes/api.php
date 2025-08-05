<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\ApifyImportController;

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

    // Organization routes
    Route::resource('organizations', OrganizationController::class);
    Route::post('organizations/{id}/restore', [OrganizationController::class, 'restore']);

    // Apify import routes
    Route::prefix('apify')->group(function () {
        Route::post('start-import', [ApifyImportController::class, 'startImport']);
        Route::get('imports', [ApifyImportController::class, 'getImports']);
        Route::get('imports/{apifyRun}', [ApifyImportController::class, 'getImport']);
    });
});
