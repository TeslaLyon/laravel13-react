<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Crawler\VixenCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CrawlVixenDailyCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:vixen-daily
                            {channel? : 片商 slug（可选，未指定时遍历所有 data_crawl_type=2 的片商）}
                            {--no-images : 跳过图片下载优化，仅同步元数据（极速模式）}
                            {--delay=1 : 各操作及分页间的休眠秒数}
                            {--force-images : 强制重新下载并覆盖已有演员的图片}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = 'Vixen 系列片商每日增量爬虫：按片商依次爬取全量演员与第 1 页最新视频';

    /**
     * 执行控制台命令
     */
    public function handle(VixenCrawlerService $crawlerService): int
    {
        $channelSlug = $this->argument('channel');
        $downloadImages = !$this->option('no-images');
        $delay = max(0, (int) ($this->option('delay') ?: 1));
        $forceImages = (bool) $this->option('force-images');

        // 关闭查询日志，保障长效循环内存安全
        DB::disableQueryLog();

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
            $channels = Channel::where('data_crawl_type', 2)->get();
            if ($channels->isEmpty()) {
                $this->error("❌ 数据库中未找到任何 data_crawl_type 为 2 的片商！");
                return self::FAILURE;
            }
        }

        $totalChannels = $channels->count();
        $this->info("🚀 启动 Vixen 系列片商每日协同爬取任务"
            . ($channelSlug ? " [指定片商: {$channelSlug}]" : " [全部片商: 共 {$totalChannels} 个]")
            . ($downloadImages ? " [图片优化: 启用(R2/本地)]" : " [图片优化: 跳过]")
        );

        $summary = [];

        foreach ($channels as $index => $channel) {
            $channelIndex = $index + 1;
            $this->newLine();
            $this->info("==================================================");
            $this->info("🎬 [{$channelIndex}/{$totalChannels}] 正在处理片商: {$channel->name} ({$channel->slug})");
            $this->info("==================================================");

            // ==========================================
            // 阶段 1：抓取该片商的所有演员数据（循环翻页至最后一页）
            // ==========================================
            $this->line("👤 [阶段 1/2] 开始抓取片商 [{$channel->slug}] 的全量演员数据...");
            $actorPage = 1;
            $actorStats = ['success' => 0, 'failed' => 0, 'skipped' => 0, 'pages' => 0];

            do {
                $this->line("   ⏳ 正在抓取演员第 {$actorPage} 页...");
                $actorResult = $crawlerService->crawlSingleChannelActors($channel, $actorPage, $downloadImages, $forceImages);

                if (!$actorResult['success']) {
                    $this->error("   ⚠️ 演员第 {$actorPage} 页抓取异常: " . ($actorResult['message'] ?? '未知错误'));
                    break;
                }

                $actorStats['success'] += $actorResult['success_count'] ?? 0;
                $actorStats['failed']  += $actorResult['failed_count'] ?? 0;
                $actorStats['skipped'] += $actorResult['skipped_count'] ?? 0;
                $actorStats['pages']   = $actorPage;

                gc_collect_cycles();

                $hasNextActorPage = $actorResult['has_next_page'] ?? false;
                if ($hasNextActorPage) {
                    $actorPage++;
                    if ($delay > 0) {
                        sleep($delay);
                    }
                } else {
                    break;
                }
            } while ($hasNextActorPage);

            $this->info("✅ [阶段 1/2] 片商 [{$channel->slug}] 演员抓取完毕！共抓取 {$actorStats['pages']} 页，新增 {$actorStats['success']} 人，跳过/已有 {$actorStats['skipped']} 人，失败 {$actorStats['failed']} 人。");

            if ($delay > 0) {
                sleep($delay);
            }

            // ==========================================
            // 阶段 2：抓取该片商视频列表第 1 页数据（增量最新视频）
            // ==========================================
            $this->line("📹 [阶段 2/2] 开始抓取片商 [{$channel->slug}] 的视频第 1 页最新数据...");
            $videoResult = $crawlerService->crawlSingleChannelVideos($channel, 1, $downloadImages);
            $videoStats = ['success' => 0, 'failed' => 0, 'skipped' => 0];

            if (!$videoResult['success']) {
                $this->error("   ⚠️ 视频第 1 页抓取异常: " . ($videoResult['message'] ?? '未知错误'));
            } else {
                $videoStats['success'] = $videoResult['success_count'] ?? 0;
                $videoStats['failed']  = $videoResult['failed_count'] ?? 0;
                $videoStats['skipped'] = $videoResult['skipped_count'] ?? 0;
                $this->info("✅ [阶段 2/2] 片商 [{$channel->slug}] 视频第 1 页抓取完毕！新增入库 {$videoStats['success']} 条，跳过/已有 {$videoStats['skipped']} 条，失败 {$videoStats['failed']} 条。");
            }

            $summary[] = [
                'channel'         => $channel->slug,
                'actor_pages'     => $actorStats['pages'],
                'actor_new'       => $actorStats['success'],
                'actor_skipped'   => $actorStats['skipped'],
                'video_page_1_new'=> $videoStats['success'],
                'video_skipped'   => $videoStats['skipped'],
            ];

            gc_collect_cycles();

            if ($delay > 0) {
                sleep($delay);
            }
        }

        $this->newLine();
        $this->info("🎉 每日片商协同抓取任务全部完成！");
        $this->table(
            ['片商', '演员处理页数', '演员新增', '演员跳过', '视频P1新增', '视频跳过'],
            array_map(fn($item) => [
                $item['channel'],
                $item['actor_pages'],
                $item['actor_new'],
                $item['actor_skipped'],
                $item['video_page_1_new'],
                $item['video_skipped'],
            ], $summary)
        );

        return self::SUCCESS;
    }
}
