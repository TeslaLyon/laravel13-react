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
                            {--force : 强制更新已存在的下载记录}';

    /**
     * 命令描述
     *
     * @var string
     */
    protected $description = '根据女演员名称与发布日期（容许前后一天时区差），将 BlackedRaw 磁力链接匹配并导入到 video_downloads 表中';

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

        $this->info("✅ 成功加载 {$videos->count()} 部视频，准备开始逐条匹配...");

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

        $unmatchedSamples = [];
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

                if (count($unmatchedSamples) < 15) {
                    $unmatchedSamples[] = [
                        'line'    => $lineIndex + 1,
                        'dn'      => $parsed['dn'],
                        'date'    => $parsed['date'],
                        'actors'  => implode(', ', $parsed['actors']),
                        'reason'  => $matchResult['reason'],
                    ];
                }
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

            // 5. 写入 video_downloads 表
            if (!$isDryRun) {
                $existing = VideoDownload::where('hash', $parsed['hash'])
                    ->where('video_id', $matchedVideo->id)
                    ->first();

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

        if (!empty($unmatchedSamples)) {
            $this->warn("\n⚠️ 未匹配样本参考 (前 " . count($unmatchedSamples) . " 组):");
            $this->table(['行号', '磁力文件名 (dn)', '磁力日期', '演员', '未匹配原因'], $unmatchedSamples);
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

            // 辅助：从 video_code 和 name 中抽取名字
            $codeNormalized = $this->normalizeName($video->video_code . ' ' . $video->name . ' ' . $video->slug);

            $indexed[] = [
                'model'            => $video,
                'release_date'     => $releaseDate,
                'timestamp'        => $timestamp,
                'actor_names'      => array_unique($actorNames),
                'actor_normalized' => array_unique($actorNormalized),
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
        if (!preg_match('/^([a-zA-Z0-9]+)\.(\d{2})\.(\d{2})\.(\d{2})\.(.*?)\.XXX\.(.*?)$/i', $dn, $parts)) {
            return null;
        }

        $channelPrefix = $parts[1];
        $year = '20' . $parts[2];
        $month = $parts[3];
        $day = $parts[4];
        $dateStr = "{$year}-{$month}-{$day}";
        $actorSection = $parts[5];
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
        $cleanActorStr = preg_replace('/\.and\./i', ' & ', $actorSection);
        $cleanActorStr = str_replace('.', ' ', $cleanActorStr);
        $rawActorParts = preg_split('/[&,]|(?:\s+and\s+)/i', $cleanActorStr);
        $actors = array_filter(array_map('trim', $rawActorParts));

        $actorsNormalized = [];
        foreach ($actors as $actor) {
            $actorsNormalized[] = $this->normalizeName($actor);
        }

        return [
            'hash'             => $hash,
            'dn'               => $dn,
            'channel'          => $channelPrefix,
            'date'             => $dateStr,
            'timestamp'        => strtotime($dateStr),
            'actors'           => $actors,
            'actor_normalized' => $actorsNormalized,
            'resolution'       => $resolution,
            'sort_order'       => $sortOrder,
        ];
    }

    /**
     * 核心打分匹配算法：演员为主 + 容许日期前后 1~2 天时区差
     */
    protected function findBestMatchingVideo(array $parsed, array $indexedVideos, int $maxAllowedWindow): array
    {
        $torrentDate = $parsed['date'];
        $torrentTimestamp = $parsed['timestamp'];
        $torrentActorsNorm = $parsed['actor_normalized'];

        $candidates = [];

        foreach ($indexedVideos as $item) {
            $videoTimestamp = $item['timestamp'];
            if (!$videoTimestamp) {
                // 若视频无发布日期，尝试从 video_code 补救提取
                if (preg_match('/\.(\d{2})\.(\d{2})\.(\d{2})\./', $item['model']->video_code ?? '', $m)) {
                    $videoTimestamp = strtotime("20{$m[1]}-{$m[2]}-{$m[3]}");
                }
            }

            if (!$videoTimestamp) {
                continue;
            }

            // 计算天数差异
            $daysDiff = (int) round(abs($torrentTimestamp - $videoTimestamp) / 86400);

            // 允许的最大浮动天数：用户配置值(默认1天)，最大放宽到 max(2, $maxAllowedWindow) 寻找备选
            $maxDays = max(2, $maxAllowedWindow);
            if ($daysDiff > $maxDays) {
                continue;
            }

            // 演员匹配比对
            $actorMatchCount = 0;
            $videoActorsNorm = $item['actor_normalized'];
            $videoCodeNorm = $item['code_normalized'];

            foreach ($torrentActorsNorm as $tActorNorm) {
                $isMatched = false;

                // 1. 优先精准匹配演员标准名
                foreach ($videoActorsNorm as $vActorNorm) {
                    if ($tActorNorm === $vActorNorm) {
                        $isMatched = true;
                        break;
                    }

                    // 2. 容错拼写相近（如 Lana Rhodes 与 Lana Rhoades，编辑简写等）
                    $lev = levenshtein($tActorNorm, $vActorNorm);
                    if ($lev <= 2 && strlen($tActorNorm) >= 6) {
                        $isMatched = true;
                        break;
                    }

                    // 3. 包含关系 (如名字缩写或艺名全名)
                    if (str_contains($vActorNorm, $tActorNorm) || str_contains($tActorNorm, $vActorNorm)) {
                        $isMatched = true;
                        break;
                    }
                }

                // 4. 若关联演员表未命中，比对 video_code / 标题中的名称文本
                if (!$isMatched && str_contains($videoCodeNorm, $tActorNorm)) {
                    $isMatched = true;
                }

                if ($isMatched) {
                    $actorMatchCount++;
                }
            }

            // 核心准则：必须至少命中一个关键女演员名字
            if ($actorMatchCount === 0) {
                continue;
            }

            // 计算综合打分
            $score = 0;

            // 演员得分 (最高 100 分)
            if ($actorMatchCount === count($torrentActorsNorm)) {
                $score += 100; // 演员全中
            } else {
                $score += 80;  // 部分演员命中
            }

            // 日期得分 (越近分越高，前后一天给极高分)
            if ($daysDiff === 0) {
                $score += 40; // 同一天
            } elseif ($daysDiff === 1) {
                $score += 35; // 前后一天 (时区差)
            } elseif ($daysDiff === 2) {
                $score += 20; // 前后两天
            } else {
                $score += 10;
            }

            $candidates[] = [
                'video'     => $item['model'],
                'score'     => $score,
                'days_diff' => $daysDiff,
            ];
        }

        if (empty($candidates)) {
            return [
                'video'     => null,
                'days_diff' => null,
                'reason'    => '未在前后日期窗口内找到匹配演员的视频',
            ];
        }

        // 按得分倒序排序
        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        $top = $candidates[0];

        // 检查是否存在同分歧义 (排重保护)
        if (count($candidates) > 1 && $candidates[1]['score'] === $top['score']) {
            // 同一天若存在多部不同视频但演员相同，需要更细致甄别
            if ($candidates[1]['video']->id !== $top['video']->id) {
                return [
                    'video'     => null,
                    'days_diff' => null,
                    'reason'    => 'ambiguous',
                ];
            }
        }

        return [
            'video'     => $top['video'],
            'days_diff' => $top['days_diff'],
            'reason'    => null,
        ];
    }

    /**
     * 标准化名称字符串 (转小写，去除所有非字母数字)
     */
    protected function normalizeName(string $name): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($name));
    }
}

