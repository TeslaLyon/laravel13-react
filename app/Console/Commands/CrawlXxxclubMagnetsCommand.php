<?php

namespace App\Console\Commands;

use App\Models\Video;
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
                            {--file= : 指定本地HTML文件进行离线解析与匹配测试 (如 Untitled-1.html)}
                            {--proxy= : 指定HTTP/SOCKS5代理 (例如 http://127.0.0.1:7890)}
                            {--flaresolverr= : 指定 FlareSolverr 服务的 URL 地址 (默认自动读取配置或 http://flaresolverr:8191/v1)}
                            {--no-flaresolverr : 禁用 FlareSolverr，仅使用常规 HTTP 直连}
                            {--delay=2 : 每次网络请求间隔延迟秒数 (默认2秒，避免频繁请求被风控)}
                            {--force : 强制覆盖更新已存在的下载项}
                            {--dry-run : 演练模式：仅抓取和匹配，不实际写入数据库}';

    /**
     * 命令描述
     */
    protected $description = '爬取 xxxclub.to 精确补充缺失下载项或缺失 4K(2160p) 清晰度的视频磁力资源';

    /**
     * 执行命令
     */
    public function handle(XxxclubCrawlerService $crawlerService): int
    {
        $limit = max(0, (int) $this->option('limit'));
        $videoId = $this->option('video-id') ? (int) $this->option('video-id') : null;
        $channel = $this->option('channel');
        $localFile = $this->option('file');
        $proxy = $this->option('proxy');
        $flaresolverrUrl = $this->option('flaresolverr');
        $noFlareSolverr = (bool) $this->option('no-flaresolverr');
        $delay = max(0, (int) $this->option('delay'));
        $force = (bool) $this->option('force');
        $isDryRun = (bool) $this->option('dry-run');

        $this->info("================================================================================");
        $this->info("🎬 xxxclub.to 磁力下载资源自动爬取与精准补充工具");
        $this->info("================================================================================");

        if ($isDryRun) {
            $this->warn("⚠️  当前处于【--dry-run 演练模式】，仅执行检索、抓取与匹配分析，不写入数据库！");
        }

        // =========================================================================
        // 模式 A：本地 HTML 文件离线解析导入（--file=path/to/file.html）
        // =========================================================================
        if (!empty($localFile)) {
            return $this->handleLocalFileMode($crawlerService, $localFile, $videoId, $isDryRun, $force);
        }

        // =========================================================================
        // 模式 B：在线网络抓取与补充
        // =========================================================================
        $this->info("🔍 正在筛选目标视频（无下载资源，或为 4K 片源但缺失 4K 下载项）...");

        try {
            $query = $crawlerService->getTargetVideosQuery($channel, $videoId);
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

        $this->info("📊 符合筛选条件的目标视频总量: {$totalAvailable} 部" . ($limit > 0 ? "，本次计划处理前 {$limit} 部" : "，本次全量处理"));

        if ($limit > 0) {
            $videos = $query->limit($limit)->get();
        } else {
            $videos = $query->get();
        }

        $stats = [
            'total_videos'        => $videos->count(),
            'processed_videos'    => 0,
            'success_videos'      => 0,
            'empty_videos'        => 0,
            'flaresolverr_solved' => 0,
            'cf_blocked'          => 0,
            'error_videos'        => 0,
            'total_magnets'       => 0,
            'inserted_magnets'    => 0,
            'updated_magnets'     => 0,
            'skipped_magnets'     => 0,
        ];

        $progressBar = $this->output->createProgressBar($videos->count());
        $progressBar->start();

        foreach ($videos as $video) {
            $stats['processed_videos']++;

            // 1. 智能生成搜索关键词
            $keyword = $crawlerService->buildSearchKeyword($video);

            // 2. 发起 HTTP / FlareSolverr 抓取请求
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
                continue;
            }

            if (!$fetchResult['success']) {
                $stats['error_videos']++;
                $progressBar->advance();
                continue;
            }

            // 3. 解析搜索结果 HTML
            $items = $crawlerService->parseSearchHtml($fetchResult['html']);

            if (empty($items)) {
                $stats['empty_videos']++;
            } else {
                // 4. 精确匹配并入库
                $importResult = $crawlerService->importDownloads($video, $items, $isDryRun, $force);

                if ($importResult['matched'] > 0) {
                    $stats['success_videos']++;
                    $stats['total_magnets'] += $importResult['matched'];
                    $stats['inserted_magnets'] += $importResult['inserted'];
                    $stats['updated_magnets'] += $importResult['updated'];
                    $stats['skipped_magnets'] += $importResult['skipped'];

                    // 💡 单行输出匹配成功明细：视频code ➔ 磁力链接名称
                    $videoCode = $video->video_code ?: ($video->name ?: "ID:{$video->id}");
                    $this->newLine();
                    foreach ($importResult['matched_items'] ?? [] as $mItem) {
                        $resTag = !empty($mItem['resolution']) ? "[{$mItem['resolution']}] " : '';
                        $sizeTag = !empty($mItem['size_str']) ? " ({$mItem['size_str']})" : '';
                        $this->line("🎯 匹配成功: <fg=cyan>{$videoCode}</> ➔ <fg=green>{$resTag}{$mItem['title']}</><fg=gray>{$sizeTag}</>");
                        Log::info("xxxclub 磁力匹配成功: 视频Code [{$videoCode}] ➔ 磁力名称 [{$mItem['title']}]");
                    }
                } else {
                    $stats['empty_videos']++;
                }
            }

            // 遵守请求延迟避免过频触发限流
            if ($delay > 0) {
                sleep($delay);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // 输出执行汇总表
        $this->info("📋 执行结果汇总统计：");
        $this->table(
            ['指标项', '统计数值'],
            [
                ['目标视频总数', $stats['total_videos']],
                ['实际处理视频', $stats['processed_videos']],
                ['成功补充视频', $stats['success_videos']],
                ['未搜到资源/无匹配', $stats['empty_videos']],
                ['FlareSolverr 成功穿透', $stats['flaresolverr_solved']],
                ['Cloudflare 拦截拦截次数', $stats['cf_blocked']],
                ['请求异常次数', $stats['error_videos']],
                ['匹配下载项总数', $stats['total_magnets']],
                ['新增下载项 (Inserted)', $stats['inserted_magnets']],
                ['覆盖更新下载项 (Updated)', $stats['updated_magnets']],
                ['跳过重复项 (Skipped)', $stats['skipped_magnets']],
            ]
        );

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
        bool $force
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

        $this->table(
            ['指标', '数值'],
            [
                ['解析条数', $importStats['total']],
                ['匹配条数', $importStats['matched']],
                ['成功新增 (Inserted)', $importStats['inserted']],
                ['覆盖更新 (Updated)', $importStats['updated']],
                ['跳过重复 (Skipped)', $importStats['skipped']],
            ]
        );

        return self::SUCCESS;
    }
}
