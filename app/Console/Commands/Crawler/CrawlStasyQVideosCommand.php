<?php

namespace App\Console\Commands\Crawler;

use App\Jobs\CrawlStasyQVideosJob;
use App\Services\Crawler\StasyQCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrawlStasyQVideosCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:stasyq-videos
                            {--all : 初次全量分页抓取模式：从第 1 页向后连续抓取全部 Releases（默认仅抓取最新第 1 页）}
                            {--page= : 指定单页抓取的页码（如 --page=2）}
                            {--start-page=1 : 批量分页抓取时的起始页码}
                            {--max-page= : 批量分页抓取时的最大截止页码（留空则自动探测到末页为止）}
                            {--limit= : 处理视频条数上限}
                            {--delay=1 : 请求详情页或翻页之间的休眠间隔秒数（防风控，默认 1 秒）}
                            {--skip-existing : 若数据库已存在该视频且详情完整，跳过详情页请求（日常增量推荐）}
                            {--no-detail : 仅抓列表页数据，不进入详情页抓取大图与 Photos 预览图}
                            {--file= : 指定本地列表页 HTML 文件（如 stasyq-list.html）进行离线抓取}
                            {--detail-file= : 指定本地详情页 HTML 文件（如 stasyq-detail.html）进行离线测试}
                            {--queue : 异步投递至 Horizon 队列后台执行}
                            {--dry-run : 演练测试模式：仅解析并打印数据结构，不写入数据库}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '抓取 StasyQ 厂牌视频（日常抓取最新 Releases，初次全量支持分页抓取，自动进入详情页提取大图与 Photos 图集）';

    /**
     * 执行控制台命令
     */
    public function handle(StasyQCrawlerService $service): int
    {
        DB::disableQueryLog();

        $this->info("==================================================");
        $this->info("🎬 StasyQ 厂牌视频数据抓取任务启动");
        $this->info("==================================================");

        $crawlAll = (bool) $this->option('all');
        $pageOption = $this->option('page');
        $startPage = $pageOption !== null ? max(1, (int) $pageOption) : max(1, (int) ($this->option('start-page') ?: 1));
        $maxPage = $pageOption !== null ? $startPage : ($this->option('max-page') ? max($startPage, (int) $this->option('max-page')) : null);

        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;
        $delay = max(0, (int) ($this->option('delay') ?? 1));
        $skipExisting = (bool) $this->option('skip-existing');
        $fetchDetail = !$this->option('no-detail');
        $dryRun = (bool) $this->option('dry-run');
        $useQueue = (bool) $this->option('queue');

        if ($useQueue) {
            CrawlStasyQVideosJob::dispatch(
                page: $startPage,
                all: $crawlAll,
                skipExisting: $skipExisting,
                crawlDelay: $delay,
                limit: $limit
            );
            $modeLabel = $crawlAll ? '初次全量分页抓取' : "第 {$startPage} 页增量抓取";
            $this->info("🚀 已将 StasyQ 任务 [{$modeLabel}] 投递至 Horizon 队列！");
            return self::SUCCESS;
        }

        $fileOption = $this->option('file');
        $detailFileOption = $this->option('detail-file');

        $service->setLogger(function (string $message, string $level = 'info') {
            match ($level) {
                'error' => $this->error($message),
                'warn'  => $this->warn($message),
                default => $this->line($message),
            };
        });

        try {
            // 1. 解析离线文件（若指定了 --file，或者仅进行本地 --dry-run 且本地存在测试文件）
            $localListHtml = null;
            if ($fileOption) {
                $path = file_exists($fileOption) ? $fileOption : base_path($fileOption);
                if (!file_exists($path)) {
                    $this->error("❌ 列表页 HTML 文件不存在: {$path}");
                    return self::FAILURE;
                }
                $localListHtml = file_get_contents($path);
                $this->info("📂 列表数据源: 本地文件 [{$path}]");
            }

            $localDetailHtml = null;
            if ($detailFileOption) {
                $detailPath = file_exists($detailFileOption) ? $detailFileOption : base_path($detailFileOption);
                if (!file_exists($detailPath)) {
                    $this->error("❌ 详情页 HTML 文件不存在: {$detailPath}");
                    return self::FAILURE;
                }
                $localDetailHtml = file_get_contents($detailPath);
                $this->info("📂 详情数据源: 本地文件 [{$detailPath}]");
            }

            if ($dryRun) {
                return $this->runDryRun(
                    $service,
                    $startPage,
                    $maxPage,
                    $crawlAll,
                    $limit,
                    $delay,
                    $fetchDetail,
                    $localListHtml,
                    $localDetailHtml
                );
            }

            $channel = $service->ensureChannel();
            $this->info("🏷️ 绑定片商: {$channel->name} (ID: {$channel->id})");

            $totalParsed = 0;
            $successCount = 0;
            $failedCount = 0;

            // 离线单文件导入模式
            if ($localListHtml !== null) {
                $items = $service->parseReleasesHtml($localListHtml);
                if ($limit) {
                    $items = array_slice($items, 0, $limit);
                }
                $totalParsed = count($items);
                $this->info("🔍 本地文件共提取到 {$totalParsed} 个有效正片，开始同步入库...");

                foreach ($items as $idx => $item) {
                    $step = $idx + 1;
                    try {
                        $video = $service->saveOrUpdateVideo($item, $fetchDetail, $skipExisting, $localDetailHtml);
                        if ($video) {
                            $successCount++;
                            $this->line("[{$step}/{$totalParsed}] ✅ {$item['title']} (ID: {$video->id})");
                        }
                    } catch (Throwable $e) {
                        $failedCount++;
                        $this->error("[{$step}/{$totalParsed}] ❌ {$item['title']} - {$e->getMessage()}");
                    }

                    if ($fetchDetail && $localDetailHtml === null && $delay > 0 && $step < $totalParsed) {
                        sleep($delay);
                    }
                }
            } else {
                // 在线分页抓取模式
                $currentPage = $startPage;
                $isSinglePageMode = !$crawlAll && ($maxPage === null);

                if ($isSinglePageMode) {
                    $this->info("📅 当前模式: 日常增量抓取 (仅抓取第 {$currentPage} 页最新 Releases)");
                } else {
                    $endDesc = $maxPage ? "第 {$maxPage} 页" : "自动探测至末页";
                    $this->info("📚 当前模式: 全量分页抓取 (第 {$startPage} 页 ➔ {$endDesc})");
                }

                $processedGlobal = 0;

                while (true) {
                    $html = $service->fetchListPageHtml($currentPage);
                    if (empty($html)) {
                        $this->warn("⚠️ 第 {$currentPage} 页响应为空，终止分页循环");
                        break;
                    }

                    $items = $service->parseReleasesHtml($html);
                    $pageCount = count($items);
                    if ($pageCount === 0) {
                        $this->warn("⚠️ 第 {$currentPage} 页未解析到任何有效正片，已到达数据末尾");
                        break;
                    }

                    $this->info("📄 第 {$currentPage} 页提取到 {$pageCount} 部正片");
                    $totalParsed += $pageCount;

                    foreach ($items as $idx => $item) {
                        if ($limit && $processedGlobal >= $limit) {
                            $this->comment("🛑 已达到总条数上限 ({$limit})，停止抓取");
                            break 2;
                        }

                        $processedGlobal++;
                        try {
                            $video = $service->saveOrUpdateVideo($item, $fetchDetail, $skipExisting);
                            if ($video) {
                                $successCount++;
                                $this->line("   [P{$currentPage}-" . ($idx + 1) . "] ✅ {$item['title']} (ID: {$video->id})");
                            }
                        } catch (Throwable $e) {
                            $failedCount++;
                            $this->error("   [P{$currentPage}-" . ($idx + 1) . "] ❌ {$item['title']} - {$e->getMessage()}");
                        }

                        if ($fetchDetail && $delay > 0) {
                            sleep($delay);
                        }
                    }

                    if ($isSinglePageMode) {
                        break;
                    }
                    if ($maxPage !== null && $currentPage >= $maxPage) {
                        break;
                    }
                    if (!$service->hasNextPage($html, $currentPage)) {
                        $this->info("🏁 已到达最后一页 (第 {$currentPage} 页)，全量分页抓取结束");
                        break;
                    }

                    $currentPage++;
                    if ($delay > 0) {
                        sleep($delay);
                    }
                }
            }

            $service->syncChannelVideoNum();

            $this->newLine();
            $this->table(
                ['统计项', '数量'],
                [
                    ['提取正片总数', $totalParsed],
                    ['成功入库/更新', $successCount],
                    ['失败数量', $failedCount],
                    ['StasyQ 当前总视频量', $channel->videos()->count()],
                ]
            );

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("❌ 任务异常中断: " . $e->getMessage());
            return self::FAILURE;
        }
    }

    /**
     * 演练模式：实际发送网络请求抓取列表页（支持单页与多页分页测试）与详情页并展示提取结果（不写入数据库）
     */
    protected function runDryRun(
        StasyQCrawlerService $service,
        int $startPage,
        ?int $maxPage,
        bool $crawlAll,
        ?int $limit,
        int $delay,
        bool $fetchDetail,
        ?string $localListHtml,
        ?string $localDetailHtml
    ): int {
        $this->warn("⚠️ 当前为 [演练测试模式 (Dry-Run)]，不写入数据库：");

        $allItems = [];
        $pagesScanned = 0;

        if ($localListHtml !== null) {
            $pagesScanned = 1;
            $items = $service->parseReleasesHtml($localListHtml);
            if ($limit) {
                $items = array_slice($items, 0, $limit);
            }
            $allItems = $items;
            $this->info("📋 本地文件解析成功：共提取到 " . count($items) . " 部正式发布正片");
            $this->printItemsTable($items, 1);
        } else {
            $currentPage = $startPage;
            $isSinglePageMode = !$crawlAll && ($maxPage === null);

            if ($isSinglePageMode) {
                $this->info("📅 演练模式: 单页测试 (第 {$currentPage} 页)");
            } else {
                $endDesc = $maxPage ? "第 {$maxPage} 页" : "自动探测至末页";
                $this->info("📚 演练模式: 分页测试 (第 {$startPage} 页 ➔ {$endDesc})");
            }

            while (true) {
                $listHtml = $service->fetchListPageHtml($currentPage);
                if (empty($listHtml)) {
                    $this->error("❌ 第 {$currentPage} 页请求失败或响应为空");
                    break;
                }

                $pagesScanned++;
                $items = $service->parseReleasesHtml($listHtml);
                $hasNext = $service->hasNextPage($listHtml, $currentPage);

                if (empty($items)) {
                    if ($pagesScanned === 1) {
                        $pageTitle = '';
                        if (preg_match('~<title[^>]*>(.*?)</title>~is', $listHtml, $tm)) {
                            $pageTitle = trim(strip_tags($tm[1]));
                        }
                        $hasCardClass = str_contains($listHtml, 'release-preview-card') ? '是' : '否';
                        $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($listHtml))), 0, 200);

                        $debugPath = storage_path('logs/stasyq_debug.html');
                        @file_put_contents($debugPath, $listHtml);

                        $this->warn("🔎 诊断信息 -> HTML 大小: " . number_format(strlen($listHtml)) . " bytes | 页面标题: [{$pageTitle}] | 包含 release-preview-card: {$hasCardClass}");
                        $this->line("📄 正文文本摘要: {$snippet}");
                        $this->comment("💾 已将完整返回 HTML 保存至: {$debugPath}");
                    } else {
                        $this->warn("⚠️ 第 {$currentPage} 页未解析到有效正片，分页结束");
                    }
                    break;
                }

                if ($limit && (count($allItems) + count($items)) > $limit) {
                    $remaining = $limit - count($allItems);
                    $items = array_slice($items, 0, max(0, $remaining));
                }

                $nextTip = $hasNext ? '检测到下一页' : '无下一页';
                $this->info("📋 第 {$currentPage} 页解析成功：提取到 " . count($items) . " 部正片 ({$nextTip})");
                $this->printItemsTable($items, $currentPage);

                $allItems = array_merge($allItems, $items);

                if ($limit && count($allItems) >= $limit) {
                    $this->comment("🛑 已达到 --limit={$limit} 上限，停止翻页");
                    break;
                }
                if ($isSinglePageMode) {
                    break;
                }
                if ($maxPage !== null && $currentPage >= $maxPage) {
                    $this->info("🏁 已到达指定截止页 (第 {$maxPage} 页)");
                    break;
                }
                if (!$hasNext) {
                    $this->info("🏁 未检测到下一页，全量分页演练结束");
                    break;
                }

                $currentPage++;
                if ($delay > 0) {
                    sleep($delay);
                }
            }
        }

        if (empty($allItems)) {
            return self::SUCCESS;
        }

        $this->info("📊 列表分页演练汇总：共扫描 {$pagesScanned} 页，累计提取 " . count($allItems) . " 部正片");

        // 2. 若开启详情抓取，抽取第 1 条进行详情页完整数据展示
        if ($fetchDetail) {
            $first = $allItems[0];
            if ($localDetailHtml !== null) {
                $detail = $service->parseDetailHtml($localDetailHtml);
            } else {
                $this->info("🌐 正在实际请求第 1 条视频详情页 [{$first['title']}]: {$first['url']}");
                $detail = $service->crawlDetail($first['url']);
            }

            $this->info("🔎 详情页完整数据提取结果：");
            $this->table(
                ['详情字段', '完整内容'],
                [
                    ['高画质大图 (list_img_large_meta)', $detail['large_img'] ?: '未匹配'],
                    ['Photos 预览图总数', count($detail['screen_img']) . ' 张'],
                    ['简介全文 (description)', $detail['description'] ?: '暂无'],
                    ['标签 (tags)', implode(', ', $detail['tags']) ?: '暂无'],
                ]
            );

            if (!empty($detail['screen_img'])) {
                $photoRows = [];
                foreach ($detail['screen_img'] as $pIdx => $pItem) {
                    $photoRows[] = [$pIdx + 1, $pItem['source_url']];
                }
                $this->info("📸 Photos 全部预览图列表 (" . count($photoRows) . " 张)：");
                $this->table(['#', '预览图 URL'], $photoRows);
            }
        }

        return self::SUCCESS;
    }

    /**
     * 打印单页视频数据表格
     */
    protected function printItemsTable(array $items, int $page): void
    {
        $rows = [];
        foreach ($items as $idx => $item) {
            $rows[] = [
                "P{$page}-" . ($idx + 1),
                $item['source_id'] ?? 'N/A',
                $item['title'],
                $item['release_date'] ?? 'N/A',
                $item['views'],
                !empty($item['is_4k']) ? '4K' : 'HD',
                implode(', ', array_column($item['models'], 'name')) ?: '-',
                $item['cover_img'] ?: '无',
                $item['video_preview'] ?: '无',
            ];
        }

        $this->table(
            ['序号', '源站 ID', '标题', '发布日期', '浏览量', '画质', '模特', '列表封面图 URL', '预览视频 MP4'],
            $rows
        );
    }
}
