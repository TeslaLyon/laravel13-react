<?php

namespace App\Console\Commands;

use App\Services\Crawler\Project1VideoCrawlerService;
use Illuminate\Console\Command;

class CrawlProject1VideosCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:project1-videos
                            {channel? : 片商 slug（可选，未指定时自动爬取所有 data_crawl_type=1 的片商）}
                            {--limit=24 : 限制拉取视频条数}
                            {--token= : 显式指定 API Token（留空则优先从 Redis/Cache 获取或自动抓取）}
                            {--refresh-token : 强制从片商官网抓取最新 Token 并更新缓存}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '针对 Project1Service 站点的视频列表爬虫定时任务';

    /**
     * 执行控制台命令
     */
    public function handle(Project1VideoCrawlerService $crawlerService): int
    {
        $channel = $this->argument('channel');
        $limit = (int) ($this->option('limit') ?: 24);
        $token = $this->option('token');
        $refreshToken = (bool) $this->option('refresh-token');

        $this->info("🚀 开始执行 Project1 视频爬虫任务" . ($channel ? " [片商: {$channel}]" : " [全部片商]") . "...");

        $result = $crawlerService->crawlVideos($channel, $limit, $token, $refreshToken);

        if (!$result['success']) {
            $this->error("❌ 任务终止: " . ($result['message'] ?? '未知错误'));
            return self::FAILURE;
        }

        if ($channel) {
            $this->info("✅ 片商 [{$channel}] 爬取完成！");
            $this->table(
                ['总条数', '成功入库', '失败', '跳过'],
                [[
                    $result['total_items'] ?? 0,
                    $result['success_count'] ?? 0,
                    $result['failed_count'] ?? 0,
                    $result['skipped_count'] ?? 0,
                ]]
            );
        } else {
            $stats = $result['stats'] ?? [];
            $this->info("✅ 全部片商爬取任务处理完成！");
            $this->table(
                ['片商总数', '已处理片商', '成功入库', '失败', '跳过'],
                [[
                    $stats['total_channels'] ?? 0,
                    $stats['channels_processed'] ?? 0,
                    $stats['success_count'] ?? 0,
                    $stats['failed_count'] ?? 0,
                    $stats['skipped_count'] ?? 0,
                ]]
            );
        }

        return self::SUCCESS;
    }
}
