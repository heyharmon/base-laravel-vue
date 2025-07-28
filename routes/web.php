<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Admin\PageController as AdminPageController;

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('pages', [AdminPageController::class, 'index'])->name('pages.index');
    Route::get('pages/create', [AdminPageController::class, 'create'])->name('pages.create');
    Route::post('pages', [AdminPageController::class, 'store'])->name('pages.store');
});

Route::get('/pages/{page:slug}', [PageController::class, 'show'])->name('pages.show');
