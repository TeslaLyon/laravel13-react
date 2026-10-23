<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CrawlXxxclubMagnetsJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大超时时间（秒）：涉及外部搜索与延迟控制，设定 30 分钟
     */
    public int $timeout = 1800;

    /**
     * 最大失败重试次数
     */
    public int $tries = 1;

    /**
     * 处理上限
     */
    public int $limit;

    /**
     * 片商筛选
     */
    public ?string $channel;

    /**
     * 请求间隔秒数
     */
    public int $requestDelay;

    /**
     * 网络代理
     */
    public ?string $proxy;

    /**
     * FlareSolverr URL
     */
    public ?string $flaresolverrUrl;

    /**
     * 是否仅处理已上映视频（默认 true，自动排除未到排期的未来预售片）
     */
    public bool $onlyReleased;

    /**
     * 未搜到/未匹配视频的冷却天数（null 为智能阶梯动态冷静期）
     */
    public ?int $cooldownDays;

    /**
     * 排序方式（desc 为最新片源优先，asc 为历史老片优先）
     */
    public string $order;

    /**
     * 任务描述（Horizon 展示）
     */
    public string $task_name = 'xxxclub 磁力资源增量爬取与补充';

    /**
     * 派发时间
     */
    public string $dispatched_at;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $limit = 50,
        ?string $channel = null,
        int $requestDelay = 2,
        ?string $proxy = null,
        ?string $flaresolverrUrl = null,
        bool $onlyReleased = true,
        ?int $cooldownDays = null,
        string $order = 'desc'
    ) {
        $this->limit = $limit;
        $this->channel = $channel;
        $this->requestDelay = $requestDelay;
        $this->proxy = $proxy;
        $this->flaresolverrUrl = $flaresolverrUrl;
        $this->onlyReleased = $onlyReleased;
        $this->cooldownDays = $cooldownDays;
        $this->order = $order;
        $this->dispatched_at = now()->toDateTimeString();
    }

    /**
     * Horizon 面板专属标签
     */
    public function tags(): array
    {
        return array_filter([
            'crawler',
            'xxxclub',
            'magnet-sync',
            $this->channel ?: 'all-channels',
            "limit:{$this->limit}",
            $this->onlyReleased ? 'only-released' : null,
            $this->cooldownDays !== null ? "cooldown:{$this->cooldownDays}d" : 'cooldown:dynamic',
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(\App\Services\Crawler\XxxclubCrawlerService $crawlerService): void
    {
        // 1. 目标站全局限流冷却保护期前置检测
        $cooldownRemaining = $crawlerService->getRateLimitCooldownRemaining();
        if ($cooldownRemaining > 0) {
            $formattedCooldown = $crawlerService->formatTtl($cooldownRemaining);
            Log::warning("⏳ [CrawlXxxclubMagnetsJob] 目标网站 (xxxclub) 当前正处于全局限流冷却保护期（剩余 {$formattedCooldown}），暂缓本次队列抓取，避免加剧封锁。");
            return;
        }

        $cooldownDesc = $this->cooldownDays !== null ? "{$this->cooldownDays}天" : "智能阶梯动态";
        Log::info("🚀 Horizon 队列开始执行 xxxclub 磁力爬取任务 [CrawlXxxclubMagnetsJob]"
            . ($this->channel ? " [片商: {$this->channel}]" : " [全部片商]")
            . " [上限: {$this->limit} 部, 延迟: {$this->requestDelay}s, 冷却: {$cooldownDesc}, 仅已上映: " . ($this->onlyReleased ? '是' : '否') . "]");

        $params = [
            '--limit' => $this->limit,
            '--delay' => $this->requestDelay,
            '--order' => $this->order,
        ];

        if ($this->cooldownDays !== null) {
            $params['--cooldown-days'] = $this->cooldownDays;
        }

        if ($this->onlyReleased) {
            $params['--only-released'] = true;
        }

        if ($this->channel) {
            $params['--channel'] = $this->channel;
        }

        if ($this->proxy) {
            $params['--proxy'] = $this->proxy;
        }

        if ($this->flaresolverrUrl) {
            $params['--flaresolverr'] = $this->flaresolverrUrl;
        }

        try {
            $exitCode = Artisan::call('video:crawl-xxxclub-magnets', $params);
            $output = trim(Artisan::output());
            if (!empty($output)) {
                Log::info("xxxclub 磁力爬取任务完成 [退出码: {$exitCode}]:\n" . $output);
            }
        } catch (\Throwable $e) {
            Log::warning("⚠️ [CrawlXxxclubMagnetsJob] 抓取命令执行过程发生异常: " . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
