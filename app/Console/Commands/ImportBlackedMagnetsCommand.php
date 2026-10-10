<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Models\VideoDownload;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportBlackedMagnetsCommand extends Command
{
    /**
     * 命令签名与选项
     *
     * @var string
     */
    protected $signature = 'video:import-blacked-magnets
                            {file=torrent.txt : 包含磁力链接的文件路径}
                            {--channel=blackedraw : 目标片商 slug 或名称}
                            {--date-window=1 : 允许的发布日期前后浮动天数(默认前后1天)}
                            {--dry-run : 演练模式：仅匹配并输出统计报告，不实际写入数据库}
                            {--force : 强制更新已存在的下载记录}
                            {--export-unmatched= : 将未匹配的磁力链接导出到指定文件(默认自动保存到 unmatched_magnets.txt)}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '根据女演员/原片片名与发布日期（容许前后一天时区差），将 BlackedRaw 磁力链接匹配并导入到 video_downloads 表中';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
        $channelIdentifier = $this->option('channel') ?: 'blackedraw';
        $dateWindow = max(0, (int) $this->option('date-window'));
        $isDryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (!file_exists($filePath)) {
            $this->error("❌ 未找到磁力文件: {$filePath}");
            return self::FAILURE;
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (empty($lines)) {
            $this->warn("⚠️ 文件内容为空: {$filePath}");
            return self::SUCCESS;
        }

        $this->info("📂 读取磁力文件成功，共 " . count($lines) . " 行数据。");
        if ($isDryRun) {
            $this->warn("🔍 当前处于 --dry-run 演练模式，将只进行匹配分析，不执行数据库写入操作。");
        }

        // 1. 查找目标片商
        $channel = Channel::where('slug', $channelIdentifier)
            ->orWhereRaw('LOWER(name) = ?', [strtolower($channelIdentifier)])
            ->orWhere('name', 'ILIKE', "%{$channelIdentifier}%")
            ->first();

        if (!$channel) {
            $this->error("❌ 数据库中未找到片商: [{$channelIdentifier}]");
            return self::FAILURE;
        }

        $this->info("🎬 目标片商匹配成功: [{$channel->name}] (ID: {$channel->id}, Slug: {$channel->slug})");

        // 2. 预加载该片商下的所有视频与演员
        $this->info("⏳ 正在预加载片商 [{$channel->name}] 下的所有视频与演员数据...");
        $videos = Video::where('channel_id', $channel->id)
            ->with('actors:id,name,slug')
            ->select(['id', 'channel_id', 'name', 'slug', 'video_code', 'release_at'])
            ->get();

        if ($videos->isEmpty()) {
            $this->error("❌ 片商 [{$channel->name}] 下暂无任何视频记录，请先同步片商视频元数据！");
            return self::FAILURE;
        }

        // 🎯 明确查询 2024 年以前的视频数据分布情况
        $before2024Count = Video::where('channel_id', $channel->id)
            ->where(function ($q) {
                $q->where('release_at', '<', '2024-01-01 00:00:00')
                  ->orWhere('video_code', 'LIKE', '%.17.%')
                  ->orWhere('video_code', 'LIKE', '%.18.%')
                  ->orWhere('video_code', 'LIKE', '%.19.%')
                  ->orWhere('video_code', 'LIKE', '%.20.%')
                  ->orWhere('video_code', 'LIKE', '%.21.%')
                  ->orWhere('video_code', 'LIKE', '%.22.%')
                  ->orWhere('video_code', 'LIKE', '%.23.%');
            })
            ->count();

        $after2024Count = Video::where('channel_id', $channel->id)
            ->where('release_at', '>=', '2024-01-01 00:00:00')
            ->count();

        $nullDateCount = Video::where('channel_id', $channel->id)
            ->whereNull('release_at')
            ->count();

        $this->info("✅ 成功加载 {$videos->count()} 部视频 (其中 2024 年以前: {$before2024Count} 部, 2024 年及以后: {$after2024Count} 部, 无日期: {$nullDateCount} 部)，准备开始逐条匹配...");

        // 3. 构建内存索引加速比对
        $indexedVideos = $this->buildVideoIndex($videos);

        // 4. 开始逐条匹配并写入
        DB::disableQueryLog();

        $stats = [
            'total'          => count($lines),
            'matched'        => 0,
            'exact_date'     => 0, // 同一天匹配
            'offset_1_day'   => 0, // 前后1天匹配
            'offset_multi'   => 0, // 前后2~3天匹配
            'inserted'       => 0,
            'updated'        => 0,
            'skipped'        => 0,
            'unmatched'      => 0,
            'ambiguous'      => 0,
            'invalid_format' => 0,
        ];

        $unmatchedItems = [];
        $matchedSamples = [];

        $progressBar = $this->output->createProgressBar(count($lines));
        $progressBar->start();

        foreach ($lines as $lineIndex => $line) {
            $progressBar->advance();
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // 解析磁力链接
            $parsed = $this->parseMagnetLine($line);
            if (!$parsed) {
                $stats['invalid_format']++;
                $unmatchedItems[] = [
                    'line'          => $lineIndex + 1,
                    'dn'            => '(无法解析磁力链接格式)',
                    'date'          => '-',
                    'name_or_actor' => '-',
                    'magnet'        => $line,
                    'reason'        => '磁力链接或 dn 命名格式不符合规范',
                ];
                continue;
            }

            // 查找最匹配的视频
            $matchResult = $this->findBestMatchingVideo($parsed, $indexedVideos, $dateWindow);

            if (!$matchResult['video']) {
                if ($matchResult['reason'] === 'ambiguous') {
                    $stats['ambiguous']++;
                } else {
                    $stats['unmatched']++;
                }

                $unmatchedItems[] = [
                    'line'          => $lineIndex + 1,
                    'dn'            => $parsed['dn'],
                    'date'          => $parsed['date'],
                    'name_or_actor' => $parsed['raw_section'],
                    'magnet'        => $line,
                    'reason'        => $matchResult['reason'],
                ];
                continue;
            }

            $matchedVideo = $matchResult['video'];
            $daysDiff = $matchResult['days_diff'];

            $stats['matched']++;
            if ($daysDiff === 0) {
                $stats['exact_date']++;
            } elseif ($daysDiff === 1) {
                $stats['offset_1_day']++;
            } else {
                $stats['offset_multi']++;
            }

            if (count($matchedSamples) < 5) {
                $matchedSamples[] = [
                    'dn'         => $parsed['dn'],
                    'video'      => $matchedVideo->name ?: $matchedVideo->video_code,
                    't_date'     => $parsed['date'],
                    'v_date'     => substr((string) $matchedVideo->release_at, 0, 10),
                    'days_diff'  => $daysDiff,
                    'resolution' => $parsed['resolution'],
                ];
            }

            // 5. 写入 video_downloads 表 (按 hash 全局排重，确保绝对幂等不污染数据)
            if (!$isDryRun) {
                $existing = VideoDownload::where('hash', $parsed['hash'])->first();

                if ($existing && !$force) {
                    $stats['skipped']++;
                    continue;
                }

                $downloadPayload = [
                    'video_id'         => $matchedVideo->id,
                    'user_id'          => null,
                    'title'            => $parsed['dn'],
                    'type'             => 'magnet',
                    'cost_type'        => 'free',
                    'resolution'       => $parsed['resolution'],
                    'price'            => null,
                    'link'             => $line,
                    'hash'             => $parsed['hash'],
                    'file_size'        => null,
                    'extraction_code'  => null,
                    'archive_password' => null,
                    'description'      => "匹配自 {$channel->name} 官方视频库 (原发布日期: " . substr((string) $matchedVideo->release_at, 0, 10) . ")",
                    'status'           => 1,
                    'sort_order'       => $parsed['sort_order'],
                ];

                if ($existing) {
                    $existing->update($downloadPayload);
                    $stats['updated']++;
                } else {
                    VideoDownload::create($downloadPayload);
                    $stats['inserted']++;
                }

                if (!$matchedVideo->has_downloads) {
                    $matchedVideo->update(['has_downloads' => true]);
                    $matchedVideo->has_downloads = true;
                }
            }
        }

        $progressBar->finish();
        $this->newLine(2);

        // 6. 输出汇总统计
        $this->info("=================== 匹配与导入汇总 ===================");
        $this->table(
            ['指标项', '数量'],
            [
                ['磁力链接总数', $stats['total']],
                ['成功匹配视频数', $stats['matched']],
                ['  ├─ 同一天精准匹配', $stats['exact_date']],
                ['  ├─ 前后1天容错匹配 (重点时区纠偏)', $stats['offset_1_day']],
                ['  └─ 前后2~3天宽限匹配', $stats['offset_multi']],
                ['未匹配数量', $stats['unmatched']],
                ['存在多部模糊歧义跳过', $stats['ambiguous']],
                ['格式无效行', $stats['invalid_format']],
                ['数据库新插入记录', $stats['inserted']],
                ['数据库更新记录', $stats['updated']],
                ['已存在跳过记录', $stats['skipped']],
            ]
        );

        if (!empty($matchedSamples)) {
            $this->info("\n🎯 典型成功匹配示例 (前 5 组):");
            $this->table(['磁力文件名 (dn)', '匹配到的视频', '磁力日期', '视频日期', '相差天数', '清晰度'], $matchedSamples);
        }

        // 7. 详细输出所有未匹配的数据
        if (!empty($unmatchedItems)) {
            $this->newLine();
            $this->warn("⚠️  共检测到 " . count($unmatchedItems) . " 条未匹配的磁力链接，明细如下：");

            // (1) 完整表格输出所有未匹配条目
            $tableRows = array_map(function ($item) {
                return [
                    $item['line'],
                    $item['dn'],
                    $item['date'],
                    $item['name_or_actor'],
                    $item['reason'],
                ];
            }, $unmatchedItems);

            $this->table(['行号', '磁力文件名 (dn)', '磁力日期', '演员/原片名 (识别文本)', '未匹配原因'], $tableRows);

            // (2) 完整打印原始磁力链接，方便终端直接复制查看
            $this->warn("\n📋 未匹配磁力链接明细 (直接复制):");
            foreach ($unmatchedItems as $item) {
                $this->line("[第 {$item['line']} 行] {$item['magnet']}");
            }

            // (3) 自动保存到文件
            $exportFile = $this->option('export-unmatched') ?: 'unmatched_magnets.txt';
            $exportLines = [
                "# BlackedRaw 未匹配磁力链接记录",
                "# 生成时间: " . date('Y-m-d H:i:s'),
                "# 总未匹配数: " . count($unmatchedItems),
                "# ----------------------------------------------------",
            ];
            foreach ($unmatchedItems as $item) {
                $exportLines[] = "# [行号: {$item['line']}] 日期: {$item['date']} | 演员/原片名: {$item['name_or_actor']} | 原因: {$item['reason']}";
                $exportLines[] = $item['magnet'];
            }
            file_put_contents($exportFile, implode("\n", $exportLines) . "\n");
            $this->newLine();
            $this->info("💾 已将未匹配的磁力链接全部导出至: [{$exportFile}] (共 " . count($unmatchedItems) . " 条)");
        } else {
            $this->newLine();
            $this->info("🎉 完美！所有 " . count($lines) . " 条磁力链接已 100% 全部成功匹配，无任何未匹配项！");
        }

        $this->info("======================================================");
        $this->info("🎉 脚本执行完毕！");

        return self::SUCCESS;
    }

    /**
     * 为视频构建加速检索索引
     */
    protected function buildVideoIndex($videos): array
    {
        $indexed = [];

        foreach ($videos as $video) {
            $releaseDate = $video->release_at ? substr((string) $video->release_at, 0, 10) : null;
            $timestamp = $releaseDate ? strtotime($releaseDate) : 0;

            // 提取所有关联演员的名字和 slug
            $actorNames = [];
            $actorNormalized = [];
            foreach ($video->actors as $actor) {
                $actorNames[] = $actor->name;
                $actorNormalized[] = $this->normalizeName($actor->name);
                if (!empty($actor->slug)) {
                    $actorNormalized[] = $this->normalizeName(str_replace('-', ' ', $actor->slug));
                }
            }

            // 提取视频的规范化名称、slug 和编码
            $nameNorm = $this->normalizeName($video->name ?? '');
            $slugNorm = $this->normalizeName(str_replace('-', ' ', $video->slug ?? ''));
            $videoCodeNorm = $this->normalizeName($video->video_code ?? '');
            $codeNormalized = $this->normalizeName(($video->video_code ?? '') . ' ' . ($video->name ?? '') . ' ' . ($video->slug ?? ''));

            $indexed[] = [
                'model'            => $video,
                'release_date'     => $releaseDate,
                'timestamp'        => $timestamp,
                'actor_names'      => array_unique(array_filter($actorNames)),
                'actor_normalized' => array_unique(array_filter($actorNormalized)),
                'name_norm'        => $nameNorm,
                'slug_norm'        => $slugNorm,
                'code_norm'        => $videoCodeNorm,
                'code_normalized'  => $codeNormalized,
            ];
        }

        return $indexed;
    }

    /**
     * 解析单个 magnet 链接行
     */
    protected function parseMagnetLine(string $line): ?array
    {
        // 1. 提取 hash (BTIH)
        if (!preg_match('/xt=urn:btih:([a-zA-Z0-9]+)/i', $line, $hashMatch)) {
            return null;
        }
        $hash = strtolower($hashMatch[1]);

        // 2. 提取 dn (Display Name)
        if (!preg_match('/dn=([^&]+)/i', $line, $dnMatch)) {
            return null;
        }
        $dn = urldecode($dnMatch[1]);

        // 3. 正则解构 BlackedRaw 命名规范:
        // 例: BlackedRaw.22.09.26.Ella.Reese.XXX.1080p.MP4-NBQ
        // 例: BlackedRaw.17.10.31.Penny.Barber.And.Armani.Black.XXX.SD.MP4-KLEENEX
        // 例: BlackedRaw.20.05.15.BBC.Beginners.Compilation.XXX.1080p.MP4-KTR
        // 例: BlackedRaw.22.06.27.High.Gear.XXX.1080p.MP4-NBQ
        if (!preg_match('/^([a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\.(.*?)\.XXX\.(.*?)$/i', $dn, $parts)) {
            return null;
        }

        $channelPrefix = $parts[1];
        $year = '20' . $parts[2];
        $month = $parts[3];
        $day = $parts[4];
        $dateStr = "{$year}-{$month}-{$day}";
        $rawSection = $parts[5];
        $tail = $parts[6];

        // 4. 清晰度与排序权重判断
        $resolution = null;
        $sortOrder = 0;
        if (preg_match('/2160p|4k|uhd/i', $tail)) {
            $resolution = '4K';
            $sortOrder = 10;
        } elseif (preg_match('/1080p/i', $tail)) {
            $resolution = '1080P';
            $sortOrder = 5;
        } elseif (preg_match('/720p/i', $tail)) {
            $resolution = '720P';
            $sortOrder = 3;
        } elseif (preg_match('/sd|480p|540p/i', $tail)) {
            $resolution = 'SD';
            $sortOrder = 1;
        }

        // 5. 演员名称提取 (处理单人与 .And. 多人)
        $cleanActorStr = preg_replace('/\.and\./i', ' & ', $rawSection);
        $cleanActorStr = str_replace('.', ' ', $cleanActorStr);
        $rawActorParts = preg_split('/[&,]|(?:\s+and\s+)/i', $cleanActorStr);
        $actors = array_filter(array_map('trim', $rawActorParts));

        $actorsNormalized = [];
        foreach ($actors as $actor) {
            $actorsNormalized[] = $this->normalizeName($actor);
        }

        return [
            'hash'               => $hash,
            'dn'                 => $dn,
            'channel'            => $channelPrefix,
            'date'               => $dateStr,
            'timestamp'          => strtotime($dateStr),
            'raw_section'        => $rawSection,
            'section_normalized' => $this->normalizeName(str_replace('.', ' ', $rawSection)),
            'actors'             => $actors,
            'actor_normalized'   => $actorsNormalized,
            'resolution'         => $resolution,
            'sort_order'         => $sortOrder,
        ];
    }

    /**
     * 多层级分步打分匹配算法：
     * 第一优先级：精准匹配当天 (Exact Date Match, daysDiff === 0)
     * 第二优先级：前后 1 天容错时区纠偏 (Offset Date Match, daysDiff <= dateWindow)
     * 第三优先级：强特征跨日期宽限兜底 (Feature Fallback Match, 片名/合集全中或演员全中)
     */
    protected function findBestMatchingVideo(array $parsed, array $indexedVideos, int $maxAllowedWindow): array
    {
        $torrentDate = $parsed['date'];
        $torrentTimestamp = $parsed['timestamp'];
        $torrentActorsNorm = $parsed['actor_normalized'];
        $torrentSectionNorm = $parsed['section_normalized'] ?? '';

        // =========================================================
        // 第一优先级：精准匹配发布日期当天 (daysDiff === 0)
        // 用户核心诉求：优先精准匹配日期，当天匹配到时绝不考虑前后推移
        // =========================================================
        $exactDayCandidates = [];

        foreach ($indexedVideos as $item) {
            $videoTimestamp = $this->resolveVideoTimestamp($item);
            if (!$videoTimestamp) {
                continue;
            }

            $daysDiff = (int) round(abs($torrentTimestamp - $videoTimestamp) / 86400);
            if ($daysDiff !== 0) {
                continue;
            }

            $actorMatchCount = $this->countActorMatches($torrentActorsNorm, $torrentSectionNorm, $item);
            $titleMatched = $this->isTitleMatched($torrentSectionNorm, $item);

            // 当天得分：基础分 50 + 特征加分
            $score = 50;
            if ($actorMatchCount > 0 && $titleMatched) {
                $score += 110; // 演员和片名双重命中
            } elseif ($actorMatchCount > 0) {
                $score += ($actorMatchCount === count($torrentActorsNorm)) ? 100 : 85;
            } elseif ($titleMatched) {
                $score += 95; // 片名命中
            } else {
                $score += 60; // 当天唯一视频兜底保底分
            }

            $exactDayCandidates[] = [
                'video'     => $item['model'],
                'score'     => $score,
                'days_diff' => 0,
            ];
        }

        if (!empty($exactDayCandidates)) {
            usort($exactDayCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);
            $top = $exactDayCandidates[0];

            if (count($exactDayCandidates) > 1 && $exactDayCandidates[1]['score'] === $top['score']) {
                if ($exactDayCandidates[1]['video']->id !== $top['video']->id) {
                    return [
                        'video'     => null,
                        'days_diff' => null,
                        'reason'    => '当天存在多部不同视频且同分模糊歧义',
                    ];
                }
            }

            return [
                'video'     => $top['video'],
                'days_diff' => 0,
                'reason'    => null,
            ];
        }

        // =========================================================
        // 第二优先级：前后 1 天容错时区纠偏匹配 (1 <= daysDiff <= maxAllowedWindow)
        // 仅在当天完全找不到任何视频时，才放宽考虑时区前后一天
        // =========================================================
        $offsetCandidates = [];
        $allowedWindow = max(1, $maxAllowedWindow);

        foreach ($indexedVideos as $item) {
            $videoTimestamp = $this->resolveVideoTimestamp($item);
            if (!$videoTimestamp) {
                continue;
            }

            $daysDiff = (int) round(abs($torrentTimestamp - $videoTimestamp) / 86400);
            if ($daysDiff < 1 || $daysDiff > $allowedWindow) {
                continue;
            }

            $actorMatchCount = $this->countActorMatches($torrentActorsNorm, $torrentSectionNorm, $item);
            $titleMatched = $this->isTitleMatched($torrentSectionNorm, $item);

            // 前后一天容错必须满足：至少命中演员或片名
            if ($actorMatchCount === 0 && !$titleMatched) {
                continue;
            }

            $score = ($daysDiff === 1) ? 35 : 20;
            if ($actorMatchCount > 0 && $titleMatched) {
                $score += 110;
            } elseif ($actorMatchCount > 0) {
                $score += ($actorMatchCount === count($torrentActorsNorm)) ? 100 : 85;
            } else {
                $score += 95;
            }

            $offsetCandidates[] = [
                'video'     => $item['model'],
                'score'     => $score,
                'days_diff' => $daysDiff,
            ];
        }

        if (!empty($offsetCandidates)) {
            usort($offsetCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);
            $top = $offsetCandidates[0];

            if (count($offsetCandidates) > 1 && $offsetCandidates[1]['score'] === $top['score']) {
                if ($offsetCandidates[1]['video']->id !== $top['video']->id) {
                    return [
                        'video'     => null,
                        'days_diff' => null,
                        'reason'    => '前后容错窗口内存在多部同分歧义视频',
                    ];
                }
            }

            return [
                'video'     => $top['video'],
                'days_diff' => $top['days_diff'],
                'reason'    => null,
            ];
        }

        // =========================================================
        // 第三优先级：强特征跨日期宽限兜底 (Feature Fallback Match)
        // 针对原片片名全匹配(如 High Gear, BBC Beginners Compilation) 或演员全匹配，
        // 允许放宽到 7 天窗口寻找唯一确切对应的视频
        // =========================================================
        $featureCandidates = [];

        foreach ($indexedVideos as $item) {
            $videoTimestamp = $this->resolveVideoTimestamp($item);
            $daysDiff = $videoTimestamp ? (int) round(abs($torrentTimestamp - $videoTimestamp) / 86400) : 999;

            $actorMatchCount = $this->countActorMatches($torrentActorsNorm, $torrentSectionNorm, $item);
            $titleMatched = $this->isTitleMatched($torrentSectionNorm, $item);

            // 强特征要求：原片片名全匹配，或者演员全匹配
            if (!$titleMatched && ($actorMatchCount === 0 || $actorMatchCount < count($torrentActorsNorm))) {
                continue;
            }

            // 宽限最大 7 天
            if ($daysDiff > 7 && $videoTimestamp > 0) {
                continue;
            }

            $score = $titleMatched ? 95 : 90;
            $score -= min(40, $daysDiff * 4); // 距离越远适度扣分

            $featureCandidates[] = [
                'video'     => $item['model'],
                'score'     => $score,
                'days_diff' => ($daysDiff === 999) ? 0 : $daysDiff,
            ];
        }

        if (!empty($featureCandidates)) {
            usort($featureCandidates, fn ($a, $b) => $b['score'] <=> $a['score']);
            $top = $featureCandidates[0];

            if (count($featureCandidates) > 1 && $featureCandidates[1]['score'] === $top['score']) {
                if ($featureCandidates[1]['video']->id !== $top['video']->id) {
                    return [
                        'video'     => null,
                        'days_diff' => null,
                        'reason'    => '特征匹配存在多部同名歧义视频',
                    ];
                }
            }

            return [
                'video'     => $top['video'],
                'days_diff' => $top['days_diff'],
                'reason'    => null,
            ];
        }

        // =========================================================
        // 未匹配原因深度诊断 (为用户提供清晰的原因提示)
        // =========================================================
        $diagReason = $this->diagnoseFailureReason($parsed, $indexedVideos);

        return [
            'video'     => null,
            'days_diff' => null,
            'reason'    => $diagReason,
        ];
    }

    /**
     * 补救解析视频时间戳
     */
    protected function resolveVideoTimestamp(array $item): int
    {
        if (!empty($item['timestamp'])) {
            return $item['timestamp'];
        }

        $code = $item['model']->video_code ?? '';
        if (preg_match('/\.(\d{2})\.(\d{2})\.(\d{2})\./', $code, $m)) {
            return strtotime("20{$m[1]}-{$m[2]}-{$m[3]}");
        }

        $slug = $item['model']->slug ?? '';
        if (preg_match('/(?:^|-)(20\d{2}|\d{2})-(\d{2})-(\d{2})(?:-|$)/', $slug, $sm)) {
            $y = strlen($sm[1]) === 4 ? $sm[1] : '20' . $sm[1];
            return strtotime("{$y}-{$sm[2]}-{$sm[3]}");
        }

        return 0;
    }

    /**
     * 计算女演员匹配数
     */
    protected function countActorMatches(array $torrentActorsNorm, string $torrentSectionNorm, array $item): int
    {
        $actorMatchCount = 0;
        $videoActorsNorm = $item['actor_normalized'];
        $videoCodeNorm = $item['code_normalized'];

        foreach ($torrentActorsNorm as $tActorNorm) {
            if (empty($tActorNorm)) {
                continue;
            }

            $isMatched = false;

            // 1. 精准全等
            foreach ($videoActorsNorm as $vActorNorm) {
                if ($tActorNorm === $vActorNorm) {
                    $isMatched = true;
                    break;
                }

                // 2. 拼写近义
                $lev = levenshtein($tActorNorm, $vActorNorm);
                if ($lev <= 2 && strlen($tActorNorm) >= 6) {
                    $isMatched = true;
                    break;
                }

                // 3. 包含关系
                if (str_contains($vActorNorm, $tActorNorm) || str_contains($tActorNorm, $vActorNorm)) {
                    $isMatched = true;
                    break;
                }
            }

            // 4. 比对 video_code / 标题
            if (!$isMatched && str_contains($videoCodeNorm, $tActorNorm)) {
                $isMatched = true;
            }

            if ($isMatched) {
                $actorMatchCount++;
            }
        }

        // 5. 反向扫描：磁力段是否包含了视频关联女演员 (如 Charlotte.Sins.Warm.Up 包含 Charlotte Sins)
        if ($actorMatchCount === 0 && !empty($torrentSectionNorm)) {
            foreach ($videoActorsNorm as $vActorNorm) {
                if (strlen($vActorNorm) >= 4 && str_contains($torrentSectionNorm, $vActorNorm)) {
                    $actorMatchCount++;
                    break;
                }
            }
        }

        return $actorMatchCount;
    }

    /**
     * 比对原片片名 / Slug / 编码
     */
    protected function isTitleMatched(string $torrentSectionNorm, array $item): bool
    {
        if (empty($torrentSectionNorm)) {
            return false;
        }

        $nameNorm = $item['name_norm'] ?? '';
        $slugNorm = $item['slug_norm'] ?? '';
        $codeNorm = $item['code_normalized'] ?? '';

        // 1. 原片名全等或包含
        if (!empty($nameNorm)) {
            if ($nameNorm === $torrentSectionNorm
                || (strlen($nameNorm) >= 4 && str_contains($torrentSectionNorm, $nameNorm))
                || (strlen($torrentSectionNorm) >= 4 && str_contains($nameNorm, $torrentSectionNorm))) {
                return true;
            }
        }

        // 2. Slug 全等或包含
        if (!empty($slugNorm)) {
            if ($slugNorm === $torrentSectionNorm
                || (strlen($slugNorm) >= 4 && str_contains($torrentSectionNorm, $slugNorm))
                || (strlen($torrentSectionNorm) >= 4 && str_contains($slugNorm, $torrentSectionNorm))) {
                return true;
            }
        }

        // 3. 完整编码包含
        if (!empty($codeNorm) && str_contains($codeNorm, $torrentSectionNorm)) {
            return true;
        }

        return false;
    }

    /**
     * 诊断未匹配原因
     */
    protected function diagnoseFailureReason(array $parsed, array $indexedVideos): string
    {
        $torrentDate = $parsed['date'];
        $torrentTimestamp = $parsed['timestamp'];
        $sectionNorm = $parsed['section_normalized'] ?? '';

        // 检查库中是否有当天的视频
        $sameDayVideos = [];
        $closestDiff = 999;
        $closestVideo = null;

        foreach ($indexedVideos as $item) {
            $ts = $this->resolveVideoTimestamp($item);
            if (!$ts) {
                continue;
            }

            $diff = (int) round(abs($torrentTimestamp - $ts) / 86400);
            if ($diff === 0) {
                $sameDayVideos[] = $item['model'];
            }

            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closestVideo = $item;
            }
        }

        if (!empty($sameDayVideos)) {
            $first = $sameDayVideos[0];
            return "当天存在视频 [ID: {$first->id}, 标题: {$first->name}] 但演员与片名不匹配";
        }

        if ($closestVideo && $closestDiff <= 7) {
            $v = $closestVideo['model'];
            $vDate = substr((string) $v->release_at, 0, 10);
            return "当天无视频 (最相近为 {$vDate}, 相差 {$closestDiff} 天, 标题: {$v->name})";
        }

        return "库中未找到前后 7 天内的相关视频或特征不匹配";
    }

    /**
     * 标准化名称字符串 (转小写，去除所有非字母数字)
     */
    protected function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }
}

