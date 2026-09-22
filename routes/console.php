<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Project1 视频列表定时爬取任务
 * 默认每小时执行一次，避免任务重叠，后台静默运行
 */
Schedule::command('crawler:project1-videos')
    ->hourly()
    ->withoutOverlapping()
    ->runInBackground();

/**
 * 视频中文标题 AI 自动翻译定时任务
 * 每 10 分钟执行一批（默认处理 60 条），避免任务重叠，后台静默运行
 */
Schedule::command('videos:translate-titles --limit=60')
    ->everyTenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

