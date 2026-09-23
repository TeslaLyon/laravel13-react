<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class TranslateVideoTitlesJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大超时时间（秒）：每次处理 60 条约耗时 15-30 秒，设定 10 分钟充足上限
     */
    public int $timeout = 600;

    /**
     * 最大失败重试次数
     */
    public int $tries = 2;

    /**
     * 本次最多翻译视频条数
     */
    public int $limit;

    /**
     * Create a new job instance.
     */
    public function __construct(int $limit = 60)
    {
        $this->limit = $limit;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Horizon 队列开始执行视频标题翻译任务 [TranslateVideoTitlesJob] [Limit: {$this->limit}]");

        Artisan::call('videos:translate-titles', [
            '--limit' => $this->limit,
        ]);

        $output = trim(Artisan::output());
        if (!empty($output)) {
            Log::info("视频标题翻译任务执行输出:\n" . $output);
        }
    }
}

