<?php

namespace App\Console\Commands\Crawler;

use App\Models\Video;
use App\Services\Crawler\JulesJordanCrawlerService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CrawlJulesJordanVideosCommand extends Command
{
    /**
     * 控制台命令签名
     */
    protected $signature = 'crawler:jules-jordan
                            {--page= : 指定抓取的分页页码（例如 --page=1，单页执行）}
                            {--start-page=1 : 批量连续抓取时的起始页码（默认第 1 页）}
                            {--max-page=93 : 批量连续抓取时的最大截止页码（默认第 93 页）}
                            {--all : 开启连续向后循环抓取至最大页}
                            {--limit= : 每页处理视频上限数量（留空默认处理整页全部视频）}
                            {--delay=1 : 抓取单部详情页间隔秒数，防触发频繁风控（默认 1 秒）}
                            {--skip-existing : 若本地数据库已存在该视频，跳过详情抓取（极速日常增量模式）}
                            {--dry-run : 演练模式，仅打印解析与性别推导结果，不写入数据库}
                            {--file= : 指定本地列表页 HTML 文件（如 jules-list.html）进行离线测试}
                            {--detail-file= : 指定本地详情页 HTML 文件（如 jules-detail.html）进行离线测试}';

    /**
     * 控制台命令描述
     */
    protected $description = '爬取 Jules Jordan 厂牌视频列表并结合 ThePornDB API 双级漏斗推导演员性别与同步元数据';

    public function handle(JulesJordanCrawlerService $service): int
    {
        DB::disableQueryLog();

        // 注册控制台日志输出
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
            $maxPage = max($startPage, (int) ($this->option('max-page') ?: 93));
            $crawlAll = (bool) $this->option('all');
        }

        $limit = $this->option('limit') ? max(1, (int) $this->option('limit')) : null;
        $delay = max(0, (int) ($this->option('delay') ?? 1));
        $skipExisting = (bool) $this->option('skip-existing');
        $dryRun = (bool) $this->option('dry-run');
        $localListFile = $this->option('file');
        $localDetailFile = $this->option('detail-file');

        $this->info("==================================================");
        $this->info("🚀 启动 Jules Jordan 视频抓取服务");
        $this->line("🎯 模式: " . ($dryRun ? '<comment>【演练模式 DRY-RUN (不写库)】</comment>' : '<info>【正式入库模式】</info>'));
        $this->line("🧬 性别推导引擎: <comment>1. 本地数据库反查 -> 2. ThePornDB 官方 API 查询</comment>");

        $token = config('services.tpdb.api_token') ?: env('TPDB_API_TOKEN');
        if (empty($token)) {
            $this->warn("⚠️  [警告] 未检测到 TPDB_API_TOKEN！未入库演员的性别将无法通过 API 识别（将记为 0 未知）。");
            $this->warn("👉 请在 .env 中设置 TPDB_API_TOKEN=your_token，并执行 sail artisan config:clear");
        } else {
            $maskedToken = substr($token, 0, 8) . '...' . substr($token, -4);
            $this->line("🔑 ThePornDB API Token: <info>{$maskedToken}</info>");
        }

        if ($localListFile) {
            $this->line("📂 列表来源: <comment>本地离线文件: {$localListFile}</comment>");
        }
        if ($localDetailFile) {
            $this->line("📂 详情来源: <comment>本地离线文件: {$localDetailFile}</comment>");
        }
        $this->info("==================================================");

        $channel = $service->ensureChannel();
        $this->line("🏢 片商已就绪: <info>{$channel->name} (slug: {$channel->slug}, id: {$channel->id})</info>");

        $currentPage = $startPage;
        $totalProcessed = 0;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        while (true) {
            $this->newLine();
            $this->info("--------------------------------------------------");
            $this->info("📄 正在抓取 Jules Jordan 第 [{$currentPage}] 页...");

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
            if (!$parseResult['success'] || empty($parseResult['scenes'])) {
                $this->warn("⚠️ 第 {$currentPage} 页未解析出任何视频卡片，任务结束。");
                break;
            }

            $scenes = $parseResult['scenes'];
            $sceneCount = count($scenes);
            $this->info("✅ 成功解析出 {$sceneCount} 部视频卡片 (网站检测总页数: {$parseResult['total_pages']})");

            if ($limit !== null) {
                $scenes = array_slice($scenes, 0, $limit);
                $this->line("✂️ 根据 --limit 限制，仅处理前 " . count($scenes) . " 部视频");
            }

            // 3. 逐部抓取详情与入库
            foreach ($scenes as $index => $scene) {
                $displayIdx = $index + 1;
                $videoName = $scene['name'];
                $sourceUuid = $scene['source_uuid'];
                $detailUrl = $scene['detail_url'];

                $this->newLine();
                $this->line("🎬 [{$displayIdx}/" . count($scenes) . "] 视频: <info>{$videoName}</info>");
                $this->line("   🆔 Source UUID: {$sourceUuid}");
                $this->line("   🔗 详情页链接: {$detailUrl}");

                // 快速跳过已存在
                if ($skipExisting && !$dryRun) {
                    $exists = Video::where('source_uuid', $sourceUuid)->exists();
                    if ($exists) {
                        $this->line("   ⏩ [跳过] 本地已存在该视频 (启用了 --skip-existing)");
                        $totalSkipped++;
                        continue;
                    }
                }

                // 获取详情页 HTML
                if ($localDetailFile && file_exists($localDetailFile)) {
                    $detailHtml = file_get_contents($localDetailFile);
                } else {
                    $this->line("   📡 请求详情页...");
                    $detailHtml = $service->fetchHtml($detailUrl);
                }

                if (empty($detailHtml)) {
                    $this->warn("   ⚠️ 获取详情页 HTML 失败，跳过该视频！");
                    continue;
                }

                // 解析详情页
                $detailItem = $service->parseDetailHtml($detailHtml, $detailUrl);

                $actorsList = $detailItem['actors'] ?? ($scene['actors'] ?? []);
                $actorNames = array_column($actorsList, 'name');
                $tagsList = $detailItem['tags'] ?? [];

                $this->line("   ⏱️ 时长: " . ($detailItem['movie_length'] ?? 0) . " 秒");
                $this->line("   📅 发布日期: " . ($detailItem['release_at'] ?? '未知'));
                $this->line("   🌟 演员阵容: " . implode(', ', $actorNames));
                $this->line("   🏷️ 标签: " . implode(', ', $tagsList));
                $this->line("   📸 剧照数量: " . count($detailItem['screen_img'] ?? []) . " 张");
                if (!empty($detailItem['trailer_url'])) {
                    $this->line("   🎬 预告片直链: " . substr($detailItem['trailer_url'], 0, 80) . "...");
                }

                if ($dryRun) {
                    $this->comment("   [演练模式] 预览推导演员性别:");
                    foreach ($actorsList as $actor) {
                        $gender = $service->resolveAndPersistActor($actor['name'])->gender;
                        $genderStr = match ($gender) {
                            1 => '男 (Male)',
                            2 => '女 (Female)',
                            3 => '变性人 (Transgender)',
                            default => '未知 (Unknown)',
                        };
                        $this->line("     - {$actor['name']}: {$genderStr}");
                    }
                    $totalProcessed++;
                    continue;
                }

                // 入库
                try {
                    $saveRes = $service->saveVideo($scene, $detailItem);
                    if ($saveRes['status'] === 'created') {
                        $this->info("   🎉 新增入库成功！VideoCode: [{$saveRes['video_code']}]");
                        $totalCreated++;
                    } else {
                        $this->info("   🔄 更新数据成功！VideoCode: [{$saveRes['video_code']}]");
                        $totalUpdated++;
                    }
                    $totalProcessed++;
                } catch (Throwable $e) {
                    $this->error("   ❌ 入库异常: " . $e->getMessage());
                }

                // 控制抓取频次
                if ($delay > 0 && !$localDetailFile && $displayIdx < count($scenes)) {
                    sleep($delay);
                }
            }

            // 是否继续下一页
            if (!$crawlAll || $currentPage >= $maxPage || $currentPage >= $parseResult['total_pages']) {
                break;
            }

            $currentPage++;
            if ($delay > 0 && !$localListFile) {
                sleep($delay);
            }
        }

        $this->newLine();
        $this->info("==================================================");
        $this->info("🏁 Jules Jordan 抓取任务完成！");
        $this->line("📊 总计处理视频: <comment>{$totalProcessed}</comment> 部");
        if (!$dryRun) {
            $this->line("✨ 新增入库: <info>{$totalCreated}</info> 部");
            $this->line("🔄 更新入库: <info>{$totalUpdated}</info> 部");
            $this->line("⏩ 跳过已存在: <comment>{$totalSkipped}</comment> 部");
            $totalCount = $service->updateChannelVideoCount();
            $this->line("📈 Jules Jordan 片商下本地视频总数: <info>{$totalCount}</info> 部");
        }
        $this->info("==================================================");

        return Command::SUCCESS;
    }
}

