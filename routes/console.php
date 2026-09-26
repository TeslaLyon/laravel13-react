<?php

use App\Jobs\CrawlProject1VideosJob;
use App\Jobs\CrawlVixenDailyJob;
use App\Jobs\TranslateVideoTitlesJob;
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
