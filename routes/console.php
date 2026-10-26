<?php

use App\Jobs\CrawlDorcelVideosJob;
use App\Jobs\CrawlJulesJordanVideosJob;
use App\Jobs\CrawlProject1VideosJob;
use App\Jobs\CrawlTrueAnalVideosJob;
use App\Jobs\CrawlVixenDailyJob;
use App\Jobs\TranslateVideoTitlesJob;
use App\Jobs\SyncVideoViewsToDatabaseJob;
use App\Jobs\SyncChannelSubscribersToDatabaseJob;
use App\Jobs\CrawlXxxclubMagnetsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Project1 视频列表定时爬取任务
 * 每天晚上 20:30 自动投递任务至 Horizon 队列，避免任务重叠
 */
Schedule::job(new CrawlProject1VideosJob())
    ->dailyAt('20:30')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Squirted (潮吹高潮专题片商) 视频增量定时爬取任务
 * 每天晚上 20:34 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'squirted', limit: 24, page: 1))
    ->dailyAt('20:34')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * True Amateurs (真实素人自拍片商) 视频增量定时爬取任务
 * 每天晚上 20:37 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'trueamateurs', limit: 24, page: 1))
    ->dailyAt('20:37')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * SexyHub 片商视频增量定时爬取任务
 * 每天晚上 20:40 自动投递任务至 Horizon 队列，与其他爬虫错峰执行
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'sexyhub', limit: 24, page: 1))
    ->dailyAt('20:40')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Sweetheart Video (女同题材标杆片商) 视频增量定时爬取任务
 * 每天晚上 20:44 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'sweetheartvideo', limit: 24, page: 1))
    ->dailyAt('20:44')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Sweet Sinner (浪漫剧情片商) 视频增量定时爬取任务
 * 每天晚上 20:48 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'sweetsinner', limit: 24, page: 1))
    ->dailyAt('20:48')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Reality Junkies (纪实真人秀片商) 视频增量定时爬取任务
 * 每天晚上 20:52 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'realityjunkies', limit: 24, page: 1))
    ->dailyAt('20:52')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Doghouse Digital (经典硬核片商) 视频增量定时爬取任务
 * 每天晚上 20:56 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'doghousedigital', limit: 24, page: 1))
    ->dailyAt('20:56')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * 每日视频抓取数据汇总并自动推送至 Telegram
 * 每天晚上 21:00（爬虫执行完毕后）生成当日数据大盘与各片商增量报告
 */
Schedule::command('crawler:send-daily-summary')
    ->dailyAt('21:00')
    ->timezone('Asia/Shanghai');

/**
 * Next Door Hobby (邻家爱好主题片商) 视频增量定时爬取任务
 * 每天晚上 21:05 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'nextdoorhobby', limit: 24, page: 1))
    ->dailyAt('21:05')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Milfed (熟女人妻专题片商) 视频增量定时爬取任务
 * 每天晚上 21:10 自动投递任务至 Horizon 队列
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'milfed', limit: 24, page: 1))
    ->dailyAt('21:10')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * 视频中文标题 AI 自动翻译定时任务
 * 每 10 分钟投递一批翻译任务至 Horizon 队列，避免任务重叠
 */
Schedule::job(new TranslateVideoTitlesJob(60))
    ->everyTenMinutes()
    ->withoutOverlapping();

/**
 * Vixen 系列片商每日增量协同定时爬取任务
 * 每天北京时间凌晨 02:00 自动投递任务至 Horizon 队列
 * 执行流程：按片商依次执行——先抓取该片商所有演员数据，然后抓取该片商视频的第 1 页数据
 */
Schedule::job(new CrawlVixenDailyJob())
    ->dailyAt('02:00')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * DorcelClub 厂牌视频增量定时爬取任务
 * 每天在北京时间 02:30、08:30、14:30、20:30（每 6 小时错峰）自动投递任务至 Horizon 队列：
 * 1. 对应法国巴黎当地时间约 19:30、01:30、07:30、13:30，全天候覆盖欧洲工作日与晚间黄金发布窗口；
 * 2. 开启 skipExisting 极速增量模式：无新片时仅拉取 1 次列表比对 UUID，耗时仅 1~2 秒，零性能开销；
 * 3. 一旦官网有新上架视频，最迟数小时内即可迅速捕获并自动入库；
 * 4. 开启 withoutOverlapping 避免并发重叠。
 */
