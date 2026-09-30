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
    public bool $forceImages;
    public bool $all;
    public string $task_name = 'Vixen 视频列表与详情抓取';
    public string $dispatched_at;

    public function __construct(
        ?string $channel = null,
        int $page = 1,
        bool $downloadImages = true,
        bool $forceImages = false,
        bool $all = false
    ) {
        $this->channel = $channel;
        $this->page = max(1, $page);
        $this->downloadImages = $downloadImages;
        $this->forceImages = $forceImages;
        $this->all = $all;
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
            $this->all ? 'auto-paging' : 'single-page',
        ]);
    }

    public function handle(VixenCrawlerService $crawlerService): void
    {
        Log::info("Horizon 队列开始执行 Vixen 视频抓取任务 [片商: " . ($this->channel ?: '全部') . "] [第 {$this->page} 页]");

        $result = $crawlerService->crawlVideos($this->channel, $this->page, 'single', $this->downloadImages, $this->forceImages);

        if (!$result['success']) {
            Log::warning("Vixen 视频抓取任务未完成: " . ($result['message'] ?? '未知错误'));
            return;
        }

        Log::info("Vixen 视频抓取任务完成 [第 {$this->page} 页]: 成功 {$result['success_count']}，跳过 {$result['skipped_count']}，失败 {$result['failed_count']}");

        // 如果开启了 --all 模式且远端还有下一页，自动链式派发下一页 Job
        if ($this->all && !empty($result['has_next_page'])) {
            $nextPage = $this->page + 1;
            Log::info("Vixen 视频抓取自动派发下一页 Job [片商: " . ($this->channel ?: '全部') . "] [第 {$nextPage} 页]");
            self::dispatch($this->channel, $nextPage, $this->downloadImages, $this->forceImages, true);
        }
    }
}

