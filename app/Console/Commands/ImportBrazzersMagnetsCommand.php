<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Models\VideoDownload;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportBrazzersMagnetsCommand extends Command
{
    /**
     * 命令签名与选项
     *
     * @var string
     */
    protected $signature = 'video:import-brazzers-magnets
                            {file=torrent.txt : 包含磁力链接的文件路径}
                            {--date-window=1 : 允许的发布日期前后浮动天数(默认前后1天)}
                            {--dry-run : 演练模式：仅匹配并输出统计报告，不实际写入数据库}
                            {--force : 强制更新已存在的下载记录}
                            {--export-unmatched= : 将未匹配的磁力链接导出到指定文件(默认自动保存到 unmatched_magnets.txt)}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '根据女演员、原片片名与发布日期，将 Brazzers 及各子站(BrazzersExxtra, PornstarsLikeItBig, DirtyMasseur等)磁力链接匹配并导入到 video_downloads 表中';

    /**
     * 执行命令
     */
    public function handle(): int
    {
        $filePath = $this->argument('file');
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

        // 1. 查找目标片商 Brazzers (data_crawl_type = 1)
        $channel = Channel::where('slug', 'brazzers')
            ->orWhereRaw('LOWER(name) = ?', ['brazzers'])
            ->orWhere('name', 'ILIKE', '%brazzers%')
            ->first();

        if (!$channel) {
            $this->error("❌ 数据库中未找到片商: [Brazzers]");
            return self::FAILURE;
        }

        $this->info("🎬 目标片商匹配成功: [{$channel->name}] (ID: {$channel->id}, Slug: {$channel->slug})");

        // 2. 预加载 Brazzers 下的所有视频与演员数据
        $this->info("⏳ 正在预加载片商 [{$channel->name}] 下的所有视频与演员数据...");
        $videos = Video::where('channel_id', $channel->id)
            ->with('actors:id,name,slug')
            ->select(['id', 'channel_id', 'name', 'slug', 'video_code', 'release_at', 'has_downloads'])
            ->get();

        if ($videos->isEmpty()) {
            $this->error("❌ 片商 [{$channel->name}] 下暂无任何视频记录，请先同步片商视频元数据！");
            return self::FAILURE;
        }

        // 统计视频数据分布情况
        $before2024Count = Video::where('channel_id', $channel->id)
            ->where('release_at', '<', '2024-01-01 00:00:00')
            ->count();

        $after2024Count = Video::where('channel_id', $channel->id)
            ->where('release_at', '>=', '2024-01-01 00:00:00')
            ->count();

        $nullDateCount = Video::where('channel_id', $channel->id)
            ->whereNull('release_at')
            ->count();

        $this->info("✅ 成功加载 {$videos->count()} 部视频 (其中 2024 年以前: {$before2024Count} 部, 2024 年及以后: {$after2024Count} 部, 无日期: {$nullDateCount} 部)，构建内存加速索引...");

        // 3. 构建内存索引加速比对
        $indexData = $this->buildVideoIndex($videos);
        $indexedVideos = $indexData['list'];
        $videosByDate = $indexData['by_date'];

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

        $siteStats = [];
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
                    'site'          => '-',
                    'dn'            => '(无法解析磁力链接格式)',
                    'date'          => '-',
                    'middle'        => '-',
                    'magnet'        => $line,
                    'reason'        => '磁力链接或 dn 命名格式不符合规范',
                ];
                continue;
            }

            $site = $parsed['site'] ?: 'Unknown';
            if (!isset($siteStats[$site])) {
                $siteStats[$site] = ['total' => 0, 'matched' => 0];
            }
            $siteStats[$site]['total']++;

            // 在 Brazzers 视频库中查找最匹配的视频
            $matchResult = $this->findBestMatchingVideo($parsed, $videosByDate, $indexedVideos, $dateWindow);

            if (!$matchResult['video']) {
                if ($matchResult['reason'] === 'ambiguous') {
                    $stats['ambiguous']++;
                } else {
                    $stats['unmatched']++;
                }

                $unmatchedItems[] = [
                    'line'          => $lineIndex + 1,
                    'site'          => $site,
                    'dn'            => $parsed['dn'],
                    'date'          => $parsed['date'],
                    'middle'        => $parsed['raw_middle'],
                    'magnet'        => $line,
                    'reason'        => $matchResult['reason'],
                ];
                continue;
            }

            $matchedVideo = $matchResult['video'];
            $daysDiff = $matchResult['days_diff'];

            $stats['matched']++;
            $siteStats[$site]['matched']++;

            if ($daysDiff === 0) {
                $stats['exact_date']++;
            } elseif ($daysDiff === 1) {
                $stats['offset_1_day']++;
            } else {
                $stats['offset_multi']++;
            }

            if (count($matchedSamples) < 5) {
                $matchedSamples[] = [
                    'site'       => $site,
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
                    'description'      => null, // 绝不写入无参考价值的占位信息
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
        $this->info("=================== Brazzers 磁力匹配与导入汇总 ===================");
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

        // 各子站系列明细统计
        if (!empty($siteStats)) {
            $this->info("\n📊 各子站系列 (Sites) 匹配明细 (Top 20):");
            uasort($siteStats, fn ($a, $b) => $b['total'] <=> $a['total']);
            $siteRows = [];
            foreach (array_slice($siteStats, 0, 20, true) as $sName => $sInfo) {
                $rate = $sInfo['total'] > 0 ? round(($sInfo['matched'] / $sInfo['total']) * 100, 1) . '%' : '0%';
                $siteRows[] = [
                    $sName,
                    $sInfo['total'],
                    $sInfo['matched'],
                    $sInfo['total'] - $sInfo['matched'],
                    $rate,
                ];
            }
            $this->table(['子站系列 (Site)', '磁力总数', '成功匹配', '未匹配', '匹配率'], $siteRows);
        }

        if (!empty($matchedSamples)) {
            $this->info("\n🎯 典型成功匹配示例 (前 5 组):");
            $this->table(['子站', '磁力文件名 (dn)', '匹配到的视频', '磁力日期', '视频日期', '相差天数', '清晰度'], $matchedSamples);
        }

        // 7. 详细输出所有未匹配的数据
        if (!empty($unmatchedItems)) {
            $this->newLine();
            $this->warn("⚠️  共检测到 " . count($unmatchedItems) . " 条未匹配的磁力链接，明细如下：");

            $displayItems = array_slice($unmatchedItems, 0, 50);
            $tableRows = array_map(function ($item) {
                return [
                    $item['line'],
                    $item['site'],
                    $item['dn'],
                    $item['date'],
                    $item['middle'],
                    $item['reason'],
                ];
            }, $displayItems);

            $this->table(['行号', '子站', '磁力文件名 (dn)', '磁力日期', '演员与原片名段落', '未匹配原因'], $tableRows);
            if (count($unmatchedItems) > 50) {
                $this->warn("... 其余 " . (count($unmatchedItems) - 50) . " 条未匹配条目请查阅导出的文件。");
            }

            // 自动保存到文件
            $exportFile = $this->option('export-unmatched') ?: 'unmatched_magnets.txt';
            $exportLines = [
                "# Brazzers 未匹配磁力链接记录",
                "# 生成时间: " . date('Y-m-d H:i:s'),
                "# 总未匹配数: " . count($unmatchedItems),
                "# ----------------------------------------------------",
            ];
            foreach ($unmatchedItems as $item) {
                $exportLines[] = "# [行号: {$item['line']}] 子站: {$item['site']} | 日期: {$item['date']} | 识别内容: {$item['middle']} | 原因: {$item['reason']}";
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
        $byDate = [];

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

            $item = [
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

            $indexed[] = $item;

            if ($releaseDate) {
                $byDate[$releaseDate][] = $item;
            }

            // 兼容 video_code 中的备用日期 (例如 BrazzersExxtra.24.09.08...)
            if (!empty($video->video_code) && preg_match('/\.(\d{2})\.(\d{2})\.(\d{2})\./', $video->video_code, $cm)) {
                $cDate = "20{$cm[1]}-{$cm[2]}-{$cm[3]}";
                if ($cDate !== $releaseDate) {
                    $byDate[$cDate][] = $item;
                }
            }
        }

        return [
            'list'    => $indexed,
            'by_date' => $byDate,
        ];
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

        // 3. 正则解构 Brazzers 及各子站系列 (BrazzersExxtra, PornstarsLikeItBig, DirtyMasseur, HotAndMean, TeensLikeItBig 等)
        // 变体 1: Site.YY.MM.DD.Middle.XXX.Resolution.Ext (最标准)
        // 变体 2: Site.YY.MM.DD.Middle.Resolution.XXX...
        // 变体 3: Site.YY.MM.DD.Middle.Resolution.Ext... (无 XXX 变体)
        // 变体 4: Site.YY.MM.DD.Middle.Ext (直接跟扩展名)
        $site = null;
        $d1 = null;
        $d2 = null;
        $d3 = null;
        $middle = null;
        $tail = '';

        if (preg_match('/^([a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\.(.*?)\.XXX\.(.*?)$/i', $dn, $parts)) {
            $site = $parts[1];
            $d1 = $parts[2];
            $d2 = $parts[3];
            $d3 = $parts[4];
            $middle = $parts[5];
            $tail = $parts[6];
        } elseif (preg_match('/^([a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\.(.*?)\.(2160p|1080p|720p|sd|4k|uhd|\d+p)\.XXX(.*)$/i', $dn, $parts)) {
            $site = $parts[1];
            $d1 = $parts[2];
            $d2 = $parts[3];
            $d3 = $parts[4];
            $middle = $parts[5];
            $tail = $parts[6] . $parts[7];
        } elseif (preg_match('/^([a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\.(.*?)\.(2160p|1080p|720p|sd|4k|uhd|\d+p)\.(mp4|mkv|avi|wmv)(.*)$/i', $dn, $parts)) {
            $site = $parts[1];
            $d1 = $parts[2];
            $d2 = $parts[3];
            $d3 = $parts[4];
            $middle = $parts[5];
            $tail = $parts[6] . ' ' . $parts[7];
        } elseif (preg_match('/^([a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\.(.*?)\.(mp4|mkv|avi|wmv)$/i', $dn, $parts)) {
            $site = $parts[1];
            $d1 = $parts[2];
            $d2 = $parts[3];
            $d3 = $parts[4];
            $middle = $parts[5];
            $tail = $parts[6];
        } else {
            return null;
        }

        // 智能解析日期：YY.MM.DD 与 MM.DD.YY 自适应
        if ((int) $d2 > 12) {
            $year = '20' . $d3;
            $month = $d1;
            $day = $d2;
        } else {
            $year = '20' . $d1;
            $month = $d2;
            $day = $d3;
        }
        $dateStr = "{$year}-{$month}-{$day}";

        // 清晰度与排序权重
        $resolution = null;
        $sortOrder = 0;
        $tailAndDn = $tail . ' ' . $dn;
        if (preg_match('/2160p|4k|uhd/i', $tailAndDn)) {
            $resolution = '4K';
            $sortOrder = 10;
        } elseif (preg_match('/1080p/i', $tailAndDn)) {
            $resolution = '1080P';
            $sortOrder = 5;
        } elseif (preg_match('/720p/i', $tailAndDn)) {
            $resolution = '720P';
            $sortOrder = 3;
        } elseif (preg_match('/sd|480p|540p/i', $tailAndDn)) {
            $resolution = 'SD';
            $sortOrder = 1;
        }

        // 规范化 middle 字符串 (如 Anissa.Kate.And.Beth.Bennett.Exes.Compete.Over.New.Pussy)
        $middleNormalized = $this->normalizeName(str_replace('.', ' ', $middle));

        return [
            'hash'              => $hash,
            'dn'                => $dn,
            'site'              => $site,
            'date'              => $dateStr,
            'timestamp'         => strtotime($dateStr),
            'raw_middle'        => $middle,
            'middle_normalized' => $middleNormalized,
            'resolution'        => $resolution,
            'sort_order'        => $sortOrder,
        ];
    }

    /**
     * 多层级分步打分匹配算法：
     * 第一优先级：精准匹配当天 (daysDiff === 0)
     * 第二优先级：前后 1 天容错时区纠偏 (1 <= daysDiff <= dateWindow)
     * 第三优先级：强特征宽限兜底 (片名强命中 + 演员命中)
     */
    protected function findBestMatchingVideo(array $parsed, array $videosByDate, array $indexedVideos, int $maxAllowedWindow): array
    {
        $torrentDate = $parsed['date'];
        $torrentTimestamp = $parsed['timestamp'];
        $middleNorm = $parsed['middle_normalized'];

        // =========================================================
        // 第一优先级：精准匹配发布日期当天 (daysDiff === 0)
        // =========================================================
        $sameDayItems = $videosByDate[$torrentDate] ?? [];

        if (!empty($sameDayItems)) {
            $exactDayCandidates = [];

            foreach ($sameDayItems as $item) {
                $score = $this->calculateMatchScore($parsed, $item, 0);
                if ($score > 0) {
                    $exactDayCandidates[] = [
                        'video'     => $item['model'],
                        'score'     => $score,
                        'days_diff' => 0,
                    ];
                }
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
        }

        // =========================================================
        // 第二优先级：前后 1 天容错时区纠偏匹配 (1 <= daysDiff <= maxAllowedWindow)
        // =========================================================
        $allowedWindow = max(1, $maxAllowedWindow);
        $offsetCandidates = [];

        for ($diff = 1; $diff <= $allowedWindow; $diff++) {
            $prevDate = date('Y-m-d', $torrentTimestamp - $diff * 86400);
            $nextDate = date('Y-m-d', $torrentTimestamp + $diff * 86400);

            $dateCandidates = array_merge(
                $videosByDate[$prevDate] ?? [],
                $videosByDate[$nextDate] ?? []
            );

            foreach ($dateCandidates as $item) {
                $score = $this->calculateMatchScore($parsed, $item, $diff);
                // 前后一天容错必须达到较高特征门槛 (至少命中标题或有效演员)
                if ($score >= 120) {
                    $offsetCandidates[] = [
                        'video'     => $item['model'],
                        'score'     => $score,
                        'days_diff' => $diff,
                    ];
                }
            }
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
        // 第三优先级：强特征跨日期宽限兜底 (Feature Fallback Match, 片名完整命中)
        // =========================================================
        $featureCandidates = [];

        foreach ($indexedVideos as $item) {
            $videoTimestamp = $item['timestamp'];
            $daysDiff = $videoTimestamp ? (int) round(abs($torrentTimestamp - $videoTimestamp) / 86400) : 999;

            // 宽限最长 7 天
            if ($daysDiff > 7 && $videoTimestamp > 0) {
                continue;
            }

            $score = $this->calculateMatchScore($parsed, $item, $daysDiff);

            // 强特征要求：原片片名完整匹配，且得分达到 150 以上
            if ($score >= 150) {
                $featureCandidates[] = [
                    'video'     => $item['model'],
                    'score'     => $score,
                    'days_diff' => ($daysDiff === 999) ? 0 : $daysDiff,
                ];
            }
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

        // 未匹配原因深度诊断
        $diagReason = $this->diagnoseFailureReason($parsed, $videosByDate, $indexedVideos);

        return [
            'video'     => null,
            'days_diff' => null,
            'reason'    => $diagReason,
        ];
    }

    /**
     * 计算单条视频匹配得分
     */
    protected function calculateMatchScore(array $parsed, array $item, int $daysDiff): int
    {
        $middleNorm = $parsed['middle_normalized'];
        $titleNorm = $item['name_norm'];
        $slugNorm = $item['slug_norm'];
        $codeNorm = $item['code_norm'];

        $score = ($daysDiff === 0) ? 60 : max(10, 35 - $daysDiff * 5);

        // 1. 原片片名命中判断
        $titleMatched = false;
        if (!empty($titleNorm) && strlen($titleNorm) >= 4) {
            if ($middleNorm === $titleNorm) {
                $titleMatched = true;
                $score += 120; // 纯标题完全一致
            } elseif (str_contains($middleNorm, $titleNorm) || str_contains($titleNorm, $middleNorm)) {
                $titleMatched = true;
                $score += 100; // 标题作为子串包含
            }
        } elseif (!empty($slugNorm) && strlen($slugNorm) >= 4 && str_contains($middleNorm, $slugNorm)) {
            $titleMatched = true;
            $score += 90;
        }

        // 2. 演员命中数判断
        $matchedActorCount = 0;
        $totalActors = count($item['actor_normalized']);
        foreach ($item['actor_normalized'] as $vActorNorm) {
            if (strlen($vActorNorm) >= 4 && str_contains($middleNorm, $vActorNorm)) {
                $matchedActorCount++;
            }
        }

        if ($matchedActorCount > 0) {
            $score += ($matchedActorCount === $totalActors && $totalActors > 0) ? 90 : ($matchedActorCount * 40);
        }

        // 3. video_code 辅助比对 (通常包含 Collection.YY.MM.DD.Actors.Title)
        if (!empty($codeNorm) && (str_contains($codeNorm, $middleNorm) || str_contains($middleNorm, $codeNorm))) {
            $score += 110;
        }

        // 4. 双重命中（原片片名 + 演员同时命中）协同加分
        if ($titleMatched && $matchedActorCount > 0) {
            $score += 80;
        }

        // 基础门槛：至少需要片名命中，或者至少命中演员与当天日期
        if (!$titleMatched && $matchedActorCount === 0 && empty($codeNorm)) {
            return 0;
        }

        return $score;
    }

    /**
     * 诊断未匹配原因
     */
    protected function diagnoseFailureReason(array $parsed, array $videosByDate, array $indexedVideos): string
    {
        $torrentDate = $parsed['date'];
        $torrentTimestamp = $parsed['timestamp'];

        // 检查库中是否有当天的视频
        $sameDayItems = $videosByDate[$torrentDate] ?? [];
        if (!empty($sameDayItems)) {
            $first = $sameDayItems[0]['model'];
            return "当天存在视频 [ID: {$first->id}, 标题: {$first->name}] 但片名与演员不匹配";
        }

        // 检查前后 7 天内的最近视频
        $closestDiff = 999;
        $closestVideo = null;
        foreach ($indexedVideos as $item) {
            $ts = $item['timestamp'];
            if (!$ts) {
                continue;
            }

            $diff = (int) round(abs($torrentTimestamp - $ts) / 86400);
            if ($diff < $closestDiff) {
                $closestDiff = $diff;
                $closestVideo = $item;
            }
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

