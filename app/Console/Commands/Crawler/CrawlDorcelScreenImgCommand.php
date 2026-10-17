<?php

namespace App\Console\Commands\Crawler;

use App\Models\Video;
use App\Services\Crawler\DorcelScreenImgCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrawlDorcelScreenImgCommand extends Command
{
    /**
     * 控制台命令签名 (同时保留别名 dorcel:screen-img-u4p)
     *
     * @var string
     */
    protected $signature = 'crawler:dorcel-screen-img
                            {--video-id= : 指定单个视频 ID 进行定向排查与补充}
                            {--limit=50 : 批量处理视频数量上限 (默认 50，0 为不限)}
                            {--delay=1 : 每次请求间隔延迟秒数 (默认 1 秒，防被第三方风控)}
                            {--proxy= : 指定 HTTP/SOCKS5 代理 (例如 http://127.0.0.1:7890)}
                            {--force : 强制覆盖已有剧照截图数据的视频}
                            {--dry-run : 演练模式：仅检索和匹配预览，不实际写入数据库}
                            {--debug : 调试排查模式：输出详细搜索词与抓取明细}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '从第三方权威相册源 (Ultra4kPorn / Perfect-Stockings) 自动检索并补充 DorcelClub 视频片段轮播剧照';

    /**
     * 执行控制台命令
     */
    public function handle(DorcelScreenImgCrawlerService $service): int
    {
        DB::disableQueryLog();
        $startTime = microtime(true);

        $videoId = $this->option('video-id') ? (int) $this->option('video-id') : null;
        $limit = max(0, (int) $this->option('limit'));
        $delay = max(0, (int) $this->option('delay'));
        $proxy = $this->option('proxy');
        $force = (bool) $this->option('force');
        $isDryRun = (bool) $this->option('dry-run');
        $isDebug = (bool) $this->option('debug') || ($videoId !== null);

        $this->info("================================================================================");
        $this->info("📸 DorcelClub 视频片段剧照 (Screen Images) 第三方多源智能抓取与补充工具");
        $this->info("   集成源: Ultra4kPorn (WordPress 架构 CDN 高清相册) + Perfect-Stockings (备选兜底)");
        $this->info("================================================================================");

        if ($isDryRun) {
            $this->warn("⚠️  当前处于【--dry-run 演练模式】，仅执行检索、匹配与预览，不写入数据库！");
        }

        $this->info("🔍 正在筛选目标视频（" . ($force ? "全部视频包含已存在截图" : "仅无截图或缺失截图的视频") . "）...");

        try {
            $query = $service->getTargetVideosQuery($videoId, $force);
            $totalAvailable = $query->count();
        } catch (Throwable $e) {
            $this->error("❌ 查询数据库失败: " . $e->getMessage());
            return self::FAILURE;
        }

        if ($totalAvailable === 0) {
            $this->info("🎉 检查完成：当前没有需要补充截图剧照的 DorcelClub 视频！");
            return self::SUCCESS;
        }

        $this->info("📊 符合条件的目标视频总量: {$totalAvailable} 部" . ($limit > 0 ? "，本次计划处理前 {$limit} 部" : "，本次全量处理"));

        $stats = [
            'total_videos'     => $totalAvailable,
            'processed_videos' => 0,
            'success_videos'   => 0,
            'u4p_solved'       => 0,
            'ps_solved'        => 0,
            'empty_videos'     => 0,
            'total_images'     => 0,
        ];

        $planCount = $limit > 0 ? min($limit, $totalAvailable) : $totalAvailable;
        $progressBar = $this->output->createProgressBar($planCount);
        $progressBar->start();

        foreach ($query->cursor() as $video) {
            $videoCode = $video->video_code ?: ($video->name ?: "ID:{$video->id}");
            $stats['processed_videos']++;

            if ($isDebug) {
                $this->newLine();
                $this->info("--------------------------------------------------------------------------------");
                $this->info("🎯 [处理目标] 视频 ID: {$video->id} | 片名: {$video->name}");
                if (!empty($video->video_code)) {
                    $this->line("   - 番号 (Code): {$video->video_code}");
                }
                $actorNames = $video->actors->pluck('name')->implode(', ');
                $this->line("   - 关联演员: " . ($actorNames ?: '未知'));
                $this->line("   - 发布日期: " . ($video->release_at ?: '未知'));
            }

            // 执行检索与抓取
            $result = $service->crawlScreenshotsForVideo($video, $proxy);

            if ($result['success'] && !empty($result['images'])) {
                $stats['success_videos']++;
                $imgCount = count($result['images']);
                $stats['total_images'] += $imgCount;

                if ($result['source'] === 'ultra4kporn') {
                    $stats['u4p_solved']++;
                } else {
                    $stats['ps_solved']++;
                }

                // 入库保存
                $saveResult = $service->saveScreenshots($video, $result['images'], $isDryRun);

                $sourceBadge = $result['source'] === 'ultra4kporn' ? '[Ultra4kPorn]' : '[Perfect-Stockings]';
                $this->newLine();
                $this->line("🎯 <fg=green>✔ 抓取成功</> <fg=magenta>{$sourceBadge}</>: <fg=cyan>{$videoCode}</> ➔ 获得 <info>{$imgCount}</info> 张高清切片图" . ($isDryRun ? " <fg=yellow>[演练不入库]</>" : " <fg=green>[已持久化入库]</>"));

                if ($isDebug) {
                    $this->line("   - 相册标题: " . ($result['article_name'] ?? '-'));
                    $this->line("   - 来源地址: <fg=gray>" . ($result['article_url'] ?? '-') . "</>");
                    $this->line("   - 首图缩略: <fg=gray>" . ($result['images'][0]['url'] ?? '') . "</>");
                    $this->line("   - 首图高清: <fg=gray>" . ($result['images'][0]['full_url'] ?? '') . "</>");
                }
            } else {
                $stats['empty_videos']++;
                if ($isDebug) {
                    $this->newLine();
                    $this->line("⚠️  <fg=yellow>未匹配到第三方剧照</>: {$videoCode} (" . ($result['message'] ?? '无结果') . ")");
                }
            }

            if ($delay > 0) {
                sleep($delay);
            }

            $progressBar->advance();

            if ($limit > 0 && $stats['processed_videos'] >= $limit) {
                break;
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $elapsedSeconds = round(microtime(true) - $startTime, 2);

        $this->info("📋 执行结果汇总统计：");
        $this->table(
            ['指标项', '统计数值'],
            [
                ['目标视频总数', $stats['total_videos']],
                ['实际处理视频', $stats['processed_videos']],
                ['成功抓取视频', $stats['success_videos']],
                ['  ├─ Ultra4kPorn 命中', $stats['u4p_solved']],
                ['  └─ Perfect-Stockings 兜底命中', $stats['ps_solved']],
                ['未搜到剧照相册', $stats['empty_videos']],
                ['抓取剧照图片总数', $stats['total_images']],
                ['⏱️ 运行总耗时', "{$elapsedSeconds} 秒"],
            ]
        );

        return self::SUCCESS;
    }
}

