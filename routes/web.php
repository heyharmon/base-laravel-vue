<?php

use Illuminate\Support\Facades\Route;

Route::any('/{any?}', function () {
    return redirect('https://app.paraloom.ai');
})->where('any', '.*');
