<?php

namespace App\Console\Commands;

use App\Models\Video;
use App\Services\Crawler\XxxclubCrawlerService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncVideoQualitiesCommand extends Command
{
    /**
     * 命令签名与可用选项
     */
    protected $signature = 'video:sync-qualities
                            {--video-id= : 指定单个视频 ID 进行同步}
                            {--channel= : 按片商筛选 (slug 或名称，如 brazzers)}
                            {--limit= : 限制处理视频数量 (默认不限制)}
                            {--force-all : 扫描所有视频记录（默认仅扫描 has_downloads 为 true 或存在下载记录的视频）}
                            {--dry-run : 演练模式：仅检测和输出将要更新的字段，不实际写入数据库}';

    /**
     * 命令描述
     */
    protected $description = '根据已有下载项(video_downloads)，重新检测并同步更新视频的 4K (is_4k/max_quality) 与 VR (is_vr) 标记';

    /**
     * 执行命令
     */
    public function handle(XxxclubCrawlerService $crawlerService): int
    {
        $startTime = microtime(true);
        $isDryRun = (bool) $this->option('dry-run');
        $forceAll = (bool) $this->option('force-all');
        $videoId = $this->option('video-id') ? (int) $this->option('video-id') : null;
        $channel = $this->option('channel');
        $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : null;

        $this->info("================================================================================");
        $this->info("🎬 视频 4K (is_4k/max_quality) 与 VR (is_vr) 下载画质属性回溯同步工具");
        $this->info("================================================================================");

        if ($isDryRun) {
            $this->warn("⚠️  当前处于【--dry-run 演练模式】，仅输出分析结果，不会更新数据库！");
        }

        // 1. 指定单个视频模式
        if ($videoId) {
            $video = Video::with(['downloads', 'channel'])->find($videoId);
            if (!$video) {
                $this->error("❌ 未找到 ID 为 [{$videoId}] 的视频！");
                return self::FAILURE;
            }

            $this->info("🔍 正在检查视频 ID: {$video->id} [{$video->video_code}] ...");
            $updates = $crawlerService->syncVideoQualities($video);

            if (empty($updates)) {
                $this->info("✅ 视频 ID: {$video->id} 画质属性与已有下载项已完全一致，无需更新。");
                $this->line("   当前状态: is_4k=" . ($video->is_4k ? 'true' : 'false') . ", max_quality=" . ($video->max_quality ?: 'null') . ", is_vr=" . ($video->is_vr ? 'true' : 'false'));
            } else {
                $this->info("✨ " . ($isDryRun ? "[演练预检] " : "") . "已更新视频 ID: {$video->id} [{$video->video_code}]");
                foreach ($updates as $k => $v) {
                    $valStr = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
                    $this->line("   -> <fg=green>{$k}</> = <fg=yellow>{$valStr}</>");
                }
            }
            return self::SUCCESS;
        }

        // 2. 批量扫描模式
        $query = $forceAll
            ? Video::query()->orderBy('id')
            : Video::where(function ($q) {
                $q->where('has_downloads', true)->orWhereHas('downloads');
            })->orderBy('id');

        if (!empty($channel)) {
            $query->whereHas('channel', function ($q) use ($channel) {
                $q->where('slug', $channel)->orWhere('name', 'like', "%{$channel}%");
            });
            $this->info("🏷️  已指定厂牌筛选: {$channel}");
        }

        $totalCandidates = (clone $query)->count();
        if ($totalCandidates === 0) {
            $this->warn("⚠️  未找到符合条件的视频记录。");
            return self::SUCCESS;
        }

        if ($limit !== null) {
            $query->limit($limit);
            $processCount = min($totalCandidates, $limit);
        } else {
            $processCount = $totalCandidates;
        }

        $this->info("📊 待扫描候选视频总计: <fg=cyan>{$processCount}</> 部" . ($limit ? " (总计 {$totalCandidates} 部，已限制 {$limit} 部)" : ""));

        $stats = [
            'scanned'             => 0,
            'updated_videos'      => 0,
            'updated_4k'          => 0,
            'updated_max_quality' => 0,
            'updated_vr'          => 0,
            'unchanged'           => 0,
        ];

        $bar = $this->output->createProgressBar($processCount);
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% -- %message%');
        $bar->setMessage('正在扫描...');
        $bar->start();

        $updatedDetails = [];

        foreach ($query->with(['downloads', 'channel'])->cursor() as $video) {
            $stats['scanned']++;

            if ($isDryRun) {
                $cloned = clone $video;
                $updates = $crawlerService->syncVideoQualities($cloned);
            } else {
                $updates = $crawlerService->syncVideoQualities($video);
            }

            if (!empty($updates)) {
                $stats['updated_videos']++;
                if (isset($updates['is_4k'])) $stats['updated_4k']++;
                if (isset($updates['max_quality'])) $stats['updated_max_quality']++;
                if (isset($updates['is_vr'])) $stats['updated_vr']++;

                if (count($updatedDetails) < 50) {
                    $changeDesc = [];
                    foreach ($updates as $k => $v) {
                        $valStr = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
                        $changeDesc[] = "{$k}: {$valStr}";
                    }
                    $updatedDetails[] = [
                        $video->id,
                        $video->video_code ?: ($video->name ?: "ID:{$video->id}"),
                        $video->channel ? $video->channel->name : '-',
                        implode(', ', $changeDesc),
                    ];
                }
            } else {
                $stats['unchanged']++;
            }

            $bar->setMessage("已更新: {$stats['updated_videos']} 部 (4K: {$stats['updated_4k']}, VR: {$stats['updated_vr']})");
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("================================================================================");
        $this->info("🎉 同步扫描完成！耗时: {$elapsed} 秒");
        $this->info("================================================================================");

        $this->table(
            ['指标', '统计数值'],
            [
                ['扫描视频数', $stats['scanned']],
                ['属性发生变更视频数', $stats['updated_videos']],
                ['同步 4K (is_4k=true) 视频数', $stats['updated_4k']],
                ['同步 最高画质 (max_quality) 视频数', $stats['updated_max_quality']],
                ['同步 VR (is_vr=true) 视频数', $stats['updated_vr']],
                ['保持未变视频数', $stats['unchanged']],
            ]
        );

        if (!empty($updatedDetails)) {
            $this->newLine();
            $this->info("📝 最近更新的部分视频明细 (前 " . count($updatedDetails) . " 部)：");
            $this->table(
                ['视频 ID', '视频 Code', '所属厂牌', '变更内容'],
                $updatedDetails
            );
        }

        return self::SUCCESS;
    }
}

