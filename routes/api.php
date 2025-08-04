<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\CategorizationController;

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

    // Banking routes
    Route::resource('accounts', AccountController::class);
    Route::resource('categories', CategoryController::class);
    Route::post('transactions/upload-csv', [TransactionController::class, 'uploadCsv']);
    Route::put('transactions/bulk-update-category', [TransactionController::class, 'bulkUpdateCategory']);
    Route::resource('transactions', TransactionController::class);

    // Categorization routes
    Route::post('transactions/{transaction}/categorize', [CategorizationController::class, 'categorizeTransaction']);
    Route::post('transactions/categorize-batch', [CategorizationController::class, 'categorizeBatch']);
    Route::post('transactions/categorize-all', [CategorizationController::class, 'categorizeAll']);
    Route::get('categorization/jobs', [CategorizationController::class, 'getJobs']);
    Route::get('categorization/jobs/active', [CategorizationController::class, 'getActiveJobs']);
    Route::get('categorization/jobs/{batchId}/status', [CategorizationController::class, 'getJobStatus']);
});
