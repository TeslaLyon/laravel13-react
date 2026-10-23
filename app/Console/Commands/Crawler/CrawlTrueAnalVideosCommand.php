<?php

namespace App\Console\Commands\Crawler;

use App\Jobs\CrawlTrueAnalVideosJob;
use App\Models\Video;
use App\Services\Crawler\TpdbApiService;
use App\Services\Crawler\TrueAnalCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrawlTrueAnalVideosCommand extends Command
{
    /**
     * 控制台命令签名
     */
    protected $signature = 'crawler:trueanal
                            {--page= : 指定抓取的分页页码（例如 --page=1，单页执行）}
                            {--start-page=1 : 批量连续抓取时的起始页码（默认第 1 页）}
                            {--max-page=39 : 批量连续抓取时的最大截止页码（默认第 39 页）}
                            {--all : 开启连续向后循环抓取至最大页}
                            {--limit= : 每页处理视频上限数量（留空默认处理整页全部视频）}
                            {--delay=1 : 抓取分页间隔秒数，防触发频繁风控（默认 1 秒）}
                            {--skip-existing : 若本地数据库已存在该视频，跳过处理（极速日常增量模式）}
                            {--dry-run : 演练模式，仅打印解析与性别推导结果，不写入数据库}
                            {--queue : 异步投递至 Horizon 队列后台执行}
                            {--stagger=5 : 多页入队时的递增延迟间隔秒数（默认 5 秒），防止瞬时并发打崩源站}
                            {--file= : 指定本地列表页 HTML 文件（如 trueanal-list.html）进行离线测试}
                            {--detail-file= : 指定本地详情页 HTML 文件（如 trueanal-detail.html）进行离线测试}';

    /**
     * 控制台命令描述
     */
    protected $description = '爬取 TrueAnal 厂牌视频列表并结合 ThePornDB API 双级漏斗推导演员性别与同步元数据';

    public function handle(TrueAnalCrawlerService $service): int
    {
        DB::disableQueryLog();

        // 注册控制台实时输出
        $service->setLogger(function (string $message, string $level = 'info') {
            match ($level) {
                'error'   => $this->error($message),
                'warn'    => $this->warn($message),
                default   => $this->line($message),
            };
        });

        $pageOption = $this->option('page');
        if ($pageOption !== null && $pageOption !== '') {
            $startPage = max(1, (int) $pageOption);
            $maxPage = $startPage;
            $crawlAll = false;
        } else {
            $startPage = max(1, (int) ($this->option('start-page') ?: 1));
            $maxPage = max($startPage, (int) ($this->option('max-page') ?: TrueAnalCrawlerService::DEFAULT_MAX_PAGES));
            $crawlAll = (bool) $this->option('all');
        }

        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;
        $delay = max(0, (int) ($this->option('delay') ?? 1));
        $skipExisting = (bool) $this->option('skip-existing');
        $dryRun = (bool) $this->option('dry-run');
        $localListFile = $this->option('file');
        $localDetailFile = $this->option('detail-file');

        // 1. 本地单部详情页离线测试模式
        if ($localDetailFile) {
            if (!file_exists($localDetailFile)) {
                $this->error("❌ 找不到本地详情页文件: {$localDetailFile}");
                return Command::FAILURE;
            }

            $this->info("==================================================");
            $this->info("🚀 启动 TrueAnal 本地单部详情页测试模式");
            $this->line("📂 详情文件: <comment>{$localDetailFile}</comment>");
            $this->line("🛡️ 运行模式: " . ($dryRun ? '<comment>【演练模式 (Dry-Run)】</comment>' : '<info>【正式入库模式】</info>'));
            $this->info("==================================================");

            $detailHtml = file_get_contents($localDetailFile);
            $scene = $service->parseDetailHtml($detailHtml, 'https://tour.trueanal.com/scenes/local-test');
            if (!$scene) {
                $this->error("❌ 解析本地详情页失败！");
                return Command::FAILURE;
            }

            $this->displaySceneInfo($scene, 1, 1);
            $res = $service->saveVideo($scene, $dryRun);
            $this->displaySaveResult($res);
            return Command::SUCCESS;
        }

        // 2. 异步队列投递模式
        if ($this->option('queue')) {
            if ($localListFile) {
                $this->error('❌ 本地离线文件模式不支持 --queue 投递队列，请移除 --queue 参数进行控制台直接执行。');
                return Command::FAILURE;
            }

            $targetPages = $crawlAll ? range($startPage, $maxPage) : [$startPage];
            $totalPages = count($targetPages);
            $stagger = max(0, (int) ($this->option('stagger') ?? 5));

            $this->info("==================================================");
            $this->info("🚀 正在将 TrueAnal 抓取任务批量投递至 Horizon 队列...");
            $this->line("🎯 目标页码范围: <info>第 {$startPage} 页 至 第 {$maxPage} 页 (共 {$totalPages} 个任务)</info>");
            $this->line("⏱️ 入队递增延迟: <comment>{$stagger} 秒/页</comment> (防并发瞬时冲击)");
            $this->line("⏩ 跳过已入库: <comment>" . ($skipExisting ? '开启 (极速增量)' : '关闭 (全量比对)') . "</comment>");
            $this->line("🛡️ 运行模式: <comment>" . ($dryRun ? '演练模式 (Dry-Run)' : '正式入库') . "</comment>");
            $this->info("==================================================");

            foreach ($targetPages as $index => $page) {
                $delaySeconds = $index * $stagger;
                $job = new CrawlTrueAnalVideosJob(
                    page: $page,
                    skipExisting: $skipExisting,
                    crawlDelay: $delay,
                    limit: $limit,
                    dryRun: $dryRun
                );

                if ($delaySeconds > 0) {
                    $job->delay(now()->addSeconds($delaySeconds));
                }

                dispatch($job);
            }

            $this->info("✅ 成功将 {$totalPages} 个任务派发至队列！");
            $this->line("💡 您可以在 Horizon 控制面板实时查看任务消费进度与消费状态。");
            return Command::SUCCESS;
        }

        // 3. 同步控制台执行模式
        $this->info("==================================================");
        $this->info("🚀 启动 TrueAnal 视频抓取服务");
        $this->line("🎯 运行模式: " . ($dryRun ? '<comment>【演练模式 DRY-RUN (不写库)】</comment>' : '<info>【正式入库模式】</info>'));
        $this->line("🧬 性别推导引擎: <comment>1. 本地数据库反查 -> 2. ThePornDB 官方 API 查询</comment>");

        $token = config('services.tpdb.api_token') ?: env('TPDB_API_TOKEN');
        if (empty($token)) {
            $this->warn("⚠️  [警告] 未检测到 TPDB_API_TOKEN！未入库演员的性别将无法通过 API 识别。");
            $this->warn("👉 请在 .env 中设置 TPDB_API_TOKEN=your_token");
        } else {
            $maskedToken = substr($token, 0, 8) . '...' . substr($token, -4);
            $this->line("🔑 ThePornDB API Token: <info>{$maskedToken}</info>");
        }

        if ($localListFile) {
            $this->line("📂 列表来源: <comment>本地离线文件: {$localListFile}</comment>");
        }
        $this->info("==================================================");

        try {
            $channel = $service->ensureChannel();
            $this->line("🏢 片商已就绪: <info>{$channel->name} (slug: {$channel->slug}, id: {$channel->id})</info>");
        } catch (Throwable $e) {
            if ($dryRun) {
                $this->line("🏢 片商状态: <comment>TrueAnal (slug: trueanal) [演练免库模式]</comment>");
            } else {
                throw $e;
            }
        }

        $currentPage = $startPage;
        $totalProcessed = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        while (true) {
            $this->newLine();
            $this->info("--------------------------------------------------");
            $this->info("📄 正在抓取 TrueAnal 第 [{$currentPage}] 页...");

            // 1. 获取列表页 HTML
            if ($localListFile && file_exists($localListFile)) {
                $listHtml = file_get_contents($localListFile);
            } else {
                $pageUrl = $service->buildPageUrl($currentPage);
                $this->line("🌐 请求列表页: {$pageUrl}");
                $listHtml = $service->fetchHtml($pageUrl);
            }

            if (empty($listHtml)) {
                $this->error("❌ 获取第 {$currentPage} 页 HTML 失败或内容为空！");
                break;
            }

            // 2. 解析列表页
            $parseResult = $service->parseListHtml($listHtml);
            $scenes = $parseResult['scenes'] ?? [];
            $sceneCount = count($scenes);

            if (empty($scenes)) {
                $this->warn("⚠️ 第 {$currentPage} 页未解析出任何视频卡片，任务结束。");
                break;
            }

            $totalPages = $parseResult['total_pages'] ?: 1;
            $this->info("✅ 成功解析出 {$sceneCount} 部视频 (远端检测总数: {$parseResult['total']} 部, 总页数: {$totalPages})");

            if ($limit !== null) {
                $scenes = array_slice($scenes, 0, $limit);
                $this->line("✂️ 根据 --limit 限制，仅处理前 " . count($scenes) . " 部视频");
            }

            // 3. 逐部处理与入库
            foreach ($scenes as $index => $scene) {
                $displayIdx = $index + 1;
                $sourceUuid = $scene['source_uuid'];

                $this->displaySceneInfo($scene, $displayIdx, count($scenes));

                // 快速跳过已存在视频
                if ($skipExisting && !$dryRun) {
                    $exists = Video::where('source_uuid', $sourceUuid)->exists();
                    if ($exists) {
                        $this->line("   ⏩ [跳过] 本地已存在该视频 (启用了 --skip-existing)");
                        $totalSkipped++;
                        continue;
                    }
                }

                // 演练模式：推导演员性别并打印
                if ($dryRun) {
                    $this->comment("   [演练模式] 预览演员阵容:");
                    foreach ($scene['actors'] ?? [] as $actor) {
                        $genderStr = '女 (Female)';
                        try {
                            $gender = $service->resolveAndPersistActor($actor['name'], $actor['avatar'] ?? null)->gender;
                            $genderStr = match ($gender) {
                                TpdbApiService::GENDER_MALE   => '男 (Male)',
                                TpdbApiService::GENDER_FEMALE => '女 (Female)',
                                TpdbApiService::GENDER_TRANS  => '变性人 (Transgender)',
                                default                       => '未知 (Unknown)',
                            };
                        } catch (Throwable) {}
                        $this->line("     - {$actor['name']}: {$genderStr}");
                    }
                    $res = $service->saveVideo($scene, true);
                    $this->displaySaveResult($res);
                    $totalProcessed++;
                    continue;
                }

                // 正式入库
                try {
                    $saveRes = $service->saveVideo($scene, false);
                    $this->displaySaveResult($saveRes);

                    if ($saveRes['status'] === 'created') {
                        $totalCreated++;
                    } else {
                        $totalUpdated++;
                    }
                    $totalProcessed++;
                } catch (Throwable $e) {
                    $this->error("   ❌ 入库异常: " . $e->getMessage());
                }
            }

            // 是否继续抓取下一页
            if (!$crawlAll || $currentPage >= $maxPage || $currentPage >= $totalPages || $localListFile) {
                break;
            }

            $currentPage++;
            if ($delay > 0) {
                $this->line("⏱️ 等待 {$delay} 秒以避免频率过载...");
                sleep($delay);
            }
        }

        $this->newLine();
        $this->info("==================================================");
        $this->info("🏁 TrueAnal 抓取任务完成！");
        $this->line("📊 总计处理视频: <comment>{$totalProcessed}</comment> 部");
        if (!$dryRun) {
            $this->line("✨ 新增入库: <info>{$totalCreated}</info> 部");
            $this->line("🔄 更新入库: <info>{$totalUpdated}</info> 部");
            $this->line("⏩ 跳过已存在: <comment>{$totalSkipped}</comment> 部");
            $totalCount = $service->updateChannelVideoCount();
            $this->line("📈 TrueAnal 片商下本地视频总数: <info>{$totalCount}</info> 部");
        }
        $this->info("==================================================");

        return Command::SUCCESS;
    }

    /**
     * 打印单部视频的基础解析信息
     */
    protected function displaySceneInfo(array $scene, int $current, int $total): void
    {
        $this->newLine();
        $this->line("🎬 [{$current}/{$total}] 视频: <info>{$scene['name']}</info>");
        $this->line("   🆔 Source UUID: {$scene['source_uuid']}");
        $this->line("   🔗 详情页链接: {$scene['detail_url']}");
        $this->line("   ⏱️ 时长: {$scene['videos_duration']} ({$scene['movie_length']} 秒) | 画质: {$scene['max_quality']}" . ($scene['is_4k'] ? ' [4K超清]' : ''));
        $this->line("   📅 发布日期: " . ($scene['release_at'] ?? '未知'));

        $actorNames = array_column($scene['actors'] ?? [], 'name');
        $this->line("   🌟 演员阵容: " . (!empty($actorNames) ? implode(', ', $actorNames) : '无'));
        $this->line("   🏷️ 标签: " . (!empty($scene['tags']) ? implode(', ', array_slice($scene['tags'], 0, 8)) : '无'));
        $this->line("   📸 剧照数量: " . count($scene['extra_thumbnails'] ?? []) . " 张 | 悬停缩略图: " . count($scene['thumbs'] ?? []) . " 张");
        if (!empty($scene['trailer_url'])) {
            $this->line("   🎬 预告片直链: " . substr($scene['trailer_url'], 0, 80) . "...");
        }
    }

    /**
     * 打印入库结果
     */
    protected function displaySaveResult(array $res): void
    {
        if (($res['status'] ?? '') === 'created') {
            $this->info("   🎉 新增入库成功！VideoCode: [{$res['video_code']}] (关联演员: {$res['actor_count']} 位, 标签: {$res['tag_count']} 个)");
        } elseif (($res['status'] ?? '') === 'updated') {
            $this->info("   🔄 数据更新成功！VideoCode: [{$res['video_code']}] (关联演员: {$res['actor_count']} 位, 标签: {$res['tag_count']} 个)");
        } elseif (($res['status'] ?? '') === 'dry_run') {
            $this->comment("   🔍 演练推导完成！拟生成 VideoCode: [{$res['video_code']}]");
        }
    }
}
