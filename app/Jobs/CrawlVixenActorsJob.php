<?php

namespace App\Jobs;

use App\Services\Crawler\VixenCrawlerService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CrawlVixenActorsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1200; // 20分钟
    public int $tries = 3;

    public ?string $channel;
    public int $page;
    public bool $downloadImages;
    public bool $forceImages;
    public string $task_name = 'Vixen 演员数据抓取';
    public string $dispatched_at;

    public function __construct(?string $channel = null, int $page = 1, bool $downloadImages = true, bool $forceImages = false)
    {
        $this->channel = $channel;
        $this->page = max(1, $page);
        $this->downloadImages = $downloadImages;
        $this->forceImages = $forceImages;
        $this->dispatched_at = now()->toDateTimeString();
    }

    public function tags(): array
    {
        return array_filter([
            'crawler',
            'vixen',
            'actors',
            $this->channel ?: 'all-channels',
            "page:{$this->page}",
        ]);
    }

    public function handle(VixenCrawlerService $crawlerService): void
    {
        Log::info("Horizon 队列开始执行 Vixen 演员抓取任务 [片商: " . ($this->channel ?: '全部') . "] [第 {$this->page} 页]");

        $result = $crawlerService->crawlActors($this->channel, $this->page, $this->downloadImages, $this->forceImages);

        if (!$result['success']) {
            Log::warning("Vixen 演员抓取任务未完成: " . ($result['message'] ?? '未知错误'));
            return;
        }

        Log::info("Vixen 演员抓取任务完成 [第 {$this->page} 页]: 成功 {$result['success_count']}，跳过 {$result['skipped_count']}，失败 {$result['failed_count']}");
    }
}

