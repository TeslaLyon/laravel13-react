<?php

namespace App\Console\Commands;

use App\Jobs\CrawlProject1VideosJob;
use App\Models\Channel;
use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlProject1VideosCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:project1-videos
                            {channel? : 片商 slug（如 brazzers, bangbros；留空自动遍历支持的 Project1 片商）}
                            {--limit=24 : 每页拉取视频条数}
                            {--page= : 指定抓取的单页页码（单页执行）}
                            {--start-page=1 : 批量连续抓取时的起始页码（默认第 1 页）}
                            {--max-page= : 批量连续抓取时的最大截止页码}
                            {--all : 开启连续向后循环抓取至最大页}
                            {--delay=1 : 批量同步抓取时每页间隔秒数（默认 1 秒）}
                            {--queue : 异步投递至 Horizon 队列后台执行}
                            {--stagger=5 : 多页入队时的递增延迟间隔秒数（默认 5 秒），防止瞬时并发打崩源站}
                            {--token= : 显式指定 API Token（留空则优先从 Redis/Cache 获取或自动抓取）}
                            {--refresh-token : 强制从片商官网抓取最新 Token 并更新缓存}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '针对 Project1Service 站点（Brazzers、BangBros 等）的视频列表爬取及站点系列同步任务';

    /**
     * 执行控制台命令
     */
    public function handle(Project1VideoCrawlerService $crawlerService): int
    {
        DB::disableQueryLog();

        $channelArg = $this->argument('channel');
        $limit = max(1, (int) ($this->option('limit') ?: 24));
        $delay = max(0, (int) ($this->option('delay') ?? 1));
        $token = $this->option('token');
        $refreshToken = (bool) $this->option('refresh-token');

        // 确定待爬取的片商列表
        if ($channelArg) {
            $targetChannels = [$channelArg];
        } else {
            $targetChannels = Channel::where('data_crawl_type', 1)
                ->whereIn('slug', ['brazzers', 'bangbros'])
                ->pluck('slug')
                ->toArray();

            if (empty($targetChannels)) {
                $targetChannels = ['brazzers', 'bangbros'];
            }
        }

        // 解析页码策略
        $pageOption = $this->option('page');
        if ($pageOption !== null && $pageOption !== '') {
            $startPage = max(1, (int) $pageOption);
            $maxPage = $startPage;
            $crawlAll = false;
        } else {
            $startPage = max(1, (int) ($this->option('start-page') ?: 1));
            $maxPage = $this->option('max-page') ? max($startPage, (int) $this->option('max-page')) : null;
            $crawlAll = (bool) $this->option('all');
        }

        // 异步队列投递模式 (--queue)
        if ($this->option('queue')) {
            $stagger = max(0, (int) ($this->option('stagger') ?? 5));
            $totalDispatched = 0;

            $this->info("==================================================");
            $this->info("🚀 正在将 Project1 抓取任务批量投递至 Horizon 队列...");
            $this->line("🏢 目标片商: <info>" . implode(', ', $targetChannels) . "</info>");
            $this->line("⏱️ 入队递增延迟: <comment>{$stagger} 秒/页</comment> (防并发瞬时冲击)");
            $this->line("📦 每页条数: <comment>{$limit} 条</comment>");
            $this->info("==================================================");

            foreach ($targetChannels as $chSlug) {
                $chMaxPage = $maxPage;
                if ($crawlAll && $chMaxPage === null) {
                    $channelModel = Channel::where('slug', $chSlug)->first();
                    $resolvedToken = $token ?: ($channelModel ? $crawlerService->resolveChannelToken($channelModel, $refreshToken) : null);
                    if ($channelModel && $resolvedToken) {
                        // 拉取一次获取总页数元信息
                        $testRes = $crawlerService->crawlSingleChannel($channelModel, $limit, $resolvedToken, false, 1);
                        $chMaxPage = $testRes['total_pages'] ?? 1;
                        $this->line("📊 片商 [{$chSlug}] 远端检测总页数: <info>{$chMaxPage} 页</info> (总计 " . ($testRes['total'] ?? 0) . " 条)");
                    } else {
                        $chMaxPage = $startPage;
                    }
                } elseif (!$crawlAll) {
                    $chMaxPage = $startPage;
                }

                $targetPages = range($startPage, $chMaxPage);
                $this->info("📤 片商 [{$chSlug}]: 投递第 {$startPage} 页 至 第 {$chMaxPage} 页 (共 " . count($targetPages) . " 个任务)...");

                foreach ($targetPages as $index => $page) {
                    $delaySeconds = ($totalDispatched + $index) * $stagger;
                    $job = new CrawlProject1VideosJob(
                        channel: $chSlug,
                        limit: $limit,
                        page: $page
                    );

                    if ($delaySeconds > 0) {
                        $job->delay(now()->addSeconds($delaySeconds));
                    }

                    dispatch($job);
                }

                $totalDispatched += count($targetPages);
            }

            $this->newLine();
            $this->info("✅ 成功派发 {$totalDispatched} 个抓取任务至 Redis 队列！");
            $this->line("💡 您可以在 Horizon 控制面板实时查看任务消费进度与消费状态。");
            $this->info("==================================================");

            return self::SUCCESS;
        }

        // 同步执行模式
        $this->info("==================================================");
        $this->info("🚀 开始同步执行 Project1 视频爬虫任务");
        $this->line("🏢 目标片商: <info>" . implode(', ', $targetChannels) . "</info>");
        $this->line("📄 执行模式: <comment>" . ($crawlAll ? "连续循环爬取 (从第 {$startPage} 页开始)" : "单页抓取 (第 {$startPage} 页)") . "</comment>");
        $this->line("📦 每页拉取: <comment>{$limit} 条</comment>");
        $this->info("==================================================");

        foreach ($targetChannels as $chSlug) {
            $channelModel = Channel::where('slug', $chSlug)->where('data_crawl_type', 1)->first();
            if (!$channelModel) {
                $this->error("❌ 片商 [{$chSlug}] 未找到或 data_crawl_type 不为 1");
                continue;
            }

            $currentPage = $startPage;
            $chCreated = 0;
            $chFailed = 0;
            $chSkipped = 0;

            do {
                $this->newLine();
                $this->line("--------------------------------------------------");
                $this->info("🔍 正在抓取片商 [{$chSlug}] 第 {$currentPage} 页...");

                $result = $crawlerService->crawlSingleChannel($channelModel, $limit, $token, $refreshToken, $currentPage);

                if (!$result['success']) {
                    $this->error("❌ [第 {$currentPage} 页] " . ($result['message'] ?? '未知错误'));
                    if (!$crawlAll) {
                        break;
                    }
                    $currentPage++;
                    continue;
                }

                $chCreated += $result['success_count'] ?? 0;
                $chFailed  += $result['failed_count'] ?? 0;
                $chSkipped += $result['skipped_count'] ?? 0;

                $this->table(
                    ['本页条数', '成功入库', '失败', '跳过', '远端总量', '总页数'],
                    [[
                        $result['total_items'] ?? 0,
                        $result['success_count'] ?? 0,
                        $result['failed_count'] ?? 0,
                        $result['skipped_count'] ?? 0,
                        $result['total'] ?? '-',
                        $result['total_pages'] ?? '-',
                    ]]
                );

                if (!$crawlAll) {
                    break;
                }

                $remoteTotalPages = $result['total_pages'] ?? null;
                if ($maxPage !== null && $currentPage >= $maxPage) {
                    $this->line("🏁 已到达指定的最大页码限制: 第 {$maxPage} 页");
                    break;
                }

                if ($remoteTotalPages !== null && $currentPage >= $remoteTotalPages) {
                    $this->line("🏁 已到达远端最大页码: 第 {$remoteTotalPages} 页");
                    break;
                }

                if (($result['total_items'] ?? 0) === 0) {
                    $this->warn("⚠️ 本页未获取到任何视频数据，终止向后爬取。");
                    break;
                }

                $currentPage++;
                if ($delay > 0) {
                    sleep($delay);
                }
            } while (true);

            $this->newLine();
            $this->info("✨ 片商 [{$chSlug}] 爬取完成！成功: {$chCreated}, 失败: {$chFailed}, 跳过: {$chSkipped}");
        }

        $this->newLine();
        $this->info("==================================================");
        $this->info("🎉 全部指定任务处理完成！");
        $this->info("==================================================");

        return self::SUCCESS;
    }
}
