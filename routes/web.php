<?php

use Illuminate\Support\Facades\Route;

// Laravel Pulse Dashboard
Route::middleware('web')->group(function () {
    // Pulse routes will be automatically registered
});

Route::get('/{any?}', function () {
    return view('app');
})->where('any', '^(?!pulse).*$'); // Exclude 'pulse' path from the catch-all route
