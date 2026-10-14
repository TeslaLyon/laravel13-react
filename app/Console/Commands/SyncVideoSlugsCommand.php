<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Photo;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SyncVideoSlugsCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'video:sync-slugs
                            {--channel= : 指定片商 slug（例如 dorcelclub、vixen、brazzers），未指定则同步所有片商视频}
                            {--dry-run : 演练预览模式，仅打印拟变更对比，不修改数据库}
                            {--force : 生产环境跳过确认交互直接执行}
                            {--chunk=500 : 批量分块处理大小（默认 500）}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '将视频数据库中的 slug 批量升级对齐为规范的 {channel_slug}-{source_slug} 格式，并补全 DorcelClub 的 source_uuid';

    /**
     * 执行控制台命令
     */
    public function handle(): int
    {
        DB::disableQueryLog();

        $targetChannel = $this->option('channel');
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $chunkSize = max(50, (int) ($this->option('chunk') ?: 500));

        $this->info("==================================================");
        $this->info("🚀 开始执行视频 slug 规范化同步与修复任务");
        $this->line("🎯 目标片商: " . ($targetChannel ? "<comment>{$targetChannel}</comment>" : "<info>全量片商</info>"));
        $this->line("🛡️ 执行模式: " . ($dryRun ? "<comment>演练模式 (Dry-Run，不写库)</comment>" : "<error>正式入库修改</error>"));
        $this->line("📦 分块批次: <info>{$chunkSize}</info>");
        $this->info("==================================================");

        if (!$dryRun && !$force && !$this->confirm('确定要在生产环境开始批量修改视频 slug 与 source_uuid 吗？', false)) {
            $this->warn("操作已取消。");
            return self::SUCCESS;
        }

        // 构建基础查询
        $query = Video::with('channel:id,slug,name');
        if (!empty($targetChannel)) {
            $channelModel = Channel::where('slug', $targetChannel)->first();
            if (!$channelModel) {
                $this->error("❌ 未找到 slug 为 [{$targetChannel}] 的片商！");
                return self::FAILURE;
            }
            $query->where('channel_id', $channelModel->id);
        }

        $totalVideos = $query->count();
        if ($totalVideos === 0) {
            $this->warn("未找到符合条件的视频记录。");
            return self::SUCCESS;
        }

        $this->line("📊 共找到 <info>{$totalVideos}</info> 部视频，开始逐批检查...");

        $stats = [
            'total'          => $totalVideos,
            'updated'        => 0,
            'already_valid'  => 0,
            'uuid_upgraded'  => 0,
            'collisions'     => 0,
            'skipped_no_chan'=> 0,
            'failed'         => 0,
        ];

        $sampleDiffs = [];
        $progressBar = $this->output->createProgressBar($totalVideos);
        $progressBar->start();

        $query->chunkById($chunkSize, function ($videos) use (&$stats, &$sampleDiffs, $dryRun, $progressBar) {
            foreach ($videos as $video) {
                $progressBar->advance();

                $channel = $video->channel;
                if (!$channel || empty($channel->slug)) {
                    $stats['skipped_no_chan']++;
                    continue;
                }

                $channelSlug = strtolower(trim($channel->slug));
                $currentSlug = trim((string) $video->slug);
                $currentUuid = trim((string) $video->source_uuid);

                // 1. 规范提取原生 source_slug
                $cleanSlug = preg_replace('/^' . preg_quote($channelSlug, '/') . '-+/i', '', $currentSlug);
                if (empty($cleanSlug)) {
                    $cleanSlug = Str::slug($video->name ?: $video->video_code);
                }

                $targetUuid = $currentUuid;
                $needUuidUpdate = false;

                // 2. 针对 DorcelClub，拼接规则为: dorcelclub-{id}-{slug}，source_uuid 为: dorcelclub:{id}:{slug}
                if ($channelSlug === 'dorcelclub') {
                    $videoSourceId = '';
                    $videoSourceSlug = $cleanSlug;

                    if (!empty($currentUuid)) {
                        $uuidParts = explode(':', $currentUuid);
                        if (count($uuidParts) >= 2 && $uuidParts[0] === 'dorcelclub') {
                            $videoSourceId = $uuidParts[1];
                            if (!empty($uuidParts[2])) {
                                $videoSourceSlug = $uuidParts[2];
                            }
                        }
                    }

                    // 若 cleanSlug 已经包含数字 ID 前缀（例如 895134-sensory-evening），提取出 ID 和纯 slug
                    if (empty($videoSourceId) && preg_match('/^(\d+)-(.*)$/', $cleanSlug, $m)) {
                        $videoSourceId = $m[1];
                        $videoSourceSlug = $m[2];
                    }

                    if (!empty($videoSourceId)) {
                        $targetSlug = "dorcelclub-{$videoSourceId}-{$videoSourceSlug}";
                        $targetUuid = "dorcelclub:{$videoSourceId}:{$videoSourceSlug}";
                        $needUuidUpdate = ($currentUuid !== $targetUuid);
                    } else {
                        $targetSlug = "dorcelclub-{$cleanSlug}";
                    }
                } else {
                    $targetSlug = "{$channelSlug}-{$cleanSlug}";
                }

                $needSlugUpdate = ($currentSlug !== $targetSlug);

                if (!$needSlugUpdate && !$needUuidUpdate) {
                    $stats['already_valid']++;
                    continue;
                }

                // 3. 冲突检测与解决（若新 slug 在库中已存在其他记录占用）
                $finalSlug = $targetSlug;
                if ($needSlugUpdate) {
                    $slugConflict = Video::where('slug', $targetSlug)
                        ->where('id', '!=', $video->id)
                        ->exists();

                    if ($slugConflict) {
                        $finalSlug = "{$targetSlug}-" . substr(md5($currentUuid ?: (string) $video->id), 0, 4);
                        $stats['collisions']++;
                    }
                }

                if (count($sampleDiffs) < 15) {
                    $sampleDiffs[] = [
                        'id'         => $video->id,
                        'channel'    => $channelSlug,
                        'old_slug'   => $currentSlug,
                        'new_slug'   => $finalSlug,
                        'old_uuid'   => $currentUuid,
                        'new_uuid'   => $targetUuid,
                    ];
                }

                if (!$dryRun) {
                    try {
                        $updateData = [];
                        if ($needSlugUpdate) {
                            $updateData['slug'] = $finalSlug;
                        }
                        if ($needUuidUpdate) {
                            $updateData['source_uuid'] = $targetUuid;
                        }

                        if (!empty($updateData)) {
                            Video::where('id', $video->id)->update($updateData);

                            // 同步升级关联的写真 Photo 记录（如果有）
                            if ($needSlugUpdate) {
                                Photo::where('channel_id', $video->channel_id)
                                    ->where('slug', $currentSlug)
                                    ->update(['slug' => $finalSlug]);
                            }

                            $stats['updated']++;
                            if ($needUuidUpdate) {
                                $stats['uuid_upgraded']++;
                            }
                        }
                    } catch (Throwable $e) {
                        $stats['failed']++;
                    }
                } else {
                    $stats['updated']++;
                    if ($needUuidUpdate) {
                        $stats['uuid_upgraded']++;
                    }
                }
            }
        });

        $progressBar->finish();
        $this->newLine(2);

        // 展示演练变更样例
        if (!empty($sampleDiffs)) {
            $this->info("📋 部分变更示例预览 (前 " . count($sampleDiffs) . " 条):");
            $this->table(
                ['ID', '片商', '原 Slug', '新 Slug', '原 Source UUID', '新 Source UUID'],
                array_map(fn ($item) => [
                    $item['id'],
                    $item['channel'],
                    $item['old_slug'],
                    $item['new_slug'],
                    $item['old_uuid'],
                    $item['new_uuid'],
                ], $sampleDiffs)
            );
        }

        $this->info("==================================================");
        $this->info("🎉 视频数据同步完成！统计概览：");
        $this->line("  * 扫描视频总数: <info>{$stats['total']}</info>");
        $this->line("  * 规范化 Slug 数量: <comment>{$stats['updated']}</comment>");
        $this->line("  * 升级 Source UUID 数量: <comment>{$stats['uuid_upgraded']}</comment>");
        $this->line("  * 无需更新 (已规范): <info>{$stats['already_valid']}</info>");
        $this->line("  * 冲突并自动消解数量: <comment>{$stats['collisions']}</comment>");
        if ($stats['skipped_no_chan'] > 0) {
            $this->line("  * 缺少关联片商跳过: <error>{$stats['skipped_no_chan']}</error>");
        }
        if ($stats['failed'] > 0) {
            $this->line("  * 更新失败异常数量: <error>{$stats['failed']}</error>");
        }
        $this->info("==================================================");

        return self::SUCCESS;
    }
}

