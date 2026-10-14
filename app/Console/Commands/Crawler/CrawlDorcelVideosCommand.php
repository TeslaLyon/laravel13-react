<?php

namespace App\Console\Commands\Crawler;

use App\Models\Video;
use App\Services\Crawler\DorcelCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrawlDorcelVideosCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:dorcel-videos
                            {--page= : 指定抓取的分页页码（例如 --page=1，单页执行）}
                            {--start-page=1 : 批量连续抓取时的起始页码（默认第 1 页）}
                            {--max-page=69 : 批量连续抓取时的最大截止页码（默认第 69 页）}
                            {--all : 开启连续向后循环抓取至最大页}
                            {--limit= : 每页处理视频上限数量（留空默认处理整页全部视频）}
                            {--delay=1 : 抓取单部详情页间隔秒数，防触发频繁风控（默认 1 秒）}
                            {--skip-existing : 若本地数据库已存在该视频，跳过详情抓取（极速日常增量模式）}
                            {--dry-run : 演练模式，仅打印解析与匹配结果，不写入数据库}
                            {--file= : 指定本地列表页 HTML 文件（如 dorcel.html）进行离线测试}
                            {--detail-file= : 指定本地详情页 HTML 文件进行离线测试}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '高效率爬取 DorcelClub 厂牌视频列表并同步详情、演员与元数据至本地数据库（保存远程原图 URL，不上传 R2）';

    /**
     * 执行控制台命令
     */
    public function handle(DorcelCrawlerService $service): int
    {
        // 关键防爆核心：关闭数据库查询日志，防止大规模写入时累积 SQL 撑爆 PHP 内存
        DB::disableQueryLog();

        $pageOption = $this->option('page');
        if ($pageOption !== null && $pageOption !== '') {
            $startPage = max(1, (int) $pageOption);
            $maxPage = $startPage;
            $crawlAll = false;
        } else {
            $startPage = max(1, (int) ($this->option('start-page') ?: 1));
            $maxPage = max($startPage, (int) ($this->option('max-page') ?: 69));
            $crawlAll = (bool) $this->option('all');
        }

        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;
        $delay = max(0, (int) ($this->option('delay') ?? 1));
        $skipExisting = (bool) $this->option('skip-existing');
        $dryRun = (bool) $this->option('dry-run');
        $localFilePath = $this->option('file');
        $localDetailFilePath = $this->option('detail-file');

        // 注入详细步骤日志回调，实时输出至终端控制台
        $service->setLogger(function (string $message, string $level = 'info') {
            match ($level) {
                'error'   => $this->error("  {$message}"),
                'warn'    => $this->warn("  {$message}"),
                'comment' => $this->comment("  {$message}"),
                'line'    => $this->line("  {$message}"),
                default   => $this->info("  {$message}"),
            };
        });

        $channel = $service->ensureChannel();

        $this->info("==================================================");
        $this->info("🚀 开始启动 DorcelClub 视频数据同步任务");
        $this->line("🎯 目标厂牌: <info>{$channel->name} ({$channel->slug})</info>");
        if ($localFilePath) {
            $this->line("📁 本地列表文件: <info>{$localFilePath}</info>");
        } else {
            $modeText = $crawlAll ? "自动向后循环爬取 (至第 {$maxPage} 页)" : "单页抓取 (第 {$startPage} 页)";
            $this->line("📄 执行模式: <comment>{$modeText}</comment>");
        }
        if ($localDetailFilePath) {
            $this->line("📁 本地详情文件: <info>{$localDetailFilePath}</info>");
        }
        $this->line("⏱️ 详情间隔: <comment>{$delay} 秒</comment>");
        $this->line("⏩ 跳过已入库: <comment>" . ($skipExisting ? '开启 (极速增量)' : '关闭 (全量比对)') . "</comment>");
        $this->line("🖼️ 图片策略: <comment>直接保存原图 URL（不上传 R2）</comment>");
        $this->line("🛡️ 运行模式: <comment>" . ($dryRun ? '演练模式 (Dry-Run)' : '正式入库') . "</comment>");
        $this->info("==================================================");

        $currentPage = $startPage;
        $stats = [
            'total_scenes' => 0,
            'created'      => 0,
            'updated'      => 0,
            'skipped'      => 0,
            'delayed'      => 0,
            'failed'       => 0,
        ];

        do {
            $this->newLine();
            $this->info("🔍 [第 {$currentPage} 页] 正在拉取视频列表...");

            $localHtml = null;
            if ($localFilePath) {
                $path = file_exists($localFilePath) ? $localFilePath : base_path($localFilePath);
                if (!file_exists($path)) {
                    $this->error("❌ 本地列表文件不存在: {$localFilePath}");
                    return Command::FAILURE;
                }
                $localHtml = file_get_contents($path);
                $crawlAll = false;
            }

            $listResult = $service->crawlListPage($currentPage, $localHtml);
            if (!$listResult['success']) {
                $this->error("❌ [第 {$currentPage} 页] " . ($listResult['message'] ?? '拉取失败'));
                if (!$crawlAll) {
                    return Command::FAILURE;
                }
                $currentPage++;
                continue;
            }

            $scenes = $listResult['scenes'] ?? [];
            $sceneCount = count($scenes);
            $this->line("📦 本页成功解析出 <comment>{$sceneCount}</comment> 部视频");

            if ($sceneCount === 0) {
                $this->warn("⚠️ 本页未发现任何视频，可能已到达最末页，停止向后爬取。");
                break;
            }

            if ($limit && $limit < $sceneCount) {
                $scenes = array_slice($scenes, 0, $limit);
                $this->line("⚠️ 触发 --limit 限制，本页截取前 <comment>{$limit}</comment> 部处理");
            }

            foreach ($scenes as $index => $sceneItem) {
                $stats['total_scenes']++;
                $sceneIndex = $index + 1;
                $sceneTitle = $sceneItem['name'];
                $sourceUuid = $sceneItem['source_uuid'];

                $this->line(sprintf("  [%d/%d] 🎬 视频: <info>%s</info> (UUID: %s)", $sceneIndex, count($scenes), $sceneTitle, $sourceUuid));

                // 极速增量模式：若本地库中已存在该 source_uuid 且开启了 --skip-existing，直接跳过详情抓取
                if ($skipExisting && Video::where('source_uuid', $sourceUuid)->exists()) {
                    $this->comment("     ⏩ 本地已存在该视频，跳过详情抓取");
                    $stats['skipped']++;
                    continue;
                }

                // 详情页抓取
                $detailData = null;
                $localDetailHtml = null;
                if ($localDetailFilePath) {
                    $dpath = file_exists($localDetailFilePath) ? $localDetailFilePath : base_path($localDetailFilePath);
                    if (file_exists($dpath)) {
                        $localDetailHtml = file_get_contents($dpath);
                    }
                }

                try {
                    $detailData = $service->crawlSceneDetail($sceneItem['detail_url'], $localDetailHtml);
                } catch (Throwable $e) {
                    $this->warn("     ⚠️ 抓取详情页异常: " . $e->getMessage());
                }

                // 数据持久化
                try {
                    $syncResult = $service->syncSceneToDatabase($sceneItem, $detailData, $dryRun);

                    match ($syncResult['status']) {
                        'created' => $this->info(sprintf("     ✅ [新增入库] Code: %s, 关联演员: %d 位", $syncResult['video_code'], $syncResult['actor_count'] ?? 0)),
                        'updated' => $this->line(sprintf("     🔄 [更新入库] Code: %s, 关联演员: %d 位", $syncResult['video_code'], $syncResult['actor_count'] ?? 0)),
                        'delayed' => $this->warn(sprintf("     ⚠️ [延期观察] %s", $syncResult['message'])),
                        'dry_run' => $this->comment(sprintf("     🛡️ [演练模拟] Code: %s, 演员: [%s]", $syncResult['video_code'], implode(', ', $syncResult['actors'] ?? []))),
                        default   => $this->line("     ℹ️ 状态: " . ($syncResult['status'] ?? '未知')),
                    };

                    if ($syncResult['status'] === 'created') {
                        $stats['created']++;
                    } elseif ($syncResult['status'] === 'updated') {
                        $stats['updated']++;
                    } elseif ($syncResult['status'] === 'delayed') {
                        $stats['delayed']++;
                    }
                } catch (Throwable $e) {
                    $this->error("     ❌ 视频入库异常: " . $e->getMessage());
                    $stats['failed']++;
                }

                // 请求间隔休眠防封禁
                if ($delay > 0 && $localDetailHtml === null) {
                    sleep($delay);
                }
            }

            // 更新片商库中视频统计数量
            if (!$dryRun) {
                $service->updateChannelVideoCount();
            }

            $currentPage++;
        } while ($crawlAll && $currentPage <= $maxPage);

        // 任务汇总报表
        $this->newLine();
        $this->info("==================================================");
        $this->info("🎉 DorcelClub 爬取同步任务执行完成！");
        $this->table(
            ['指标项目', '统计结果'],
            [
                ['扫描视频总数', $stats['total_scenes']],
                ['新增入库', $stats['created']],
                ['更新入库', $stats['updated']],
                ['跳过已存在', $stats['skipped']],
                ['冲突延期 (Delayed)', $stats['delayed']],
                ['处理失败', $stats['failed']],
                ['运行模式', $dryRun ? '演练模式 (未写入)' : '正式入库 (已持久化)'],
            ]
        );
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}

