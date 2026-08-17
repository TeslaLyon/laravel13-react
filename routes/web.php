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
use App\Http\Controllers\VideoCorrectionController;
use App\Http\Controllers\FeedbackController;
use App\Http\Controllers\ActorCorrectionController;
use App\Http\Controllers\VideoSubtitleFeedbackController;
use App\Http\Controllers\VideoDownloadSubmissionController;
use App\Http\Controllers\Forum\ForumController;
use App\Http\Controllers\Settings\ProfileController;

// TODO: 处在 http://localhost/videos/ 页面，然后登录 cookie 过期了，跳转到登录页面，要求实现的功能是登录后再跳转回登录之前的页面
// TODO: 在执行 post 操作时发现登录 cookie 过期了，该如何处理？
// TODO：上线前查看SQL 语句并添加复合索引
// TODO:路由上添加限速器
// TODO:统一所有按钮的样式和大小：https://www.youtube.com/results?search_query=%E5%BD%AD%E5%9D%A6 （订阅、查看频道）
// TODO: 将数据库 status 字段类型都更换为int类型的，
// TODO：当初在 http://localhost/videos/1/shes-not-that-into-anal-or-is-she 时点击退出登录，应该跳转首页还是当前页面？
// TODO:用户名和昵称不允许包含特殊字符，具体的规则参考主流网站的：X
// TODO: 登录页面接入 cloudflare 的验证，否则不允许点击登录按钮

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
Route::get('/actors/{actor}/{slug}/{tab?}', [ActorController::class, 'show'])->name('actors.show');
Route::get('/pictures', [PictureController::class, 'index'])->name('pictures.index');
Route::get('/channels', [ChannelController::class, 'index'])->name('channels.index');
Route::get('/channels/{channel}/{slug}/{tab?}', [ChannelController::class, 'show'])->where('tab', 'home|videos|photos|about')->name('channels.show');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{article}/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
Route::get('/categories/{category}/{slug}/{tab?}', [CategoryController::class, 'show'])
    ->where('tab', 'home|videos|photos')
    ->name('categories.show');
Route::get('/store', [StoreController::class, 'index'])->name('store.index');
Route::get('/store/product', [StoreController::class, 'show'])->name('store.show');
Route::get('/vip', [VipController::class, 'index'])->name('vip.index');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::post('/videos/cascade-filters', [VideoController::class, 'getCascadeFilters']);

// 🎯 论坛模块路由组
Route::prefix('forum')->name('forum.')->controller(ForumController::class)->group(function () {

    // 1. 论坛板块大厅首页: GET /forum
    Route::get('/', 'index')->name('index');

    // 2. 单个版块主题列表页: GET /forum/nodes/{id}
    Route::get('/nodes/{id}', 'show')->name('nodes.show');

});


// TODO: 该路由组下所有路由都需要检查在调用前是否验证了邮箱，没有的话就弹出提示框
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

    // 处理上传本地字幕文件或提交外部字幕链接
    Route::post('/videos/{video}/{slug}/subtitles/upload', [VideoController::class, 'subtitleUpload'])
        ->name('videos.subtitles.upload');

    // 处理提交求字幕申请
    Route::post('/videos/{video}/{slug}/subtitles/request', [VideoController::class, 'subtitleRequest'])
        ->name('videos.subtitles.request');
    Route::post('/videos/{video}/{slug}/subtitles/{subtitle}/download', [VideoController::class, 'subtitleDownload'])
        ->name('videos.subtitles.download');
    Route::post('/videos/{video}/{slug}/subtitles/{subtitle}/feedback', [VideoSubtitleFeedbackController::class, 'store'])
        ->name('subtitles.feedback.store');
    Route::post('/videos/{video}/{slug}/download-submission', [VideoDownloadSubmissionController::class, 'store'])
        ->name('video.download-submission.store');


    Route::post('/channels/{channel}/{slug}/subscribe', [ChannelController::class, 'toggleSubscribe'])
        ->name('channels.subscribe');
    Route::get('/channels/{channel}/{slug}/subscribe-status', [ChannelController::class, 'subscribeStatus'])
        ->name('channels.subscribe.status');
    Route::post('/actors/{actor}/{slug}/follow', [ActorController::class, 'follow'])
        ->name('actors.follow');
    Route::get('/actors/{actor}/{slug}/menu-status', [ActorController::class, 'menuStatus'])
        ->name('actors.menu.status');
    Route::post('/actors/{actor}/{slug}/corrections', [ActorCorrectionController::class, 'store'])
        ->name('actors.corrections.store');

    Route::prefix('search')->group(function () {
        Route::get('/actors', [SearchController::class, 'actors']);
        Route::get('/categories', [SearchController::class, 'categories']);
        Route::get('/tags', [SearchController::class, 'tags']);
    });

    // Route::post('/videos/{video}/{slug}/report', [VideoController::class, 'report'])
    //     ->name('videos.report');
    Route::post('/videos/{video}/{slug}/corrections', [VideoCorrectionController::class, 'store'])
        ->name('videos.corrections');

    // 提交全站通用反馈
    Route::post('/feedback', [FeedbackController::class, 'store'])->name('feedback.store');


    Route::get('/settings/avatar', [ProfileController::class, 'editAvatar'])->name('profile.avatar.edit');
    Route::post('/settings/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
});


require __DIR__ . '/settings.php';
