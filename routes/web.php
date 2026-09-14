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
use App\Http\Controllers\UserSpaceController;
use App\Http\Controllers\Forum\ThreadController;
use App\Http\Controllers\Settings\ProfileBannerController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\MedalController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\GrowthController;
use App\Http\Controllers\AvatarDecorationController;
use Illuminate\Support\Facades\DB;

// 你能犯的最昂贵的错误，就是试图通过浪费时间来省钱。真正的顶尖高手，绝不会在自己的视野和眼界上妥协将就。
// TODO: 在执行 post 操作时发现登录 cookie 过期了，该如何处理？
// TODO：上线前查看SQL 语句并添加复合索引
// TODO:路由上添加限速器
// TODO:统一所有按钮的样式和大小：https://www.youtube.com/results?search_query=%E5%BD%AD%E5%9D%A6 （订阅、查看频道）
// TODO：当初在 http://localhost/videos/1/shes-not-that-into-anal-or-is-she 时点击退出登录，应该跳转首页还是当前页面？
// TODO:用户名和昵称不允许包含特殊字符，具体的规则参考主流网站的：X
// TODO: 登录页面接入 cloudflare 的验证，否则不允许点击登录按钮
// TODO:增加用户上传头像、横幅内容审核功能，按添加时间排序
// TODO:如果用户未验证邮箱也会进行点赞、收藏等操作限制
// TODO:支付方式选择 V 免签和 虎皮椒
// TODO:请问你需要我为你继续编写对应的 Laravel Eloquent Models（带关联关系），还是这个用于自动判定勋章发放的 MedalCriteriaEvaluator 规则匹配服务？
// TODO:增加个人资料查看权限
// TODO: 当访问 http://localhost/medals 不存在的路由页面时，应该显示 404 页面
// TODO:如果配置了两步验证后，登录后未跳转到redirect链接，当登录页面 url 是http://localhost/login?redirect=%2Fvideos%2F2%2Fim-off-the-clock-gimme-your-cock
// TODO:当用户点赞某个帖子后过了一个月又取消了，那么怎么处理统计呢？还是说不将这种可以撤销的行为算入总积分什么的？
// TODO:在用户执行特殊操作时需要记录 ip 地址 v4 v6
// TODO：在 http://localhost/avatar-decorations 佩戴头饰时，如何更改 sidebar 中的头像头饰
// https://pdcams.com/cam/bongacams/DemonicAngel/
// TODO:前端支付页面的倒计时功能在切换到别的标签页后会暂停，导致支付超时后用户还在继续支付，应该在切换标签页时继续倒计时
// TODO：修复支付端 tmp_price 表有脏数据的问题
// TODO:跳转支付页面默认不要是空白的，能不能来个正在加载中的动画？
// TODO：每次发布代码后日志文件就会重新生成，历史日志文件消失，不应该消失。

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
Route::get('/store/product/{product}/{slug}', [StoreController::class, 'show'])->name('store.product.show');
Route::get('/vip', [VipController::class, 'index'])->name('vip.index');
Route::get('/search', [SearchController::class, 'index'])->name('search');
Route::post('/videos/cascade-filters', [VideoController::class, 'getCascadeFilters']);

Route::get('/@{user:name}/{tab?}', [UserSpaceController::class, 'show'])
    ->name('userspace.show');

// 用户勋章荣誉馆详情页
Route::get('/@{user:name}/medals', [MedalController::class, 'userMedals'])->name('user.medals');

Route::match(['get', 'post'], '/wallet/notify', [WalletController::class, 'notify'])
    ->name('wallet.notify');

