<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class CrawlStasyQVideosJob implements ShouldQueue
{
    use Queueable;

    /**
     * 任务最大超时时间（秒）
     */
    public int $timeout = 1800;

    /**
     * 最大失败重试次数
     */
    public int $tries = 3;

    /**
     * 指定抓取的分页页码（日常增量默认第 1 页）
     */
    public int $page;

    /**
     * 是否开启全量向后分页抓取
     */
    public bool $all;

    /**
     * 是否跳过已存在且详情完整的视频
     */
    public bool $skipExisting;

    /**
     * 抓取单部详情页间隔秒数
     */
    public int $crawlDelay;

    /**
     * 处理上限条数
     */
    public ?int $limit;

    /**
     * 任务类型描述
     */
    public string $task_name = 'StasyQ 视频列表与详情抓取';

    /**
     * 派发时间
     */
    public string $dispatched_at;

    public function __construct(
        int $page = 1,
        bool $all = false,
        bool $skipExisting = true,
        int $crawlDelay = 1,
        ?int $limit = null
    ) {
        $this->page = max(1, $page);
        $this->all = $all;
        $this->skipExisting = $skipExisting;
        $this->crawlDelay = max(0, $crawlDelay);
        $this->limit = $limit;
        $this->dispatched_at = now()->toDateTimeString();
    }

    /**
     * Horizon 面板专属标签
     */
    public function tags(): array
    {
        return [
            'crawler',
            'stasyq',
            $this->all ? 'mode:full' : "page:{$this->page}",
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $modeDesc = $this->all ? '全量分页抓取' : "第 {$this->page} 页日常增量抓取";
        Log::info("Horizon 队列开始执行爬虫任务 [CrawlStasyQVideosJob] [{$modeDesc}]");

        $params = [
            '--page'  => $this->all ? null : $this->page,
            '--delay' => $this->crawlDelay,
        ];

        if ($this->all) {
            unset($params['--page']);
            $params['--all'] = true;
        }
        if ($this->skipExisting) {
            $params['--skip-existing'] = true;
        }
        if ($this->limit !== null) {
            $params['--limit'] = $this->limit;
        }

        $exitCode = Artisan::call('crawler:stasyq-videos', $params);
        Log::info("Horizon 队列爬虫任务 [CrawlStasyQVideosJob] 执行完毕，退出码: {$exitCode}");
    }
}
