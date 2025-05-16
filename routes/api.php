<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WebsiteController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\PageController;

Route::resource('conversations', ConversationController::class);
Route::resource('conversations/{conversation}/chats', ChatController::class);
Route::resource('websites', WebsiteController::class);
Route::resource('websites/{website}/pages', PageController::class);