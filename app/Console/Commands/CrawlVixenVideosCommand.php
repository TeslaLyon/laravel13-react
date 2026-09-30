<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Crawler\VixenCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlVixenVideosCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:vixen-videos
                            {channel? : 片商 slug（可选，未指定时自动遍历爬取所有 data_crawl_type=2 的片商）}
                            {--exclude= : 排除的片商 slug，支持逗号分隔，例如 --exclude=vixen}
                            {--page= : 指定抓取的分页页码（不传则默认从第 1 页自动循环爬取至最后一页）}
                            {--all : 配合 --page 使用，从指定页开始向后自动循环抓取至最后一页}
                            {--no-images : 跳过图片下载与 R2 上传，仅同步视频元数据（极速同步推荐）}
                            {--force-images : 强制重新抓取并更新图片，忽略现有已入库图片缓存}
                            {--delay=2 : 批量循环时每页间隔休眠秒数}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '针对 Vixen 系列片商（data_crawl_type=2）的视频列表与详情爬虫任务';

    /**
     * 执行控制台命令
     */
    public function handle(VixenCrawlerService $crawlerService): int
    {
        $channelSlug = $this->argument('channel');
        $pageOption = $this->option('page');

        if ($pageOption === null || $pageOption === '') {
            $startPage = 1;
            $all = true;
        } else {
            $startPage = max(1, (int) $pageOption);
            $all = (bool) $this->option('all');
        }

        $downloadImages = !$this->option('no-images');
        $forceImages = (bool) $this->option('force-images');
        $useQueue = (bool) $this->option('queue');
        $delay = max(0, (int) ($this->option('delay') ?: 2));

        // 关键防爆核心：关闭数据库查询日志，防止大规模写入撑爆内存
        DB::disableQueryLog();

        $excludeOption = $this->option('exclude');
        $excludeSlugs = $excludeOption ? array_filter(array_map('trim', explode(',', $excludeOption))) : [];

        // 确定需要处理的片商列表
        if (!empty($channelSlug)) {
            $channels = Channel::where('slug', $channelSlug)
                ->where('data_crawl_type', 2)
                ->get();
            if ($channels->isEmpty()) {
                $this->error("❌ 未找到片商 [{$channelSlug}] 或其 data_crawl_type 不为 2");
                return self::FAILURE;
            }
        } else {
            $channelsQuery = Channel::where('data_crawl_type', 2);
            if (!empty($excludeSlugs)) {
                $channelsQuery->whereNotIn('slug', $excludeSlugs);
            }
            $channels = $channelsQuery->get();
            if ($channels->isEmpty()) {
                $this->error("❌ 数据库中未找到任何符合条件的 data_crawl_type 为 2 的片商！");
                return self::FAILURE;
            }
        }

        if ($useQueue) {
            $this->info("🚀 正在将任务投递至 Horizon 队列...");
            foreach ($channels as $channel) {
                \App\Jobs\CrawlVixenVideosJob::dispatch(
                    $channel->slug,
                    $startPage,
                    $downloadImages,
                    $forceImages,
                    $all
                );
                $this->info("✅ 已派发片商 [{$channel->slug}] 抓取任务至 Horizon 队列 (起始页: 第 {$startPage} 页" . ($all ? "，自动链式向后抓取" : "") . ")");
            }
            $this->newLine();
            $this->info("🎉 队列投递完成！Horizon 容器将在后台独立处理，无惧服务器部署或终端断开。");
            $this->line("👉 可随时在 Horizon 面板监控实时进度: /horizon");
            return self::SUCCESS;
        }

        $totalChannels = $channels->count();
        $modeText = $all ? "自动循环爬取至最后一页" : "仅抓取第 {$startPage} 页";

        $this->info("🚀 启动 Vixen 系列视频爬虫"
            . ($channelSlug ? " [指定片商: {$channelSlug}]" : " [全部片商: 共 {$totalChannels} 个]")
            . " [起始页: 第 {$startPage} 页]"
            . " [模式: {$modeText}]"
            . ($downloadImages ? " [图片优化: 启用(R2/本地)]" : " [图片优化: 跳过]")
        );

        $globalSuccess = 0;
        $globalFailed = 0;
        $globalSkipped = 0;

        foreach ($channels as $index => $channel) {
            $channelIndex = $index + 1;
            $this->newLine();
            $this->info("==================================================");
            $this->info("🎬 [{$channelIndex}/{$totalChannels}] 正在处理片商: {$channel->name} ({$channel->slug})");
            $this->info("==================================================");

            $currentPage = $startPage;

            do {
                $this->line("⏳ 正在抓取片商 [{$channel->slug}] 第 {$currentPage} 页视频数据...");
                $result = $crawlerService->crawlSingleChannelVideos($channel, $currentPage, $downloadImages, $forceImages);

                if (!$result['success']) {
                    $this->error("⚠️ 片商 [{$channel->slug}] 第 {$currentPage} 页抓取失败: " . ($result['message'] ?? '未知错误'));
                    break;
                }

                $sCount = $result['success_count'] ?? 0;
                $fCount = $result['failed_count'] ?? 0;
                $skCount = $result['skipped_count'] ?? 0;

                $globalSuccess += $sCount;
                $globalFailed += $fCount;
                $globalSkipped += $skCount;

                if (isset($result['total'])) {
                    $this->line("📊 片商 [{$channel->slug}] 远端视频总量: {$result['total']} 条 | 总页数: {$result['total_pages']} 页 | 当前页: 第 {$currentPage} 页");
                }
                $this->table(
                    ['片商', '当前页', '成功入库', '失败', '跳过/重复'],
                    [[
                        $channel->slug,
                        $currentPage,
                        $sCount,
                        $fCount,
                        $skCount,
                    ]]
                );

                gc_collect_cycles();

                $hasNextPage = $result['has_next_page'] ?? false;
                if ($all && $hasNextPage) {
                    $currentPage++;
                    if ($delay > 0) {
                        sleep($delay);
                    }
                } else {
                    break;
                }
            } while ($all && $hasNextPage);

            $this->info("✅ 片商 [{$channel->slug}] 视频处理完成！");
        }

        $this->newLine();
        $this->info("🎉 全部视频任务执行完毕！共处理 {$totalChannels} 个片商。");
        $this->table(
            ['总片商数', '总成功入库', '总失败', '总跳过/重复'],
            [[
                $totalChannels,
                $globalSuccess,
                $globalFailed,
                $globalSkipped,
            ]]
        );

        return self::SUCCESS;
    }
}
