<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Video;
use App\Models\VideoDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class CleanVideoImageMetaCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'video:clean-image-meta
                            {channel? : 指定片商 slug（可选，未指定则默认处理所有 data_crawl_type=1 的源站直链片商）}
                            {--all : 强制处理所有片商（包括不同 crawl_type 的片商）}
                            {--chunk=500 : 批量分块处理的记录数，防止大表内存溢出}
                            {--dry-run : 演练模式，仅输出清理对比样例与统计，不实际修改数据库}
                            {--force : 强制执行，跳过交互式二次确认}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '清理生产环境中 videos.list_img 与 video_details.list_img_large_meta 中无用的本地路径字段(src, placeholder, double, webp)，升级为纯净的 WebP 源站直链结构';

    /**
     * 执行控制台命令
     */
    public function handle(): int
    {
        $channelSlug = $this->argument('channel');
        $allChannels = (bool) $this->option('all');
        $chunkSize = max(50, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info("==================================================");
        $this->info("🚀 开始执行视频封面与大图元数据清理脚本");
        $this->line("🛡️ 运行模式: " . ($dryRun ? "<comment>演练模式 (Dry-Run，不修改数据库)</comment>" : "<info>正式清理模式 (写库)</info>"));
        $this->line("📦 分块大小: {$chunkSize}");

        // 1. 确定目标片商
        $channelQuery = Channel::query();
        if (!empty($channelSlug)) {
            $channelQuery->where('slug', $channelSlug);
        } elseif (!$allChannels) {
            // 默认针对 data_crawl_type = 1 (源站直链片商如 Project1 矩阵与 DorcelClub)
            $channelQuery->where('data_crawl_type', 1);
        }

        $channels = $channelQuery->get();
        if ($channels->isEmpty() && !empty($channelSlug)) {
            $this->error("❌ 未找到指定的片商 [{$channelSlug}]！");
            return self::FAILURE;
        }

        $channelIds = $channels->pluck('id')->toArray();
        if (!empty($channelIds)) {
            $this->line("🎯 目标片商 (" . count($channels) . " 个): " . $channels->pluck('slug')->implode(', '));
        } else {
            $this->line("🎯 目标片商: 全量片商 (--all)");
        }
        $this->info("==================================================");

        // 2. 统计需要检查的视频总数
        $videoQuery = Video::query();
        if (!empty($channelIds)) {
            $videoQuery->whereIn('channel_id', $channelIds);
        }
        $totalVideos = $videoQuery->count();

        if ($totalVideos === 0) {
            $this->warn("⚠️  目标范围内没有找到任何视频记录。");
            return self::SUCCESS;
        }

        $this->line("📊 待检索视频总数: {$totalVideos} 条");

        // 3. 交互式确认
        if (!$dryRun && !$force) {
            if (!$this->confirm("确认开始清理这批视频的图片元数据吗？", true)) {
                $this->info("操作已取消。");
                return self::SUCCESS;
            }
        }

        // 4. 开始分块检索并执行清理
        $stats = [
            'total_scanned'    => 0,
            'videos_cleaned'   => 0,
            'details_cleaned'  => 0,
            'already_clean'    => 0,
            'errors'           => 0,
        ];

        $sampleDiffPrinted = false;

        $bar = $this->output->createProgressBar($totalVideos);
        $bar->start();

        $chunkQuery = Video::query();
        if (!empty($channelIds)) {
            $chunkQuery->whereIn('channel_id', $channelIds);
        }

        $chunkQuery->select(['id', 'channel_id', 'list_img'])->chunkById($chunkSize, function ($videos) use (
            $dryRun,
            &$stats,
            &$sampleDiffPrinted,
            $bar
        ) {
            $videoIds = $videos->pluck('id')->toArray();
            // 一次性查出对应的 video_details
            $detailsMap = VideoDetail::whereIn('video_id', $videoIds)
                ->get(['id', 'video_id', 'list_img_large_meta'])
                ->keyBy('video_id');

            foreach ($videos as $video) {
                $stats['total_scanned']++;
                $bar->advance();

                $rawListImg = $video->getRawOriginal('list_img');
                $listImgData = is_string($rawListImg) ? json_decode($rawListImg, true) : $rawListImg;

                $videoNeedsUpdate = false;
                $cleanedListImg = [];

                if (is_array($listImgData) && !empty($listImgData)) {
                    if ($this->isOldFormatListImg($listImgData)) {
                        $cleanedListImg = $this->cleanListImgData($listImgData);
                        $videoNeedsUpdate = true;
                    } else {
                        $cleanedListImg = $listImgData;
                    }
                }

                // 详情大图
                $detail = $detailsMap->get($video->id);
                $detailNeedsUpdate = false;
                $cleanedLargeMeta = [];

                if ($detail) {
                    $rawLargeMeta = $detail->getRawOriginal('list_img_large_meta');
                    $largeMetaData = is_string($rawLargeMeta) ? json_decode($rawLargeMeta, true) : $rawLargeMeta;

                    if (is_array($largeMetaData) && !empty($largeMetaData)) {
                        if ($this->isOldFormatLargeMeta($largeMetaData)) {
                            $cleanedLargeMeta = $this->cleanLargeMetaData($largeMetaData, $cleanedListImg);
                            $detailNeedsUpdate = true;
                        }
                    }
                }

                if (!$videoNeedsUpdate && !$detailNeedsUpdate) {
                    $stats['already_clean']++;
                    continue;
                }

                // 打印前几个演练对比样本
                if (!$sampleDiffPrinted) {
                    $sampleDiffPrinted = true;
                    $this->newLine();
                    $this->info("--------------------------------------------------");
                    $this->info("🔍 [清理对比样本 - Video ID: {$video->id}]");
                    if ($videoNeedsUpdate) {
                        $this->line("<comment>【旧 list_img (清洗前)】</comment>:");
                        $this->line(json_encode($listImgData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                        $this->line("<info>【新 list_img (清洗后)】</info>:");
                        $this->line(json_encode($cleanedListImg, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    }
                    if ($detailNeedsUpdate) {
                        $this->line("<comment>【旧 list_img_large_meta (清洗前)】</comment>:");
                        $this->line(json_encode($largeMetaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                        $this->line("<info>【新 list_img_large_meta (清洗后)】</info>:");
                        $this->line(json_encode($cleanedLargeMeta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
                    }
                    $this->info("--------------------------------------------------");
                }

                // 正式写库
                if (!$dryRun) {
                    try {
                        if ($videoNeedsUpdate) {
                            DB::table('videos')->where('id', $video->id)->update([
                                'list_img' => json_encode($cleanedListImg, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            ]);
                            $stats['videos_cleaned']++;
                        }

                        if ($detailNeedsUpdate && $detail) {
                            DB::table('video_details')->where('id', $detail->id)->update([
                                'list_img_large_meta' => json_encode($cleanedLargeMeta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                            ]);
                            $stats['details_cleaned']++;
                        }
                    } catch (Throwable $e) {
                        $stats['errors']++;
                    }
                } else {
                    if ($videoNeedsUpdate) {
                        $stats['videos_cleaned']++;
                    }
                    if ($detailNeedsUpdate) {
                        $stats['details_cleaned']++;
                    }
                }
            }
        });

        $bar->finish();
        $this->newLine(2);

        // 5. 汇报最终统计结果
        $this->info("==================================================");
        $this->info("🎉 数据清理执行完毕！统计汇报如下：");
        $this->table(
            ['指标', '数值'],
            [
                ['扫描视频总数 (Scanned)', $stats['total_scanned']],
                ['清洗更新封面数 (videos.list_img)', $stats['videos_cleaned']],
                ['清洗更新大图数 (video_details.list_img_large_meta)', $stats['details_cleaned']],
                ['原生已规范无需处理 (Already Clean)', $stats['already_clean']],
                ['异常错误数 (Errors)', $stats['errors']],
                ['运行模式', $dryRun ? '演练模式 (未修改数据库)' : '正式写库'],
            ]
        );
        $this->info("==================================================");

        return self::SUCCESS;
    }

    /**
     * 判断 list_img 是否包含旧冗余字段
     */
    public function isOldFormatListImg(array $listImg): bool
    {
        foreach ($listImg as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (array_key_exists('src', $item)
                || array_key_exists('placeholder', $item)
                || array_key_exists('webp', $item)
                || (isset($item['highdpi']) && is_array($item['highdpi']) && array_key_exists('double', $item['highdpi']))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 判断 list_img_large_meta 是否包含旧冗余字段
     */
    public function isOldFormatLargeMeta(array $largeMeta): bool
    {
        foreach ($largeMeta as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (array_key_exists('src', $item)
                || array_key_exists('placeholder', $item)
                || array_key_exists('webp', $item)
                || (isset($item['highdpi']) && is_array($item['highdpi']) && array_key_exists('double', $item['highdpi']))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 将旧格式的 list_img 清理为新格式（优先提取 WebP 源站直链）
     */
    public function cleanListImgData(array $listImg): array
    {
        $cleaned = [];
        foreach ($listImg as $item) {
            if (!is_array($item)) {
                continue;
            }

            $srcSource = $item['webp']['src_source'] ?? $item['src_source'] ?? '';
            $placeholderSource = $item['webp']['placeholder_source'] ?? $item['placeholder_source'] ?? '';
            $doubleSource = $item['webp']['highdpi']['double_source'] ?? $item['highdpi']['double_source'] ?? '';

            $cleaned[] = [
                'src_source'         => $srcSource,
                'placeholder_source' => $placeholderSource,
                'width'              => (int) ($item['width'] ?? 0),
                'height'             => (int) ($item['height'] ?? 0),
                'highdpi'            => [
                    'double_source' => $doubleSource,
                ],
            ];
        }

        return $cleaned;
    }

    /**
     * 将旧格式的 list_img_large_meta 清理为新格式
     */
    public function cleanLargeMetaData(array $largeMeta, array $cleanedListImg = []): array
    {
        $cleaned = [];
        foreach ($largeMeta as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $srcSource = $item['webp']['src_source'] ?? $item['src_source'] ?? '';
            $placeholderSource = $item['webp']['placeholder_source']
                ?? $item['placeholder_source']
                ?? ($cleanedListImg[0]['placeholder_source'] ?? '');

            $doubleSource = $item['webp']['highdpi']['double_source']
                ?? $item['highdpi']['double_source']
                ?? '';

            // 智能兜底：若旧数据无 double_source，按响应式断点从已清洗的 list_img 提取
            if (empty($doubleSource) && !empty($cleanedListImg)) {
                if ($index === 0) {
                    $doubleSource = $cleanedListImg[0]['highdpi']['double_source'] ?? $srcSource;
                } elseif ($index === 1) {
                    $doubleSource = $cleanedListImg[1]['highdpi']['double_source'] ?? $srcSource;
                } else {
                    $doubleSource = $cleanedListImg[1]['highdpi']['double_source'] ?? $srcSource;
                }
            }

            $cleaned[] = [
                'src_source'         => $srcSource,
                'placeholder_source' => $placeholderSource,
                'width'              => (int) ($item['width'] ?? 0),
                'height'             => (int) ($item['height'] ?? 0),
                'media'              => $item['media'] ?? '',
                'highdpi'            => [
                    'double_source' => $doubleSource,
                ],
            ];
        }

        return $cleaned;
    }
}

