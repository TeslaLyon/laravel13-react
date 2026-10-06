<?php

use App\Jobs\CrawlProject1VideosJob;
use App\Jobs\CrawlVixenDailyJob;
use App\Jobs\TranslateVideoTitlesJob;
use App\Jobs\SyncVideoViewsToDatabaseJob;
use App\Jobs\SyncChannelSubscribersToDatabaseJob;
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



