<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CrawlProject1VideosJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大超时时间（秒）：爬虫涉及外部多接口及资源下载，设定 30 分钟
     */
    public int $timeout = 1800;

    /**
     * 最大失败重试次数
     */
    public int $tries = 3;

    /**
     * 片商 slug（可选，为 null 时抓取全部配置片商）
     */
    public ?string $channel;

    /**
     * 每次抓取上限条数
     */
    public int $limit;

    /**
     * 指定抓取的页码
     */
    public int $page;

    /**
     * 任务类型描述（用于 Horizon 面板直观展示）
     */
    public string $task_name = 'Project1 视频列表抓取';

    /**
     * 派发时间
     */
    public string $dispatched_at;

    /**
     * Create a new job instance.
     */
    public function __construct(?string $channel = null, int $limit = 24, int $page = 1)
    {
        $this->channel = $channel;
        $this->limit = $limit;
        $this->page = max(1, $page);
        $this->dispatched_at = now()->toDateTimeString();
    }

    /**
     * Horizon 面板专属标签（会在面板列表及详情顶部高亮展示为彩色徽章，并支持在搜索栏按标签检索）
     */
    public function tags(): array
    {
        return array_filter([
            'crawler',
            $this->channel ?: 'all-channels',
            "page:{$this->page}",
            "limit:{$this->limit}",
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("Horizon 队列开始执行爬虫任务 [CrawlProject1VideosJob]" . ($this->channel ? " [片商: {$this->channel}]" : " [全部片商]") . " [第 {$this->page} 页, 每页 {$this->limit} 条]");

        $params = [
            '--limit' => $this->limit,
            '--page'  => $this->page,
        ];

        if ($this->channel) {
            $params['channel'] = $this->channel;
        }

        Artisan::call('crawler:project1-videos', $params);

        if ($this->channel && $this->page > 1) {
            $cacheKey = "crawler:history:{$this->channel}:last_page";
            $lastPage = (int) Cache::get($cacheKey, 0);
            if ($this->page > $lastPage) {
                Cache::forever($cacheKey, $this->page);
            }
        }

        $output = trim(Artisan::output());
        if (!empty($output)) {
            Log::info("视频爬虫任务执行输出:\n" . $output);
        }
    }
}

