<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CrawlVixenDailyJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大超时时间（秒）：多片商演员及视频下载，设定 60 分钟
     */
    public int $timeout = 3600;

    /**
     * 最大失败重试次数
     */
    public int $tries = 2;

    /**
     * 片商 slug（可选，为 null 时遍历所有片商）
     */
    public ?string $channel;

    /**
     * 任务类型描述（用于 Horizon 面板直观展示）
     */
    public string $task_name = 'Vixen 每日增量抓取 (全量演员+最新视频)';

    /**
     * 派发时间
     */
    public string $dispatched_at;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $channel = null)
    {
        $this->channel = $channel;
        $this->dispatched_at = now()->toDateTimeString();
    }

    /**
     * Horizon 面板专属徽章标签
     */
    public function tags(): array
    {
        return array_filter([
            'crawler',
            'vixen',
            'daily-sync',
            $this->channel ?: 'all-channels',
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("🚀 Horizon 队列开始执行 Vixen 每日增量协同任务 [CrawlVixenDailyJob]" . ($this->channel ? " [片商: {$this->channel}]" : " [全部片商]"));

        $params = [];
        if ($this->channel) {
            $params['channel'] = $this->channel;
        }

        Artisan::call('crawler:vixen-daily', $params);

        $output = trim(Artisan::output());
        if (!empty($output)) {
            Log::info("Vixen 每日爬虫任务执行完毕，控制台输出:\n" . $output);
        }
    }
}
