<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CrawlDorcelScreenImgJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大超时时间（秒）：涉及外部抓取与网络延迟，设定 30 分钟
     */
    public int $timeout = 1800;

    /**
     * 最大失败重试次数
     */
    public int $tries = 1;

    /**
     * 单个视频 ID（可选）
     */
    public ?int $videoId;

    /**
     * 处理上限
     */
    public int $limit;

    /**
     * 请求间隔延迟（秒）
     */
    public int $requestDelay;

    /**
     * 网络代理
     */
    public ?string $proxy;

    /**
     * 是否强制覆盖
     */
    public bool $force;

    /**
     * 是否演练模式
     */
    public bool $dryRun;

    /**
     * 是否全量处理
     */
    public bool $all;

    /**
     * 冷静期天数
     */
    public int $cooldownDays;

    /**
     * 是否忽略冷静期
     */
    public bool $ignoreCooldown;

    /**
     * 是否禁用 Telegram 推送
     */
    public bool $noTelegram;

    /**
     * 任务描述（Horizon 展示）
     */
    public string $task_name = 'DorcelClub 视频片段剧照切片抓取与补充';

    /**
     * 派发时间
     */
    public string $dispatched_at;

    /**
     * Create a new job instance.
     */
    public function __construct(
        ?int $videoId = null,
        int $limit = 50,
        int $requestDelay = 1,
        ?string $proxy = null,
        bool $force = false,
        bool $dryRun = false,
        bool $all = false,
        int $cooldownDays = 7,
        bool $ignoreCooldown = false,
        bool $noTelegram = false
    ) {
        $this->videoId = $videoId;
        $this->limit = $limit;
        $this->requestDelay = $requestDelay;
        $this->proxy = $proxy;
        $this->force = $force;
        $this->dryRun = $dryRun;
        $this->all = $all;
        $this->cooldownDays = $cooldownDays;
        $this->ignoreCooldown = $ignoreCooldown;
        $this->noTelegram = $noTelegram;
        $this->dispatched_at = now()->toDateTimeString();
    }

    /**
     * Horizon 面板专属标签
     */
    public function tags(): array
    {
        return array_filter([
            'crawler',
            'dorcelclub',
            'screen-img',
            $this->videoId ? "video:{$this->videoId}" : null,
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $scopeDesc = $this->videoId ? " [视频 ID: {$this->videoId}]" : ($this->all ? " [全量处理]" : " [限制: {$this->limit} 部]");
        Log::info("🚀 Horizon 队列开始执行 DorcelClub 剧照抓取任务 [CrawlDorcelScreenImgJob]{$scopeDesc}");

        $params = [
            '--limit' => $this->limit,
            '--delay' => $this->requestDelay,
            '--cooldown-days' => $this->cooldownDays,
        ];

        if ($this->all) {
            $params['--all'] = true;
        }

        if ($this->videoId) {
            $params['--video-id'] = $this->videoId;
        }

        if ($this->proxy) {
            $params['--proxy'] = $this->proxy;
        }

        if ($this->force) {
            $params['--force'] = true;
        }

        if ($this->dryRun) {
            $params['--dry-run'] = true;
        }

        if ($this->ignoreCooldown) {
            $params['--ignore-cooldown'] = true;
        }

        if ($this->noTelegram) {
            $params['--no-telegram'] = true;
        }

        $exitCode = Artisan::call('crawler:dorcel-screen-img', $params);
        $output = trim(Artisan::output());

        if (!empty($output)) {
            Log::info("DorcelClub 剧照抓取任务控制台输出汇总:\n" . $output);
        }

        if ($exitCode === 0) {
            Log::info("🎉 DorcelClub 剧照抓取队列任务执行成功！");
        } else {
            Log::warning("⚠️ DorcelClub 剧照抓取队列任务执行结束，返回退出码: {$exitCode}");
        }
    }
}

