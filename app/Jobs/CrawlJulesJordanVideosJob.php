<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CrawlJulesJordanVideosJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大超时时间（秒）：单页约 32 部视频，设为 30 分钟足够充裕
     */
    public int $timeout = 1800;

    /**
     * 最大失败重试次数
     */
    public int $tries = 3;

    /**
     * 指定抓取的页码
     */
    public int $page;

    /**
     * 是否跳过已入库视频（极速日常增量模式）
     */
    public bool $skipExisting;

    /**
     * 抓取单部详情页间隔秒数
     */
    public int $crawlDelay;

    /**
     * 每页处理视频上限数量（null 为整页全部）
     */
    public ?int $limit;

    /**
     * 是否为演练模式
     */
    public bool $dryRun;

    /**
     * 任务类型描述（用于 Horizon 面板直观展示）
     */
    public string $task_name = 'Jules Jordan 视频列表与详情抓取';

    /**
     * 派发时间
     */
    public string $dispatched_at;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $page = 1,
        bool $skipExisting = true,
        int $crawlDelay = 1,
        ?int $limit = null,
        bool $dryRun = false
    ) {
        $this->page = max(1, $page);
        $this->skipExisting = $skipExisting;
        $this->crawlDelay = max(0, $crawlDelay);
        $this->limit = $limit;
        $this->dryRun = $dryRun;
        $this->dispatched_at = now()->toDateTimeString();
    }

    /**
     * Horizon 面板专属标签（高亮展示为彩色徽章，并支持在搜索栏按标签检索）
     */
    public function tags(): array
    {
        return array_filter([
            'crawler',
            'julesjordan',
            "page:{$this->page}",
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("🚀 Horizon 队列开始执行 Jules Jordan 视频抓取任务 [CrawlJulesJordanVideosJob] [第 {$this->page} 页]");

        $params = [
            '--page'  => $this->page,
            '--delay' => $this->crawlDelay,
        ];

        if ($this->skipExisting) {
            $params['--skip-existing'] = true;
        }

        if ($this->limit !== null) {
            $params['--limit'] = $this->limit;
        }

        if ($this->dryRun) {
            $params['--dry-run'] = true;
        }

        $exitCode = Artisan::call('crawler:jules-jordan', $params);
        $output = trim(Artisan::output());

        if (!empty($output)) {
            Log::info("Jules Jordan 爬虫 [第 {$this->page} 页] 控制台输出:\n" . $output);
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException("Jules Jordan 爬虫 [第 {$this->page} 页] 执行失败，退出码: {$exitCode}");
        }
    }
}

