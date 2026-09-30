<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\Photo;
use App\Models\Video;
use App\Models\VideoDetail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResetVixenVideoImagesCommand extends Command
{
    /**
     * 控制台命令签名
     *
     * @var string
     */
    protected $signature = 'crawler:vixen-reset-images
                            {channel? : 指定片商 slug（可选，未指定则处理所有 data_crawl_type=2 的 Vixen 系列片商）}
                            {--delete-records : 彻底物理删除关联的视频与详情数据行，而非仅清空图片字段值}
                            {--force : 强制执行，跳过交互确认}';

    /**
     * 控制台命令描述
     *
     * @var string
     */
    protected $description = '清空或重置 Vixen 系列片商视频的图片字段（list_img, preview, screen_img, list_img_large_meta），以便爬虫重新抓取并上传至 R2';

    /**
     * 执行控制台命令
     */
    public function handle(): int
    {
        $channelSlug = $this->argument('channel');
        $deleteRecords = (bool) $this->option('delete-records');
        $force = (bool) $this->option('force');

        // 1. 查询目标片商
        $query = Channel::where('data_crawl_type', 2);
        if (!empty($channelSlug)) {
            $query->where('slug', $channelSlug);
        }
        $channels = $query->get();

        if ($channels->isEmpty()) {
            $this->error("❌ 未找到符合条件的 Vixen 系列片商" . ($channelSlug ? " [{$channelSlug}]" : " (data_crawl_type=2)"));
            return self::FAILURE;
        }

        $channelIds = $channels->pluck('id')->toArray();
        $channelSlugs = $channels->pluck('slug')->toArray();

        // 2. 统计受影响的记录
        $videoIds = Video::whereIn('channel_id', $channelIds)->pluck('id');
        $videoCount = $videoIds->count();
        $detailCount = VideoDetail::whereIn('video_id', $videoIds)->count();

        $actionName = $deleteRecords ? '【彻底删除数据行】' : '【清空图片字段为 NULL (推荐)】';
        $this->warn("⚠️  即将对以下 " . count($channelSlugs) . " 个片商执行 {$actionName}：");
        $this->line("   片商列表: " . implode(', ', $channelSlugs));
        $this->line("   受影响视频数 (videos): {$videoCount}");
        $this->line("   受影响详情数 (video_details): {$detailCount}");

        if (!$deleteRecords) {
            $this->line("   涉及清空字段: videos.list_img, videos.preview, video_details.screen_img, video_details.list_img_large_meta");
        }

        // 3. 交互式确认
        if (!$force && !$this->confirm("确认继续执行吗？", false)) {
            $this->info("操作已取消。");
            return self::SUCCESS;
        }

        // 4. 事务安全执行
        DB::beginTransaction();
        try {
            if ($deleteRecords) {
                if ($videoIds->isNotEmpty()) {
                    // 清理中间表关联
                    if (Schema::hasTable('actor_video')) {
                        DB::table('actor_video')->whereIn('video_id', $videoIds)->delete();
                    }
                    if (Schema::hasTable('category_video')) {
                        DB::table('category_video')->whereIn('video_id', $videoIds)->delete();
                    }
                    if (Schema::hasTable('tag_video')) {
                        DB::table('tag_video')->whereIn('video_id', $videoIds)->delete();
                    }
                    DB::table('video_details')->whereIn('video_id', $videoIds)->delete();
                    Video::whereIn('id', $videoIds)->forceDelete();
                }
                $this->info("✅ 成功删除 {$videoCount} 条视频及关联的详情与中间表记录！");
            } else {
                if ($videoIds->isNotEmpty()) {
                    // 1) 清空 video_details 涉及图片的字段
                    $updatedDetails = DB::table('video_details')
                        ->whereIn('video_id', $videoIds)
                        ->update([
                            'screen_img'          => null,
                            'list_img_large_meta' => null,
                            'updated_at'          => now(),
                        ]);

                    // 2) 清空 videos 涉及图片的字段 (list_img, preview)
                    $updatedVideos = DB::table('videos')
                        ->whereIn('channel_id', $channelIds)
                        ->update([
                            'list_img'   => null,
                            'preview'    => null,
                            'updated_at' => now(),
                        ]);

                    // 3) 若存在写真套图表，同步清空封面
                    if (Schema::hasTable('photos')) {
                        DB::table('photos')
                            ->whereIn('channel_id', $channelIds)
                            ->update([
                                'cover_img'  => null,
                                'updated_at' => now(),
                            ]);
                    }

                    $this->info("✅ 成功清空 {$updatedVideos} 条视频与 {$updatedDetails} 条详情的图片字段！");
                } else {
                    $this->info("ℹ️ 目标片商下暂无视频记录，无需重置。");
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("❌ 执行失败: " . $e->getMessage());
            return self::FAILURE;
        }

        // 5. 输出下一步操作指引
        $this->newLine();
        $this->info("🎉 数据清理完毕！现在您可以重新运行爬虫命令重新抓取并上传图片至 Cloudflare R2：");
        $this->line("1. 重新抓取指定片商全量视频（含图片）：");
        $this->line("   <comment>php artisan crawler:vixen-videos vixen --all</comment>");
        $this->line("2. 重新遍历抓取所有 Vixen 系列片商视频（含图片）：");
        $this->line("   <comment>php artisan crawler:vixen-videos --all</comment>");
        $this->line("3. 或者执行每日协同增量抓取（含演员与最新视频）：");
        $this->line("   <comment>php artisan crawler:vixen-daily</comment>");

        return self::SUCCESS;
    }
}
