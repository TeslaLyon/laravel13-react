<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 优化现有 video_details 表中 screen_img 的 JSON 键名：
     * 1. 对于 data_crawl_type = 1 (Project1 等免防盗链源站片商)：
     *    - 剔除冗余 screen_img_ 前缀
     *    - 剔除永远为空的 default_url / full_url 本地字段
     *    - 保留 source 字段与宽高: source_url, width, height, full_source_url, full_width, full_height
     * 2. 对于 data_crawl_type = 2 或其他 (Vixen 等本地/云存储片商)：
     *    - 剔除所有 source 字段
     *    - 保留本地小图 url, width, height，大图 full_url 留空，尺寸设为 0
     */
    public function up(): void
    {
        $query = DB::table('video_details')
            ->whereNotNull('screen_img')
            ->where('screen_img', '!=', '')
            ->where('screen_img', '!=', '[]');

        $total = $query->count();
        if ($total === 0) {
            return;
        }

        $query->orderBy('id')->chunkById(200, function ($details) {
            $videoIds = $details->pluck('video_id')->filter()->unique()->toArray();

            $channelTypes = DB::table('videos')
                ->join('channels', 'channels.id', '=', 'videos.channel_id')
                ->whereIn('videos.id', $videoIds)
                ->pluck('channels.data_crawl_type', 'videos.id')
                ->toArray();

            foreach ($details as $detail) {
                $raw = $detail->screen_img;
                $items = is_string($raw) ? json_decode($raw, true) : (array) $raw;
                if (!is_array($items) || empty($items)) {
                    continue;
                }

                $type = (int) ($channelTypes[$detail->video_id] ?? 0);
                $isType1 = $type === 1;
                $newItems = [];
                $changed = false;

                foreach ($items as $item) {
                    if (!is_array($item)) {
                        $newItems[] = $item;
                        continue;
                    }

                    if ($isType1) {
                        // Project1: 仅保留 source 字段与尺寸
                        $sourceUrl = $item['source_url'] ?? $item['default_source_url'] ?? $item['screen_img_default_source_url'] ?? '';
                        $fullSourceUrl = $item['full_source_url'] ?? $item['screen_img_full_source_url'] ?? '';
                        $width = (int) ($item['width'] ?? $item['default_width'] ?? $item['screen_img_default_width'] ?? 0);
                        $height = (int) ($item['height'] ?? $item['default_height'] ?? $item['screen_img_default_height'] ?? 0);
                        $fullWidth = (int) ($item['full_width'] ?? $item['screen_img_full_width'] ?? $item['screen_img_full_source_width'] ?? 0);
                        $fullHeight = (int) ($item['full_height'] ?? $item['screen_img_full_height'] ?? $item['screen_img_full_source_height'] ?? 0);

                        $newItems[] = [
                            'source_url'      => $sourceUrl,
                            'width'           => $width,
                            'height'          => $height,
                            'full_source_url' => $fullSourceUrl,
                            'full_width'      => $fullWidth,
                            'full_height'     => $fullHeight,
                        ];
                        $changed = true;
                    } else {
                        // Vixen 及其他本地存储片商: 仅保留小图 url/width/height，无 source 字段，大图留空
                        $url = $item['url'] ?? $item['default_url'] ?? $item['screen_img_default_url'] ?? '';
                        $width = (int) ($item['width'] ?? $item['default_width'] ?? $item['screen_img_default_width'] ?? 0);
                        $height = (int) ($item['height'] ?? $item['default_height'] ?? $item['screen_img_default_height'] ?? 0);

                        $newItems[] = [
                            'url'         => $url,
                            'width'       => $width,
                            'height'      => $height,
                            'full_url'    => '',
                            'full_width'  => 0,
                            'full_height' => 0,
                        ];
                        $changed = true;
                    }
                }

                if ($changed) {
                    DB::table('video_details')
                        ->where('id', $detail->id)
                        ->update([
                            'screen_img' => json_encode($newItems, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        ]);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 单向数据结构升级与精简优化，无需回滚
    }
};
