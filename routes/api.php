<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\KeywordController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\RunController;
use App\Http\Controllers\ResponseController;
use App\Http\Controllers\PromptRunController;
use App\Http\Controllers\AnalyticsController;

Route::resource('conversations', ConversationController::class);
Route::resource('conversations/{conversation}/chats', ChatController::class);
Route::resource('websites', WebsiteController::class);

// LLM Alerts API Routes
Route::resource('keywords', KeywordController::class);
Route::resource('prompts', PromptController::class);
Route::resource('runs', RunController::class);
Route::resource('runs/{run}/responses', ResponseController::class);

// Run prompts against LLM providers
Route::post('prompts/{prompt}/run', [PromptRunController::class, 'store']);

// Analytics endpoints
Route::get('analytics/keywords', [AnalyticsController::class, 'keywordStats']);
Route::get('analytics/prompts', [AnalyticsController::class, 'promptStats']);
Route::get('analytics/timeseries', [AnalyticsController::class, 'timeSeriesData']);