// 🎯 论坛模块路由组
Route::prefix('forum')->name('forum.')->controller(ForumController::class)->group(function () {

    // 1. 论坛板块大厅首页: GET /forum
    Route::get('/', 'index')->name('index');

    // 2. 单个版块主题列表页: GET /forum/nodes/{id}
    Route::get('/nodes/{node}', 'show')->name('nodes.show');

    // 🎯 用户悬浮卡片公开接口（无需登录也可查看）
    Route::get('/users/{user}/hover-card', 'userHoverCard')
        ->name('users.hover-card');

    // 🎯 兼容: /threads/31-slug (第1页) 与 /threads/31-slug/page-3 (第3页)
    Route::get('/threads/{thread}/{slug?}/page-{page}', [ThreadController::class, 'page'])
        ->where(['page' => '[0-9]+'])
        ->name('threads.show.page');

    // 帖子详情展示
    Route::get('/threads/{thread}/{slug?}', [ThreadController::class, 'show'])
        ->name('threads.show');

    // 提交回帖 (需要登录中间件 auth)
    Route::post('/threads/{thread}/posts', [ThreadController::class, 'storePost'])
        ->middleware('auth')
        ->name('threads.posts.store');

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

    Route::get('/settings/banner', [ProfileBannerController::class, 'edit'])->name('profile.banner.edit');
    Route::post('/settings/banner', [ProfileBannerController::class, 'update'])->name('profile.banner.update');
    Route::delete('/settings/banner', [ProfileBannerController::class, 'destroy'])->name('profile.banner.destroy');

    Route::post('/subscribe/{type}/{id}', [SubscriptionController::class, 'subscribe'])
        ->name('entities.subscribe');

    Route::post('/unsubscribe/{type}/{id}', [SubscriptionController::class, 'unsubscribe'])
        ->name('entities.unsubscribe');

    Route::post('/notification/{type}/{id}', [SubscriptionController::class, 'updateNotification'])
        ->name('entities.notification.edit');

    Route::get('/wallet', [WalletController::class, 'index'])->name('wallet.index');

    Route::get('/payment-methods', [WalletController::class, 'paymentMethods'])->name('wallet.payment-methods');
    Route::post('/deposit', [WalletController::class, 'deposit'])->name('wallet.deposit');
    Route::get('/wallet/orders/{orderNo}/status', [WalletController::class, 'checkOrderStatus'])
    ->name('wallet.order.status');

    // 2. 登录用户切换勋章佩戴状态接口
    Route::post('/user/medals/{medal}/toggle-wear', [MedalController::class, 'toggleWear'])
        ->name('user.medals.toggle-wear');

    Route::get('/checkin', [CheckInController::class, 'index'])->name('checkin.index');
    Route::post('/checkin', [CheckInController::class, 'store'])->name('checkin.store');

    Route::get('/user/center/growth', [GrowthController::class, 'index'])->name('growth.index');

    // 挂件列表页面
    Route::get('/avatar-decorations', [AvatarDecorationController::class, 'index'])
        ->name('avatar-decorations.index');

    // 切换佩戴 / 卸下挂件接口
    Route::post('/avatar-decorations/wear', [AvatarDecorationController::class, 'wear'])
        ->name('avatar-decorations.wear');
});

Route::get('/test-timezone', function () {
    // 1. 查询 PostgreSQL 当前会话生效的时区设置
    $pgTimezone = DB::selectOne('SHOW timezone')->TimeZone ?? null;

    // 2. 查询 PostgreSQL 当前事务时间戳
    $pgNow = DB::selectOne('SELECT NOW() as db_time')->db_time ?? null;

    return response()->json([
        'status' => 'success',
        'database_driver' => 'pgsql (PostgreSQL 18)',
        'configurations' => [
            'app_timezone' => config('app.timezone'),
            'php_default_timezone' => date_default_timezone_get(),
            'postgres_session_timezone' => $pgTimezone,
        ],
        'current_times' => [
            'carbon_now' => now()->toDateTimeString(),
            'php_date_now' => date('Y-m-d H:i:s'),
            'postgres_now' => $pgNow,
        ],
    ]);
});


require __DIR__ . '/settings.php';
