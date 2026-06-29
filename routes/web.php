<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VideosController;

// TODO: 处在 http://localhost/videos/ 页面，然后登录 cookie 过期了，跳转到登录页面，要求实现的功能是登录后再跳转回登录之前的页面
// TODO: 在执行 post 操作时发现登录 cookie 过期了，该如何处理？

// Route::inertia('/', 'welcome')->name('home');

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::inertia('dashboard', 'dashboard')->name('dashboard');
// });
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/videos', [VideosController::class, 'index'])->name('videos.index');
Route::middleware(['verified'])->group(function () {});


require __DIR__ . '/settings.php';
