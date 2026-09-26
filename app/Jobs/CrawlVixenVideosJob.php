<?php

namespace App\Jobs;

use App\Services\Crawler\VixenCrawlerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CrawlVixenVideosJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800; // 30分钟
    public int $tries = 3;

    public ?string $channel;
    public int $page;
    public bool $downloadImages;
    public string $task_name = 'Vixen 视频列表与详情抓取';
    public string $dispatched_at;

    public function __construct(?string $channel = null, int $page = 1, bool $downloadImages = true)
    {
        $this->channel = $channel;
        $this->page = max(1, $page);
        $this->downloadImages = $downloadImages;
        $this->dispatched_at = now()->toDateTimeString();
    }

    public function tags(): array
    {
        return array_filter([
            'crawler',
            'vixen',
            'videos',
            $this->channel ?: 'all-channels',
            "page:{$this->page}",
        ]);
    }

    public function handle(VixenCrawlerService $crawlerService): void
    {
        Log::info("Horizon 队列开始执行 Vixen 视频抓取任务 [片商: " . ($this->channel ?: '全部') . "] [第 {$this->page} 页]");

        $result = $crawlerService->crawlVideos($this->channel, $this->page, 'single', $this->downloadImages);

        if (!$result['success']) {
            Log::warning("Vixen 视频抓取任务未完成: " . ($result['message'] ?? '未知错误'));
            return;
        }

        Log::info("Vixen 视频抓取任务完成 [第 {$this->page} 页]: 成功 {$result['success_count']}，跳过 {$result['skipped_count']}，失败 {$result['failed_count']}");
    }
}