Schedule::job(new CrawlDorcelVideosJob(page: 1, skipExisting: true))
    ->cron('30 2,8,14,20 * * *')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Jules Jordan 厂牌视频增量定时爬取任务
 * 每天在北京时间 14:00 与 19:00 各执行一次，自动投递任务至 Horizon 队列：
 * 1. 抓取第 1 页最新视频列表；
 * 2. 开启 skipExisting 极速增量模式：已入库视频跳过详情抓取，仅抓取新上架视频；
 * 3. 开启 withoutOverlapping 避免并发重叠。
 */
Schedule::job(new CrawlJulesJordanVideosJob(page: 1, skipExisting: true))
    ->cron('0 14,19 * * *')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * TrueAnal 厂牌视频增量定时爬取任务
 * 每天在北京时间 19:10 自动投递任务至 Horizon 队列：
 * 1. 抓取第 1 页最新视频列表；
 * 2. 开启 skipExisting 极速增量模式：已入库视频跳过详情抓取，仅抓取新上架视频；
 * 3. 开启 withoutOverlapping 避免并发重叠。
 */
Schedule::job(new CrawlTrueAnalVideosJob(page: 1, skipExisting: true))
    ->dailyAt('19:10')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * 视频浏览量 Redis 缓冲批量回写持久化任务
 * 每 5 分钟将 Redis 缓冲的增量批量同步至数据库，避免高频并发写锁
 */
Schedule::job(new SyncVideoViewsToDatabaseJob())
    ->everyFiveMinutes()
    ->withoutOverlapping();

/**
 * 视频点赞数与收藏数全量对齐校准定时任务
 * 每天凌晨 03:30 自动执行一次，确保事实反应表与视频表计数绝对一致
 */
Schedule::command('video:sync-counts')
    ->dailyAt('03:30')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * 片商订阅数 Redis 缓冲批量回写持久化任务
 * 每 2 分钟将 Redis 缓冲的增量批量同步至数据库 channels 表
 */
Schedule::job(new SyncChannelSubscribersToDatabaseJob())
    ->everyTwoMinutes()
    ->withoutOverlapping();

/**
 * 片商订阅数全量对齐校准定时任务
 * 每天凌晨 03:40 自动执行一次，确保事实反应表与片商表计数绝对一致
 */
Schedule::command('channel:sync-subscribers')
    ->dailyAt('03:40')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * xxxclub 视频磁力下载资源定时抓取与自动补充任务
 * 每间隔 10 分钟自动投递任务至 Horizon 队列，每次处理 15 部有效目标视频：
 * 1. 默认仅筛选已上映片源 (onlyReleased: true)，彻底跳过未到排期的未来预售片；
 * 2. 启用 7 天失败冷却期 (cooldownDays: 7)，未搜到资源的视频跳过并自动向后顺延补齐；
 * 3. 单批 15 部耗时约 2~3 分钟，完美契合 10 分钟调度周期，杜绝单次任务超长触发 30 分钟超时熔断；
 * 4. 开启 withoutOverlapping(15)，确保上一批未跑完时不会重叠触发。
 */
Schedule::job(new CrawlXxxclubMagnetsJob(limit: 15, onlyReleased: true, cooldownDays: 7))
    ->everyTenMinutes()
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping(15);

/**
 * Family Sinners (已完结归档系列) 凌晨低频巡检爬取任务
 * 每天凌晨 04:10 自动投递任务至 Horizon 队列，轻量检测是否有新剧照或重新发行版本
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'familysinners', limit: 24, page: 1))
    ->dailyAt('04:10')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();

/**
 * Sex On Sight (已完结归档系列) 凌晨低频巡检爬取任务
 * 每天凌晨 04:20 自动投递任务至 Horizon 队列，轻量检测是否有新剧照或重新发行版本
 */
Schedule::job(new CrawlProject1VideosJob(channel: 'sexonsight', limit: 24, page: 1))
    ->dailyAt('04:20')
    ->timezone('Asia/Shanghai')
    ->withoutOverlapping();





