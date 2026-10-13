<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\Crawler\CiligouCrawlerService;
use App\Services\Crawler\XxxclubCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class CrawlXxxclubMagnetsCommand extends Command
{
    /**
     * 命令签名与可用选项
     */
    protected $signature = 'video:crawl-xxxclub-magnets
                            {--limit=50 : 每次任务处理的目标视频数量上限 (默认50条，0为不限)}
                            {--video-id= : 指定单个视频ID进行定向抓取排查}
                            {--channel= : 按片商筛选 (slug 或名称，如 brazzers)}
                            {--file= : 指定本地xxxclub HTML文件进行离线解析与匹配测试 (如 Untitled-1.html)}
                            {--ciligou-file= : 指定本地磁力狗HTML文件进行离线解析与匹配测试 (如 ciligou.html)}
                            {--proxy= : 指定HTTP/SOCKS5代理 (例如 http://127.0.0.1:7890)}
                            {--flaresolverr= : 指定 FlareSolverr 服务的 URL 地址 (默认自动读取配置或 http://flaresolverr:8191/v1)}
                            {--no-flaresolverr : 禁用 FlareSolverr，仅使用常规 HTTP 直连}
                            {--delay=2 : 每次网络请求间隔延迟秒数 (默认2秒，避免频繁请求被风控)}
                            {--include-unreleased : 包含尚未到排期日期的未来预售视频 (默认排除未来片，避免无效请求)}
                            {--only-released : 仅筛选已到发布日期的视频 (默认即为开启状态)}
                            {--cooldown-days=7 : 搜索未匹配视频的冷却天数 (默认7天内跳过不重复请求，0为不进入冷却)}
                            {--ignore-cooldown : 忽略冷却期，强制扫描所有候选视频}
                            {--clear-cooldown : 一键清空所有已记录的冷却期标记，使其立即重新具备候选资格}
                            {--clear-cooldown-id= : 清除指定视频ID的冷静期标记 (例如 --clear-cooldown-id=102)}
                            {--list-cooldown : 查看当前所有正处于冷静期中的视频列表及剩余冷静时间}
                            {--order=desc : 排序方式：desc（最新片源优先）或 asc（最早历史片优先）}
                            {--export-unmatched= : 将未匹配的视频 Code 导出至指定文件 (例如 unmatched_videos.txt)}
                            {--force : 强制覆盖更新已存在的下载项}
                            {--dry-run : 演练模式：仅抓取和匹配，不实际写入数据库}';

    /**
     * 命令描述
     */
    protected $description = '爬取 xxxclub.to (及磁力狗 ciligou.net 备选兜底) 精确补充缺失下载项或缺失 4K(2160p) 清晰度的视频磁力资源';

    /**
     * 执行命令
     */
    public function handle(XxxclubCrawlerService $crawlerService, CiligouCrawlerService $ciligouService): int
    {
        $startTime = microtime(true);

        // 快捷选项：查看冷静期列表
        if ((bool) $this->option('list-cooldown')) {
            return $this->handleListCooldown($crawlerService);
        }

        // 快捷选项：单独清除某部视频的冷静期
        $clearCooldownId = $this->option('clear-cooldown-id') ? (int) $this->option('clear-cooldown-id') : null;
        if ($clearCooldownId) {
            $crawlerService->clearCoolingDown($clearCooldownId);
            $this->info("🧹 已成功清除视频 ID: [{$clearCooldownId}] 的冷静期标记！该视频已可重新参与抓取。");
        }

        $limit = max(0, (int) $this->option('limit'));
        $videoId = $this->option('video-id') ? (int) $this->option('video-id') : null;
        $channel = $this->option('channel');
        $localFile = $this->option('file');
        $localCiligouFile = $this->option('ciligou-file');
        $proxy = $this->option('proxy');
        $flaresolverrUrl = $this->option('flaresolverr');
        $noFlareSolverr = (bool) $this->option('no-flaresolverr');
        $delay = max(0, (int) $this->option('delay'));
        // 默认自动排除未来排期未上映视频；只有显式指定 --include-unreleased 时才允许处理未来片
        $includeUnreleased = (bool) $this->option('include-unreleased');
        $onlyReleased = !$includeUnreleased;
        $cooldownDays = max(0, (int) $this->option('cooldown-days'));
        $ignoreCooldown = (bool) $this->option('ignore-cooldown');
        $clearCooldown = (bool) $this->option('clear-cooldown');
        $order = strtolower((string) $this->option('order')) === 'asc' ? 'asc' : 'desc';
        $exportUnmatched = $this->option('export-unmatched');
        $force = (bool) $this->option('force');
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("================================================================================");
        $this->info("🎬 xxxclub.to 磁力下载资源自动爬取与精准补充工具 (集成磁力狗 ciligou.net 自动兜底)");
        $this->info("================================================================================");

        if ($clearCooldown) {
            $cleared = $crawlerService->clearAllCoolingDown();
            $this->info("🧹 已清空所有现存的冷却期标记" . ($cleared > 0 ? " (共清除 {$cleared} 条记录)" : "") . "，所有视频已恢复可抓取状态！");
        }

        if ($isDryRun) {
            $this->warn("⚠️  当前处于【--dry-run 演练模式】，仅执行检索、抓取与匹配分析，不写入数据库！");
        }

        // =========================================================================
        // 模式 A1：本地 xxxclub HTML 文件离线解析导入（--file=path/to/file.html）
        // =========================================================================
        if (!empty($localFile)) {
            return $this->handleLocalFileMode($crawlerService, $localFile, $videoId, $isDryRun, $force, $startTime);
        }

        // =========================================================================
        // 模式 A2：本地 磁力狗 HTML 文件离线解析导入（--ciligou-file=path/to/ciligou.html）
        // =========================================================================
        if (!empty($localCiligouFile)) {
            return $this->handleLocalCiligouFileMode($ciligouService, $crawlerService, $localCiligouFile, $videoId, $isDryRun, $force, $startTime);
        }

        // =========================================================================
        // 模式 B：在线网络抓取与补充
        // =========================================================================
        $this->info("🔍 正在筛选目标视频（无下载资源，或为 4K 片源但缺失 4K 下载项）...");

        try {
            $query = $crawlerService->getTargetVideosQuery($channel, $videoId, $onlyReleased, $order);
            $totalAvailable = $query->count();
        } catch (Throwable $e) {
            $this->error("❌ 查询数据库失败: " . $e->getMessage());
            $this->warn("💡 如果在本地 Docker/Sail 宿主机环境运行，请确认数据库连接配置或使用 ./vendor/bin/sail artisan 执行。");
            return self::FAILURE;
        }

        if ($totalAvailable === 0) {
            $this->info("🎉 检查完成：当前没有需要补充下载资源的视频！");
            return self::SUCCESS;
        }

        $this->info("📊 符合筛选条件的目标视频总量: {$totalAvailable} 部" . ($limit > 0 ? "，本次计划处理前 {$limit} 部有效目标" : "，本次全量处理"));

        $stats = [
            'total_videos'        => $totalAvailable,
            'processed_videos'    => 0,
            'future_skipped'      => 0,
            'cooldown_skipped'    => 0,
            'success_videos'      => 0,
            'ciligou_solved'      => 0,
            'empty_videos'        => 0,
            'flaresolverr_solved' => 0,
            'cf_blocked'          => 0,
            'error_videos'        => 0,
            'total_magnets'       => 0,
            'inserted_magnets'    => 0,
            'updated_magnets'     => 0,
            'skipped_magnets'     => 0,
        ];

        $unmatchedVideos = [];

        $planCount = $limit > 0 ? min($limit, $totalAvailable) : $totalAvailable;
        $progressBar = $this->output->createProgressBar($planCount);
        $progressBar->start();

        foreach ($query->cursor() as $video) {
            $videoCode = $video->video_code ?: ($video->name ?: "ID:{$video->id}");

            // 0. 未上映未来排期片检测（双重校验：release_at 或 video_code 中的未来日期）
            if ($onlyReleased && !$videoId && $crawlerService->isFutureVideo($video)) {
                $stats['future_skipped']++;
                $this->newLine();
                $this->line("⏭️  跳过未上映视频: <fg=gray>{$videoCode}</> <fg=yellow>[排期在未来，暂无种子]</>");
                continue;
            }

            // 1. 冷却期检查：未指定具体视频ID且未忽略冷却时，若在冷却期内则直接跳过并顺延
            $shouldCheckCooldown = !$ignoreCooldown && !$videoId;
            if ($shouldCheckCooldown && $crawlerService->isCoolingDown($video->id)) {
                $stats['cooldown_skipped']++;
                continue;
            }

            $stats['processed_videos']++;

            // 1. 智能生成搜索关键词
            $keyword = $crawlerService->buildSearchKeyword($video);

            // 2. 发起 HTTP / FlareSolverr 抓取请求 (xxxclub)
            $fetchResult = $crawlerService->fetchSearchHtml(
                $keyword,
                $proxy,
                [],
                $flaresolverrUrl,
                !$noFlareSolverr
            );

            if (!empty($fetchResult['via_flaresolverr']) && $fetchResult['success']) {
                $stats['flaresolverr_solved']++;
            }

            if ($fetchResult['is_cf']) {
                $stats['cf_blocked']++;
                $this->newLine();
                $this->warn("⚠️  [视频 ID: {$video->id}] 关键词 [{$keyword}] " . ($fetchResult['message'] ?? '触发了 Cloudflare 防护！'));
                if ($delay > 0) {
                    sleep($delay);
                }
                $progressBar->advance();
                if ($limit > 0 && $stats['processed_videos'] >= $limit) {
                    break;
                }
                continue;
            }

            if (!$fetchResult['success']) {
                $stats['error_videos']++;
                $progressBar->advance();
                if ($limit > 0 && $stats['processed_videos'] >= $limit) {
                    break;
                }
                continue;
            }

            // 3. 解析 xxxclub 搜索结果 HTML 并尝试匹配入库
            $matchedSource = null;
            $importResult = ['matched' => 0, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'matched_items' => []];

            $items = $crawlerService->parseSearchHtml($fetchResult['html']);
            if (!empty($items)) {
                $xxxResult = $crawlerService->importDownloads($video, $items, $isDryRun, $force);
                if ($xxxResult['matched'] > 0) {
                    $matchedSource = 'xxxclub';
                    $importResult = $xxxResult;
                }
            }

            // 4. 💡 备选搜索引擎兜底：如果 xxxclub 未搜到或未匹配到，调用磁力狗 (ciligou.net) 进行二次检索与日期精准比对
            if (empty($matchedSource)) {
                $ciligouItems = $ciligouService->searchAndMatch($video, $proxy);
                if (!empty($ciligouItems)) {
                    $ciligouResult = $crawlerService->importDownloads($video, $ciligouItems, $isDryRun, $force);
                    if ($ciligouResult['matched'] > 0) {
                        $matchedSource = 'ciligou';
                        $importResult = $ciligouResult;
                        $stats['ciligou_solved']++;
                    }
                }
            }

            if (!empty($matchedSource)) {
                $stats['success_videos']++;
                $stats['total_magnets'] += $importResult['matched'];
                $stats['inserted_magnets'] += $importResult['inserted'];
                $stats['updated_magnets'] += $importResult['updated'];
                $stats['skipped_magnets'] += $importResult['skipped'];

                // 成功获取，清除可能存在的历史冷却标记
                $crawlerService->clearCoolingDown($video->id);

                // 💡 单行输出匹配成功明细：视频code ➔ 磁力链接名称 (带来源标签)
                $this->newLine();
                $sourceLabel = $matchedSource === 'ciligou' ? '[磁力狗]' : '[xxxclub]';
                foreach ($importResult['matched_items'] ?? [] as $mItem) {
                    $resTag = !empty($mItem['resolution']) ? "[{$mItem['resolution']}] " : '';
                    $sizeTag = !empty($mItem['size_str']) ? " ({$mItem['size_str']})" : '';
                    $this->line("🎯 匹配成功 <fg=magenta>{$sourceLabel}</>: <fg=cyan>{$videoCode}</> ➔ <fg=green>{$resTag}{$mItem['title']}</><fg=gray>{$sizeTag}</>");
                    Log::info("磁力匹配成功 {$sourceLabel}: 视频Code [{$videoCode}] ➔ 磁力名称 [{$mItem['title']}]");
                }
            } else {
                $stats['empty_videos']++;
                $reason = '两站均未检索到匹配种子 (xxxclub & 磁力狗)';
                $unmatchedVideos[] = [
                    'id'         => $video->id,
                    'video_code' => $videoCode,
                    'keyword'    => $keyword,
                    'reason'     => $reason,
                ];

                // 标记进入冷却期
                if ($cooldownDays > 0 && !$videoId) {
                    $crawlerService->markCoolingDown($video->id, $cooldownDays);
                }

                $this->newLine();
                $this->line("⚠️  未匹配: <fg=yellow>{$videoCode}</> <fg=gray>({$reason})</>" . ($cooldownDays > 0 ? " <fg=blue>[进入 {$cooldownDays} 天冷却]</>" : ""));
                Log::info("磁力未检索到资源: 视频Code [{$videoCode}], 搜索词 [{$keyword}], 原因 [{$reason}]" . ($cooldownDays > 0 ? ", 已进入 {$cooldownDays} 天冷却期" : ""));
            }

            // 遵守请求延迟避免过频触发限流
            if ($delay > 0) {
                sleep($delay);
            }

            $progressBar->advance();

            // 达到计划处理配额则退出循环
            if ($limit > 0 && $stats['processed_videos'] >= $limit) {
                break;
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        $elapsedSeconds = round(microtime(true) - $startTime, 2);
        $formattedDuration = $this->formatDuration($elapsedSeconds);
        $avgPerVideo = $stats['processed_videos'] > 0
            ? round($elapsedSeconds / $stats['processed_videos'], 2) . ' 秒/部'
            : '-';

        // 输出执行汇总表
        $this->info("📋 执行结果汇总统计：");
        $xxxclubSolved = $stats['success_videos'] - $stats['ciligou_solved'];
        $this->table(
            ['指标项', '统计数值'],
            [
                ['目标视频总数', $stats['total_videos']],
                ['⏭️ 跳过未上映未来片', $stats['future_skipped']],
                ['❄️ 冷却期跳过 (Cool-down)', $stats['cooldown_skipped']],
                ['实际处理视频', $stats['processed_videos']],
                ['成功补充视频', $stats['success_videos']],
                ['  ├─ xxxclub 命中', $xxxclubSolved],
                ['  └─ 🐶 磁力狗兜底命中', $stats['ciligou_solved']],
                ['未搜到资源/无匹配', $stats['empty_videos']],
                ['FlareSolverr 成功穿透', $stats['flaresolverr_solved']],
                ['Cloudflare 拦截拦截次数', $stats['cf_blocked']],
                ['请求异常次数', $stats['error_videos']],
                ['匹配下载项总数', $stats['total_magnets']],
                ['新增下载项 (Inserted)', $stats['inserted_magnets']],
                ['覆盖更新下载项 (Updated)', $stats['updated_magnets']],
                ['跳过重复项 (Skipped)', $stats['skipped_magnets']],
                ['⏱️ 运行总耗时 (Duration)', $formattedDuration . " ({$elapsedSeconds}s)"],
                ['⚡ 平均每部耗时 (Avg Speed)', $avgPerVideo],
            ]
        );

        // 💡 打印未匹配的视频 Code 列表，方便用户直接复制到官网手动搜索
        if (!empty($unmatchedVideos)) {
            $this->newLine();
            $this->warn("================================================================================");
            $this->warn("📋 未匹配的视频 Code 列表（共 " . count($unmatchedVideos) . " 部，可复制至官网手动排查）：");
            $this->warn("================================================================================");
            foreach ($unmatchedVideos as $idx => $u) {
                $this->line(sprintf("  %2d. <fg=yellow>%s</>", $idx + 1, $u['video_code']));
            }
            $this->newLine();

            if (!empty($exportUnmatched)) {
                $lines = array_column($unmatchedVideos, 'video_code');
                file_put_contents($exportUnmatched, implode(PHP_EOL, $lines) . PHP_EOL);
                $this->info("💾 未匹配视频 Code 列表已保存至文件: {$exportUnmatched}");
            }
        }

        if ($stats['cf_blocked'] > 0) {
            $this->warn("💡 提示：检测到有 {$stats['cf_blocked']} 次请求被 Cloudflare 拦截。");
            $this->line("   可通过 `--proxy=http://127.0.0.1:7890` 配置海外代理，或配置 `--file=xxx.html` 进行离线导入。");
        }

        return self::SUCCESS;
    }

    /**
     * 处理本地 HTML 文件的离线导入测试模式
     */
    protected function handleLocalFileMode(
        XxxclubCrawlerService $crawlerService,
        string $filePath,
        ?int $videoId,
        bool $isDryRun,
        bool $force,
        float $startTime = 0.0
    ): int {
        if (!file_exists($filePath)) {
            $this->error("❌ 本地 HTML 文件不存在: {$filePath}");
            return self::FAILURE;
        }

        $html = file_get_contents($filePath);
        if (empty(trim($html))) {
            $this->error("❌ HTML 文件内容为空: {$filePath}");
            return self::FAILURE;
        }

        $this->info("📂 读取本地 HTML 文件成功: {$filePath} (" . number_format(strlen($html)) . " 字节)");
        $items = $crawlerService->parseSearchHtml($html);

        if (empty($items)) {
            $this->warn("⚠️  未能在 HTML 的 class=\"tsearch\" 列表中解析出任何下载资源。");
            return self::SUCCESS;
        }

        $this->info("🎯 成功解析出 " . count($items) . " 条下载资源：");

        $rows = [];
        foreach ($items as $idx => $item) {
            $rows[] = [
                $idx + 1,
                $item['resolution'] ?: $item['cat'],
                $item['size_str'],
                $item['hash'],
                $item['seeders'] . ' / ' . $item['leechers'],
                \Illuminate\Support\Str::limit($item['title'], 45),
            ];
        }

        $this->table(['#', '清晰度', '文件大小', 'Info Hash', 'S/L', '标题'], $rows);

        // 如果未指定具体视频 ID，尝试根据种子标题从数据库中反查对应的视频
        $video = null;
        try {
            if ($videoId) {
                $video = Video::with(['actors:id,name', 'channel:id,name'])->find($videoId);
            } else {
                // 取第一条条目的标题进行反查
                $firstTitle = $items[0]['title'] ?? '';
                // 尝试提取常见的 BrazzersExxtra.26.10.08... 格式
                if (preg_match('/^([a-zA-Z0-9.]+)/', $firstTitle, $codeMatches)) {
                    $testCode = $codeMatches[1];
                    $video = Video::with(['actors:id,name', 'channel:id,name'])
                        ->where('video_code', 'ILIKE', "%{$testCode}%")
                        ->orWhere('name', 'ILIKE', "%{$testCode}%")
                        ->first();
                }
            }
        } catch (Throwable $e) {
            $this->warn("⚠️  数据库连接不可用 (" . $e->getMessage() . ")，跳过自动关联。");
        }

        if (!$video) {
            $this->warn("ℹ️  未指定 --video-id，或数据库中未自动匹配到目标视频。");
            $this->line("   如需写入数据库，请确保数据库已连接，并指定: php artisan video:crawl-xxxclub-magnets --file={$filePath} --video-id=目标ID");
            return self::SUCCESS;
        }

        $this->info("🎬 目标关联视频: [ID: {$video->id}] {$video->name} (编码: {$video->video_code})");

        $importStats = $crawlerService->importDownloads($video, $items, $isDryRun, $force);

        if (!empty($importStats['matched_items'])) {
            $videoCode = $video->video_code ?: ($video->name ?: "ID:{$video->id}");
            $this->newLine();
            foreach ($importStats['matched_items'] as $mItem) {
                $resTag = !empty($mItem['resolution']) ? "[{$mItem['resolution']}] " : '';
                $sizeTag = !empty($mItem['size_str']) ? " ({$mItem['size_str']})" : '';
                $this->line("🎯 匹配成功: <fg=cyan>{$videoCode}</> ➔ <fg=green>{$resTag}{$mItem['title']}</><fg=gray>{$sizeTag}</>");
                Log::info("xxxclub 磁力匹配成功: 视频Code [{$videoCode}] ➔ 磁力名称 [{$mItem['title']}]");
            }
            $this->newLine();
        }

        $elapsedSeconds = $startTime > 0 ? round(microtime(true) - $startTime, 2) : 0;
        $tableData = [
            ['解析条数', $importStats['total']],
            ['匹配条数', $importStats['matched']],
            ['成功新增 (Inserted)', $importStats['inserted']],
            ['覆盖更新 (Updated)', $importStats['updated']],
            ['跳过重复 (Skipped)', $importStats['skipped']],
        ];

        if ($startTime > 0) {
            $tableData[] = ['⏱️ 运行总耗时 (Duration)', $this->formatDuration($elapsedSeconds) . " ({$elapsedSeconds}s)"];
        }

        $this->table(['指标', '数值'], $tableData);

        return self::SUCCESS;
    }

    /**
     * 处理本地磁力狗 HTML 文件的离线导入测试模式
     */
    protected function handleLocalCiligouFileMode(
        CiligouCrawlerService $ciligouService,
        XxxclubCrawlerService $crawlerService,
        string $filePath,
        ?int $videoId,
        bool $isDryRun,
        bool $force,
        float $startTime = 0.0
    ): int {
        if (!file_exists($filePath)) {
            $this->error("❌ 本地磁力狗 HTML 文件不存在: {$filePath}");
            return self::FAILURE;
        }

        $html = file_get_contents($filePath);
        if (empty(trim($html))) {
            $this->error("❌ HTML 文件内容为空: {$filePath}");
            return self::FAILURE;
        }

        $this->info("📂 读取本地磁力狗 HTML 文件成功: {$filePath} (" . number_format(strlen($html)) . " 字节)");
        $candidates = $ciligouService->parseSearchHtml($html);

        if (empty($candidates)) {
            $this->warn("⚠️  未能在 HTML 中解析出任何磁力狗候选结果。");
            return self::SUCCESS;
        }

        $this->info("🎯 成功解析出 " . count($candidates) . " 条磁力狗候选结果：");

        $rows = [];
        foreach ($candidates as $idx => $c) {
            $rows[] = [
                $idx + 1,
                $c['size_str'],
                $c['date_str'],
                $c['detail_url'],
                \Illuminate\Support\Str::limit($c['title'], 50),
            ];
        }
        $this->table(['#', '文件大小', '收录时间', '详情页', '标题'], $rows);

        // 如果未指定具体视频 ID，尝试根据种子标题从数据库中反查对应的视频
        $video = null;
        try {
            if ($videoId) {
                $video = Video::with(['actors:id,name', 'channel:id,name'])->find($videoId);
            } else {
                $firstTitle = $candidates[0]['title'] ?? '';
                if (preg_match('/([a-zA-Z\s]+)\s*-\s*([a-zA-Z\s]+)/', $firstTitle, $m)) {
                    $testName = trim($m[2]);
                    $video = Video::with(['actors:id,name', 'channel:id,name'])
                        ->where('name', 'ILIKE', "%{$testName}%")
                        ->orWhere('video_code', 'ILIKE', "%{$testName}%")
                        ->first();
                }
            }
        } catch (Throwable $e) {
            $this->warn("⚠️  数据库连接不可用 (" . $e->getMessage() . ")，跳过自动关联。");
        }

        if (!$video) {
            $this->warn("ℹ️  未指定 --video-id，或数据库中未自动匹配到目标视频。");
            $this->line("   如需测试精准比对与写入数据库，请指定: --ciligou-file={$filePath} --video-id=目标ID");
            return self::SUCCESS;
        }

        $this->info("🎬 目标关联视频: [ID: {$video->id}] {$video->name} (编码: {$video->video_code})");
        $dateInfo = $ciligouService->extractDateFromVideo($video);
        $this->line("📅 视频提取日期: " . json_encode($dateInfo, JSON_UNESCAPED_UNICODE));

        foreach ($candidates as $idx => $c) {
            $isMatched = $ciligouService->matchDateInTitle($c['title'], $dateInfo);
            $this->line("   条目 #" . ($idx + 1) . ($isMatched ? " <fg=green>[日期匹配成功 ➔ {$c['title']}]</>" : " <fg=red>[日期不匹配]</>"));
        }

        $elapsedSeconds = $startTime > 0 ? round(microtime(true) - $startTime, 2) : 0;
        $this->info("⏱️ 解析总耗时: " . $this->formatDuration($elapsedSeconds));

        return self::SUCCESS;
    }

    /**
     * 将秒数格式化为人类易读的时间字符串（如 1 小时 23 分 45 秒 / 2 分 15 秒 / 12.34 秒）
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

    /**
     * 处理 --list-cooldown 选项：格式化列出当前所有正处于冷静期中的视频及剩余时间
     */
    protected function handleListCooldown(XxxclubCrawlerService $crawlerService): int
    {
        $this->info("🔍 正在检索当前所有处于冷静期中的视频标记...");

        $items = $crawlerService->getCoolingDownVideos();

        if (empty($items)) {
            $this->info("🎉 当前没有任何处于冷静期中的视频！");
            return self::SUCCESS;
        }

        $total = count($items);
        $this->info("❄️  当前共有 <fg=cyan>{$total}</> 部视频处于冷静期中：");

        $rows = [];
        foreach ($items as $idx => $item) {
            $video = $item['video'];
            $code = $video ? ($video->video_code ?: ($video->name ?: "ID:{$item['id']}")) : "未知视频 [ID:{$item['id']}]";
            $channel = $video && $video->channel ? $video->channel->name : '-';
            $release = $video && $video->release_at ? Carbon::parse($video->release_at)->format('Y-m-d') : '-';

            $rows[] = [
                $item['id'],
                $code,
                $channel,
                $release,
                $item['ttl_formatted'],
            ];
        }

        $this->table(
            ['视频 ID', '视频 Code / 标题', '所属厂牌', '上映日期', '剩余冷静时间'],
            $rows
        );

        $this->newLine();
        $this->line("💡 <fg=yellow>快捷操作提示：</>");
        $this->line(" 1. 对指定视频立即重试抓取: <fg=cyan>php artisan video:crawl-xxxclub-magnets --video-id={ID}</>");
        $this->line(" 2. 单独解除某个视频的冷静期: <fg=cyan>php artisan video:crawl-xxxclub-magnets --clear-cooldown-id={ID}</>");
        $this->line(" 3. 忽略冷静期全量重新扫描:   <fg=cyan>php artisan video:crawl-xxxclub-magnets --ignore-cooldown --limit=50</>");
        $this->line(" 4. 一键清空所有视频的冷静期:   <fg=cyan>php artisan video:crawl-xxxclub-magnets --clear-cooldown</>");

        return self::SUCCESS;
    }
}
