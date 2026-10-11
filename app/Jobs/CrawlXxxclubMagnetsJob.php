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
        ?string $flaresolverrUrl = null
    ) {
        $this->limit = $limit;
        $this->channel = $channel;
        $this->requestDelay = $requestDelay;
        $this->proxy = $proxy;
        $this->flaresolverrUrl = $flaresolverrUrl;
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
        ]);
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("🚀 Horizon 队列开始执行 xxxclub 磁力爬取任务 [CrawlXxxclubMagnetsJob]"
            . ($this->channel ? " [片商: {$this->channel}]" : " [全部片商]")
            . " [上限: {$this->limit} 部, 延迟: {$this->requestDelay}s]");

        $params = [
            '--limit' => $this->limit,
            '--delay' => $this->requestDelay,
        ];

        if ($this->channel) {
            $params['--channel'] = $this->channel;
        }

        if ($this->proxy) {
            $params['--proxy'] = $this->proxy;
        }

        if ($this->flaresolverrUrl) {
            $params['--flaresolverr'] = $this->flaresolverrUrl;
        }

        Artisan::call('video:crawl-xxxclub-magnets', $params);

        $output = trim(Artisan::output());
        if (!empty($output)) {
            Log::info("xxxclub 磁力爬取任务输出:\n" . $output);
        }
    }
}
