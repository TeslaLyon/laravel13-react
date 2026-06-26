<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VideosController;

// Route::inertia('/', 'welcome')->name('home');

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::inertia('dashboard', 'dashboard')->name('dashboard');
// });
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/videos', [VideosController::class, 'index'])->name('videos');
Route::middleware(['verified'])->group(function () {});


require __DIR__ . '/settings.php';
