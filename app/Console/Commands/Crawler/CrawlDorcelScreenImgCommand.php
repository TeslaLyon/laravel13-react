<?php

namespace App\Console\Commands\Crawler;

use App\Jobs\CrawlDorcelScreenImgJob;
use App\Models\Video;
use App\Services\Crawler\DorcelScreenImgCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
                            {--all : 全量处理所有符合条件的视频 (等同于 --limit=0)}
                            {--limit=50 : 批量处理视频数量上限 (默认 50，0 为不限)}
                            {--delay=1 : 每次请求间隔延迟秒数 (默认 1 秒，防被第三方风控)}
                            {--proxy= : 指定 HTTP/SOCKS5 代理 (例如 http://127.0.0.1:7890)}
                            {--force : 强制覆盖已有剧照截图数据的视频}
                            {--cooldown-days=7 : 搜索未匹配视频的冷却天数 (默认 7 天内跳过不重复请求，0 为不进入冷却)}
                            {--ignore-cooldown : 忽略冷却期，强制扫描所有目标视频}
                            {--clear-cooldown : 一键清空所有已记录的剧照冷却期标记}
                            {--clear-cooldown-id= : 清除指定视频 ID 的剧照冷静期标记 (例如 --clear-cooldown-id=16715)}
                            {--count-cooldown : 快速查看当前正处于剧照冷静期中的视频总量}
                            {--export-unmatched= : 将未匹配到剧照的视频清单导出至指定文本文件 (例如 unmatched.txt)}
                            {--order-by=release_at : 排序规则：release_at (最新发布日期优先) 或 id (按数据库自增ID降序)}
                            {--no-telegram : 禁用 Telegram 通知推送 (默认当 .env 配置了 Token 时自动推送)}
                            {--queue : 异步投递至 Horizon 队列后台执行}
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

        // 快捷选项 1: 查看当前冷静期总量
        if ((bool) $this->option('count-cooldown')) {
            $count = $service->getCoolingDownCount();
            $this->info("❄️  当前处于剧照冷静期中的视频总数: <fg=cyan;options=bold>{$count}</> 部");
            return self::SUCCESS;
        }

        // 快捷选项 2: 清除某部视频的冷静期
        $clearCooldownId = $this->option('clear-cooldown-id') ? (int) $this->option('clear-cooldown-id') : null;
        if ($clearCooldownId) {
            $service->clearCoolingDown($clearCooldownId);
            $this->info("🧹 已成功清除视频 ID: [{$clearCooldownId}] 的剧照冷静期标记！该视频已恢复可抓取状态。");
            return self::SUCCESS;
        }

        // 快捷选项 3: 一键清空所有冷静期
        if ((bool) $this->option('clear-cooldown')) {
            $cleared = $service->clearAllCoolingDown();
            $this->info("🧹 已清空所有现存的剧照冷静期标记" . ($cleared > 0 ? " (共清除 {$cleared} 条记录)" : "") . "，所有视频已恢复可抓取状态！");
            return self::SUCCESS;
        }

        $videoId = $this->option('video-id') ? (int) $this->option('video-id') : null;
        $isAll = (bool) $this->option('all');
        $limit = $isAll ? 0 : max(0, (int) $this->option('limit'));
        $delay = max(0, (int) $this->option('delay'));
        $proxy = $this->option('proxy');
        $force = (bool) $this->option('force');
        $cooldownDays = max(0, (int) $this->option('cooldown-days'));
        $ignoreCooldown = (bool) $this->option('ignore-cooldown');
        $exportUnmatched = $this->option('export-unmatched');
        $orderBy = in_array($this->option('order-by'), ['id', 'release_at'], true) ? (string) $this->option('order-by') : 'release_at';
        $noTelegram = (bool) $this->option('no-telegram');
        $isDryRun = (bool) $this->option('dry-run');
        $isDebug = (bool) $this->option('debug') || ($videoId !== null);

        // 异步队列投递模式
        if ($this->option('queue')) {
            $job = new CrawlDorcelScreenImgJob(
                videoId: $videoId,
                limit: $limit,
                requestDelay: $delay,
                proxy: $proxy,
                force: $force,
                dryRun: $isDryRun,
                all: $isAll,
                cooldownDays: $cooldownDays,
                ignoreCooldown: $ignoreCooldown,
                noTelegram: $noTelegram,
                orderBy: $orderBy
            );
            dispatch($job);

            $this->info("================================================================================");
            $this->info("🚀 已成功将 DorcelClub 剧照抓取任务投递至 Horizon 队列后台执行！");
            $this->line("   - 目标范围: " . ($videoId ? "指定视频 ID: {$videoId}" : ($isAll || $limit === 0 ? "全量所有视频" : "批量前 {$limit} 部视频")));
            $this->line("   - 排序规则: " . ($orderBy === 'release_at' ? "最新发布优先 (release_at desc)" : "数据库自增 ID 降序 (id desc)"));
            $this->line("   - 覆盖设置: " . ($force ? "强制覆盖已有截图" : "仅补充无截图视频"));
            $this->line("   - 冷静期: " . ($ignoreCooldown ? "忽略冷静期" : "未匹配进入 {$cooldownDays} 天冷却"));
            $this->line("   - 运行模式: " . ($isDryRun ? "演练模式 (Dry-Run)" : "正式入库"));
            $this->info("================================================================================");
            return self::SUCCESS;
        }

        $this->info("================================================================================");
        $this->info("📸 DorcelClub 视频片段剧照 (Screen Images) 第三方多源智能抓取与补充工具");
        $this->info("   集成源: Ultra4kPorn (WordPress 架构 CDN 高清相册) + Perfect-Stockings (备选兜底)");
        $this->info("================================================================================");

        if ($isDryRun) {
            $this->warn("⚠️  当前处于【--dry-run 演练模式】，仅执行检索、匹配与预览，不写入数据库！");
        }

        $this->info("🔍 正在筛选目标视频（" . ($force ? "全部视频包含已存在截图" : "仅无截图或缺失截图的视频") . "）...");

        try {
            $query = $service->getTargetVideosQuery($videoId, $force, DorcelScreenImgCrawlerService::CHANNEL_SLUG, $orderBy);
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
            'cooldown_skipped' => 0,
            'success_videos'   => 0,
            'u4p_solved'       => 0,
            'ps_solved'        => 0,
            'empty_videos'     => 0,
            'total_images'     => 0,
        ];

        $unmatchedVideos = [];

        $planCount = $limit > 0 ? min($limit, $totalAvailable) : $totalAvailable;
        $progressBar = $this->output->createProgressBar($planCount);
        $progressBar->start();

        foreach ($query->cursor() as $video) {
            $videoCode = $video->video_code ?: ($video->name ?: "ID:{$video->id}");

            // 冷静期检测：未指定单个视频且未忽略冷却时，在冷却期内的跳过
            $shouldCheckCooldown = !$ignoreCooldown && !$videoId;
            if ($shouldCheckCooldown && $service->isCoolingDown($video->id)) {
                $stats['cooldown_skipped']++;
                continue;
            }

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

                // 成功抓取，清除可能存在的历史冷静期标记
                $service->clearCoolingDown($video->id);

                // 入库保存
                $service->saveScreenshots($video, $result['images'], $isDryRun);

                $sourceBadge = $result['source'] === 'ultra4kporn' ? '[Ultra4kPorn]' : '[Perfect-Stockings]';
                Log::info("🎯 [DorcelClub 剧照抓取成功] 视频 [ID:{$video->id}] {$videoCode} 来源: {$sourceBadge}，获得 {$imgCount} 张切片图" . ($isDryRun ? " (演练)" : ""));

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

                // 未搜到相册，标记进入冷静期
                if ($cooldownDays > 0 && !$videoId) {
                    $service->markCoolingDown($video->id, $cooldownDays);
                }

                $unmatchedVideos[] = [
                    'id'         => $video->id,
                    'slug'       => (string) $video->slug,
                    'name'       => (string) $video->name,
                    'video_code' => (string) $videoCode,
                    'reason'     => $result['message'] ?? '无匹配',
                ];

                $cooldownLog = ($cooldownDays > 0 && !$videoId) ? " [进入 {$cooldownDays} 天冷却]" : "";
                Log::info("⚠️ [DorcelClub 剧照未匹配] 视频 [ID:{$video->id}] {$videoCode} ({$result['message']}){$cooldownLog}");

                if ($isDebug) {
                    $this->newLine();
                    $this->line("⚠️  <fg=yellow>未匹配到第三方剧照</>: {$videoCode} (" . ($result['message'] ?? '无结果') . ")" . ($cooldownDays > 0 && !$videoId ? " <fg=blue>[进入 {$cooldownDays} 天冷却]</>" : ""));
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
        $formattedDuration = $this->formatDuration($elapsedSeconds);

        $this->info("📋 执行结果汇总统计：");
        $this->table(
            ['指标项', '统计数值'],
            [
                ['目标视频总数', $stats['total_videos']],
                ['❄️ 冷却期跳过 (Cool-down)', $stats['cooldown_skipped']],
                ['实际处理视频', $stats['processed_videos']],
                ['成功抓取视频', $stats['success_videos']],
                ['  ├─ Ultra4kPorn 命中', $stats['u4p_solved']],
                ['  └─ Perfect-Stockings 兜底命中', $stats['ps_solved']],
                ['未搜到剧照相册', $stats['empty_videos']],
                ['抓取剧照图片总数', $stats['total_images']],
                ['⏱️ 运行总耗时', "{$formattedDuration} ({$elapsedSeconds}s)"],
            ]
        );

        // 打印未搜到资源的视频 ID 与 Slug 列表
        if (!empty($unmatchedVideos)) {
            $this->newLine();
            $this->warn("================================================================================");
            $this->warn("📋 未搜到剧照相册的视频列表（共 " . count($unmatchedVideos) . " 部）：");
            $this->warn("================================================================================");
            foreach ($unmatchedVideos as $idx => $u) {
                $this->line(sprintf("  %2d. <fg=yellow>[ID: %d]</> <fg=cyan>%s</> <fg=gray>(%s)</>", $idx + 1, $u['id'], $u['slug'], $u['name']));
            }
            $this->newLine();

            if (!empty($exportUnmatched)) {
                $exportLines = [];
                foreach ($unmatchedVideos as $u) {
                    $exportLines[] = "{$u['id']}\t{$u['slug']}\t{$u['name']}";
                }
                file_put_contents($exportUnmatched, implode(PHP_EOL, $exportLines) . PHP_EOL);
                $this->info("💾 未搜到剧照的视频清单已导出保存至: {$exportUnmatched}");
            }
        }

        // 发送 Telegram 汇总通知
        if (!$noTelegram) {
            $hasToken = !empty(config('services.telegram.bot_token')) && !empty(config('services.telegram.chat_id'));
            if ($hasToken) {
                $this->info("📲 正在向 Telegram 发送任务执行报告与未搜到清单...");
                $stats['duration'] = "{$formattedDuration} ({$elapsedSeconds}s)";
                $sent = $service->sendTelegramSummary($stats, $unmatchedVideos);
                if ($sent) {
                    $this->info("✅ Telegram 汇总报告推送成功！");
                } else {
                    $this->warn("⚠️ Telegram 消息发送失败，请检查 Bot Token、Chat ID 与网络配置。");
                }
            } else {
                $this->line("💡 提示：在 <fg=yellow>.env</> 中配置 <fg=cyan>TELEGRAM_BOT_TOKEN</> 与 <fg=cyan>TELEGRAM_CHAT_ID</> 即可自动接收 Telegram 汇报。");
            }
        }

        return self::SUCCESS;
    }

    /**
     * 将秒数格式化为人类易读的时间字符串
     */
    protected function formatDuration(float $seconds): string
    {
        if ($seconds < 60) {
            return round($seconds, 2) . ' 秒';
        }

        $minutes = (int) floor($seconds / 60);
        $remSeconds = round($seconds - ($minutes * 60), 1);

        if ($minutes < 60) {
            return "{$minutes} 分 {$remSeconds} 秒";
        }

        $hours = (int) floor($minutes / 60);
        $remMinutes = $minutes % 60;

        return "{$hours} 小时 {$remMinutes} 分 {$remSeconds} 秒";
    }
}
