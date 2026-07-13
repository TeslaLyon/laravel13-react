<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\ActorController;
use App\Http\Controllers\PictureController;
use App\Http\Controllers\ChannelController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\VipController;
use App\Http\Controllers\HelpCenterController;
use App\Http\Controllers\SearchController;

// TODO: 处在 http://localhost/videos/ 页面，然后登录 cookie 过期了，跳转到登录页面，要求实现的功能是登录后再跳转回登录之前的页面
// TODO: 在执行 post 操作时发现登录 cookie 过期了，该如何处理？
// TODO：上线前查看SQL 语句并添加复合索引
// TODO:路由上添加限速器

// Route::inertia('/', 'welcome')->name('home');

// Route::middleware(['auth', 'verified'])->group(function () {
//     Route::inertia('dashboard', 'dashboard')->name('dashboard');
// });
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/help', [HelpCenterController::class, 'index'])->name('help');
Route::get('/help/category/{id}', [HelpCenterController::class, 'category'])->name('help.category');
Route::get('/videos', [VideoController::class, 'index'])->name('videos.index');
Route::get('/videos/{video}/{slug}', [VideoController::class, 'show'])->name('videos.show');
Route::get('/actors', [ActorController::class, 'index'])->name('actors.index');
Route::get('/actors/{actor}/{slug}', [ActorController::class, 'show'])->name('actors.show');
Route::get('/pictures', [PictureController::class, 'index'])->name('pictures.index');
Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
Route::get('/channels/{channel}/{slug}', [ChannelController::class, 'show'])->name('channels.show');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/store', [StoreController::class, 'index'])->name('store.index');
Route::get('/store/product', [StoreController::class, 'show'])->name('store.show');
Route::get('/vip', [VipController::class, 'index'])->name('vip.index');
Route::get('/search', [SearchController::class, 'index'])->name('search');


Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/videos/{video}/{slug}/like', [VideoController::class, 'like'])
        ->name('videos.like');
    Route::post('/videos/{video}/{slug}/dislike', [VideoController::class, 'dislike'])
        ->name('videos.dislike');
    Route::post('/videos/{video}/{slug}/collect', [VideoController::class, 'collect'])
        ->name('videos.collect');
    Route::post('/videos/{video}/{slug}/watch-later', [VideoController::class, 'saveToWatchLater'])
        ->name('videos.watch.later');
    Route::get('/videos/{video}/{slug}/menu-status', [VideoController::class, 'menuStatus'])
        ->name('videos.menu.status');
    Route::post('/channels/{channel}/{slug}/subscribe', [ChannelController::class, 'toggleSubscribe'])
        ->name('channels.subscribe');
    Route::get('/channels/{channel}/{slug}/subscribe-status', [ChannelController::class, 'subscribeStatus'])
    ->name('channels.subscribe.status');
    Route::post('/actors/{actor}/{slug}/follow', [ActorController::class, 'follow'])
        ->name('actors.follow');
    Route::get('/actors/{actor}/{slug}/menu-status', [ActorController::class, 'menuStatus'])
        ->name('actors.menu.status');

});


require __DIR__ . '/settings.php';
