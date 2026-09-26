<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\ActorDetail;
use App\Models\Channel;
use App\Models\DelayedProcessingRepeatActor;
use App\Models\Photo;
use App\Models\PhotoDetail;
use App\Models\Video;
use App\Models\VideoDetail;
use App\Services\Image\ImageStorageService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class VixenCrawlerService
{
    protected ImageStorageService $imageStorage;

    public function __construct(?ImageStorageService $imageStorage = null)
    {
        $this->imageStorage = $imageStorage ?: new ImageStorageService();
    }

    /**
     * 抓取演员列表与图册
     *
     * @param string|null $channelSlug 片商 slug (为 null 时遍历所有 data_crawl_type=2 的片商)
     * @param int $page 指定抓取页码
     * @param bool $downloadImages 是否下载并优化图片至 R2
     * @return array 统计与分页结果
     */
    public function crawlActors(?string $channelSlug = null, int $page = 1, bool $downloadImages = true, bool $forceImages = false): array
    {
        if (!empty($channelSlug)) {
            $channel = Channel::where('slug', $channelSlug)
                ->where('data_crawl_type', 2)
                ->first();

            if (!$channel) {
                return ['success' => false, 'message' => "未找到片商 [{$channelSlug}] 或其 data_crawl_type 不为 2", 'stats' => []];
            }

            return $this->crawlSingleChannelActors($channel, $page, $downloadImages, $forceImages);
        }

        $channels = Channel::where('data_crawl_type', 2)->get();
        $totalStats = [
            'total_channels'     => $channels->count(),
            'channels_processed' => 0,
            'success_count'      => 0,
            'failed_count'       => 0,
            'skipped_count'      => 0,
        ];

        $anyHasNextPage = false;
        foreach ($channels as $channel) {
            $result = $this->crawlSingleChannelActors($channel, $page, $downloadImages, $forceImages);
            $totalStats['channels_processed']++;
            $totalStats['success_count'] += $result['success_count'] ?? 0;
            $totalStats['failed_count']  += $result['failed_count'] ?? 0;
            $totalStats['skipped_count'] += $result['skipped_count'] ?? 0;
            if (!empty($result['has_next_page'])) {
                $anyHasNextPage = true;
            }
        }

        return [
            'success'       => true,
            'stats'         => $totalStats,
            'has_next_page' => $anyHasNextPage,
        ];
    }

    /**
     * 爬取单个片商某一页的演员数据
     */
    public function crawlSingleChannelActors(Channel $channel, int $page = 1, bool $downloadImages = true, bool $forceImages = false): array
    {
        $page = max(1, $page);
        $url = rtrim($channel->official_website_url, '/') . '/performers';

        try {
            $html = $this->fetchHtml($url, ['page' => $page]);
            if (empty($html)) {
                return ['success' => false, 'message' => "请求 [{$channel->slug}] 演员列表第 {$page} 页失败", 'channel' => $channel->slug, 'page' => $page];
            }

            $nextData = $this->extractNextData($html);
            if (!$nextData || empty($nextData['props']['pageProps']['models'])) {
                return ['success' => false, 'message' => "解析 [{$channel->slug}] 第 {$page} 页 Next.js 数据为空", 'channel' => $channel->slug, 'page' => $page];
            }

            $pageProps = $nextData['props']['pageProps'];
            $models = $pageProps['models'];
            $totalCount = $pageProps['totalCount'] ?? 0;
            $pageSize = 18;

            $successCount = 0;
            $skippedCount = 0;
            $failedCount = 0;

            foreach ($models as $babe) {
                try {
                    $slug = $babe['slug'] ?? '';
                    $name = $babe['name'] ?? '';

                    if (empty($slug) || empty($name)) {
                        $skippedCount++;
                        continue;
                    }

                    // 1. 同名/重复演员冲突检测（旧系统 Redis 判定逻辑）
                    if ($this->isActorInDelayList($slug)) {
                        Log::warning("演员 [{$slug}] 存在于 repeat-actor-delay-list，已跳过抓取");
                        $skippedCount++;
                        continue;
                    }

                    // 2. 核心预检（选项 B：仅为空时补全，已有则绝不覆盖）
                    $actor = Actor::where('slug', $slug)->first();
                    if (!$forceImages && $actor && !empty($actor->booty_img)) {
                        // 演员已有图，直接维护 Redis 集合并跳过，杜绝任何无谓的图片下载与 R2 请求
                        try {
                            Redis::sadd("{$channel->slug}_actors", $slug);
                            Redis::sadd('actors', $slug);
                        } catch (Throwable $e) {}

                        $skippedCount++;
                        continue;
                    }

                    // 3. 仅当演员不存在或尚未有图片（或显式 force-images）时，才执行下载、cwebp优化并上传 R2
                    $bootyImg = $this->processActorImages($babe, $slug, $downloadImages);

                    // 4. 幂等入库或增量写入 Actor（选项 B：已有图片绝不覆盖，仅为空时补全）
                    if (!$actor) {
                        $actor = Actor::create([
                            'name'      => $name,
                            'slug'      => $slug,
                            'gender'    => 2, // 女性
                            'booty_img' => $bootyImg,
                        ]);
                    } elseif ($forceImages || empty($actor->booty_img)) {
                        if (!empty($bootyImg)) {
                            $actor->update(['booty_img' => $bootyImg]);
                        }
                    }

                    // 暂不同步写入 actor_details 表（等待表结构迁移统一对齐）

                    // 5. 维护 Redis 片商演员集合与全局演员集合（保留旧代码索引功能）
                    try {
                        Redis::sadd("{$channel->slug}_actors", $slug);
                        Redis::sadd('actors', $slug);
                    } catch (Throwable $e) {
                        // Redis 连接异常不阻断主流程
                    }

                    $successCount++;
                } catch (Throwable $e) {
                    $failedCount++;
                    Log::error("处理演员 [{$babe['slug']}] 入库异常: " . $e->getMessage());
                }
            }

            $hasNextPage = ($page * $pageSize) < $totalCount;

            return [
                'success'       => true,
                'channel'       => $channel->slug,
                'page'          => $page,
                'total'         => $totalCount,
                'total_pages'   => ceil($totalCount / $pageSize),
                'success_count' => $successCount,
                'failed_count'  => $failedCount,
                'skipped_count' => $skippedCount,
                'has_next_page' => $hasNextPage,
            ];
        } catch (Throwable $e) {
            Log::error("抓取片商 [{$channel->slug}] 演员异常: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'channel' => $channel->slug, 'page' => $page];
        }
    }

    /**
     * 抓取视频列表与详情
     *
     * @param string|null $channelSlug 片商 slug (为 null 时遍历所有 data_crawl_type=2 的片商)
     * @param int $page 指定抓取页码
     * @param string $type 运行模式: 'single' (单页) 或 'all' (向后循环)
     * @param bool $downloadImages 是否下载并优化图片至 R2
     * @return array 统计与结果
     */
    public function crawlVideos(?string $channelSlug = null, int $page = 1, string $type = 'single', bool $downloadImages = true): array
    {
        if (!empty($channelSlug)) {
            $channel = Channel::where('slug', $channelSlug)
                ->where('data_crawl_type', 2)
                ->first();

            if (!$channel) {
                return ['success' => false, 'message' => "未找到片商 [{$channelSlug}] 或其 data_crawl_type 不为 2", 'stats' => []];
            }

            return $this->crawlSingleChannelVideos($channel, $page, $downloadImages);
        }

        $channels = Channel::where('data_crawl_type', 2)->get();
        $totalStats = [
            'total_channels'     => $channels->count(),
            'channels_processed' => 0,
            'success_count'      => 0,
            'failed_count'       => 0,
            'skipped_count'      => 0,
        ];

        foreach ($channels as $channel) {
            $result = $this->crawlSingleChannelVideos($channel, $page, $downloadImages);
            $totalStats['channels_processed']++;
            $totalStats['success_count'] += $result['success_count'] ?? 0;
            $totalStats['failed_count']  += $result['failed_count'] ?? 0;
            $totalStats['skipped_count'] += $result['skipped_count'] ?? 0;
        }

        return [
            'success' => true,
            'stats'   => $totalStats,
        ];
    }

    /**
     * 爬取单个片商某一页的视频数据
     */
    public function crawlSingleChannelVideos(Channel $channel, int $page = 1, bool $downloadImages = true): array
    {
        $page = max(1, $page);
        $url = rtrim($channel->official_website_url, '/') . '/videos';

        try {
            $html = $this->fetchHtml($url, ['page' => $page]);
            if (empty($html)) {
                return ['success' => false, 'message' => "请求 [{$channel->slug}] 视频列表第 {$page} 页失败", 'channel' => $channel->slug, 'page' => $page];
            }

            $nextData = $this->extractNextData($html);
            if (!$nextData || empty($nextData['props']['pageProps']['edges'])) {
                return ['success' => false, 'message' => "解析 [{$channel->slug}] 第 {$page} 页 Next.js 视频数据为空", 'channel' => $channel->slug, 'page' => $page];
            }

            $pageProps = $nextData['props']['pageProps'];
            $edges = $pageProps['edges'];
            $totalCount = $pageProps['totalCount'] ?? 0;
            $pageSize = $pageProps['pageSize'] ?? 24;

            // 1. 同步更新片商视频总数
            if ($totalCount > 0) {
                $channel->update(['video_num' => $totalCount]);
            }

            $successCount = 0;
            $skippedCount = 0;
            $failedCount = 0;

            foreach ($edges as $edge) {
                $videoNode = $edge['node'] ?? null;
                if (!$videoNode) {
                    continue;
                }

                $res = $this->processSingleVideo($channel, $videoNode, $downloadImages);
                if ($res['status'] === 'success') {
                    $successCount++;
                } elseif ($res['status'] === 'skipped') {
                    $skippedCount++;
                } else {
                    $failedCount++;
                }
            }

            $hasNextPage = ($page * $pageSize) < $totalCount;

            return [
                'success'       => true,
                'channel'       => $channel->slug,
                'page'          => $page,
                'total'         => $totalCount,
                'total_pages'   => ceil($totalCount / $pageSize),
                'total_items'   => count($edges),
                'success_count' => $successCount,
                'failed_count'  => $failedCount,
                'skipped_count' => $skippedCount,
                'has_next_page' => $hasNextPage,
            ];
        } catch (Throwable $e) {
            Log::error("抓取片商 [{$channel->slug}] 视频异常: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage(), 'channel' => $channel->slug, 'page' => $page];
        }
    }

    /**
     * 处理单部视频的解析、图片上传与入库
     */
    protected function processSingleVideo(Channel $channel, array $videoNode, bool $downloadImages): array
    {
        $slug = $videoNode['slug'] ?? '';
        $videoId = $videoNode['videoId'] ?? '';
        if (empty($slug) || empty($videoId)) {
            return ['status' => 'skipped', 'reason' => '缺少 slug 或 videoId'];
        }

        $sourceUUID = "{$channel->slug}:{$videoId}:{$slug}";

        // 1. 冲突判定（旧代码核心逻辑：检测同名/待定演员）
        if ($this->handleVideoRepeatActor($videoNode, $channel, $sourceUUID)) {
            return ['status' => 'skipped', 'reason' => "命中重复演员判定，已记录至 delayed_processing_repeat_actors: {$sourceUUID}"];
        }

        // 2. 幂等性预检：如果视频已完整入库（包括大图元数据和片段截图），则跳过
        $existingVideo = Video::with('videoDetail')->where('source_uuid', $sourceUUID)->first();
        if (
            $existingVideo
            && !empty($existingVideo->list_img)
            && !empty($existingVideo->preview)
            && !empty($existingVideo->videoDetail?->screen_img)
            && !empty($existingVideo->videoDetail?->list_img_large_meta)
        ) {
            return ['status' => 'skipped', 'reason' => '视频已存在且数据完整'];
        }

        // 3. 拉取详情页 HTML，获取 carousel 轮播截图、大图 meta、高清时长等
        $detailUrl = rtrim($channel->official_website_url, '/') . "/videos/{$slug}";
        $detailHtml = $this->fetchHtml($detailUrl);
        $detailData = [];
        if (!empty($detailHtml)) {
            $detailNext = $this->extractNextData($detailHtml);
            $detailData = $detailNext['props']['pageProps']['video'] ?? [];
        }

        // 4. 解析视频基础属性
        $releaseAt = $this->parseReleaseAt($videoNode['releaseDate'] ?? null);
        $title = $videoNode['title'] ?? '';
        $downloadResolutions = $detailData['downloadResolutions'] ?? [];
        $is4k = $this->checkIs4k($downloadResolutions);
        $maxQuality = $this->parseMaxQuality($downloadResolutions);
        $sexualOrientation = $this->determineSexualOrientation($channel->slug);
        $movieLength = $this->parseMovieLength($detailData['runLength'] ?? null);
        $description = $detailData['description'] ?? '';

        // 5. 格式化生成统一编码 video_code
        $femaleActors = $detailData['modelsSlugged'] ?? ($videoNode['modelsSlugged'] ?? []);
        $videoCode = $this->generateVideoCode($channel->slug, $releaseAt, $femaleActors, $slug);

        // 6. 处理图片资源（list_img, list_img_large_meta, screen_img）
        $listImg = $this->processListImg($channel->slug, $videoId, $videoNode, $downloadImages);
        $listImgLargeMeta = $this->processListImgLargeMeta($channel->slug, $videoId, $detailData, $downloadImages);
        $screenImg = $this->processScreenImg($channel->slug, $videoId, $detailData, $downloadImages);

        // 7. 处理多图轮播预览（preview 字段格式：3<url1,url2...，摒弃 1< 视频存储）
        $previewUrl = $this->generateMultiImagePreview($screenImg, $listImg);

        $picturesInSet = (int) ($detailData['picturesInSet'] ?? 0);

        // 8. 数据库原子事务写入
        try {
            DB::transaction(function () use (
                $channel,
                $sourceUUID,
                $slug,
                $videoId,
                $title,
                $releaseAt,
                $videoCode,
                $is4k,
                $maxQuality,
                $sexualOrientation,
                $previewUrl,
                $listImg,
                $listImgLargeMeta,
                $screenImg,
                $movieLength,
                $description,
                $femaleActors,
                $picturesInSet
            ) {
                $video = Video::updateOrCreate(
                    ['source_uuid' => $sourceUUID],
                    [
                        'channel_id'         => $channel->id,
                        'name'               => $title,
                        'slug'               => $slug,
                        'release_at'         => $releaseAt,
                        'video_code'         => $videoCode,
                        'is_4k'              => $is4k,
                        'max_quality'        => $maxQuality,
                        'sexual_orientation' => $sexualOrientation,
                        'status'             => 1,
                        'preview'            => $previewUrl ?: null,
                        'list_img'           => $listImg,
                    ]
                );

                VideoDetail::updateOrCreate(
                    ['video_id' => $video->id],
                    [
                        'screen_img'          => $screenImg,
                        'list_img_large_meta' => $listImgLargeMeta,
                        'movie_length'        => $movieLength,
                        'description'         => $description,
                    ]
                );

                // 关联女演员到 actor_video
                $actorIds = [];
                foreach ($femaleActors as $actorItem) {
                    $actorSlug = $actorItem['slugged'] ?? ($actorItem['slug'] ?? '');
                    $actorName = $actorItem['name'] ?? '';
                    if (!empty($actorSlug) && !empty($actorName)) {
                        $actor = Actor::firstOrCreate(
                            ['slug' => $actorSlug],
                            ['name' => $actorName, 'gender' => 2]
                        );
                        $actorIds[] = $actor->id;
                    }
                }

                if (!empty($actorIds)) {
                    $video->actors()->syncWithoutDetaching($actorIds);
                }

                // 9. 同步写真套图 Photo 记录（若远端存在 picturesInSet 写真资源）
                if ($picturesInSet > 0) {
                    $photo = Photo::updateOrCreate(
                        [
                            'channel_id' => $channel->id,
                            'slug'       => $slug,
                        ],
                        [
                            'name'               => $title,
                            'slug'               => $slug,
                            'source_uuid'        => "{$channel->slug}:photo:{$videoId}:{$slug}",
                            'photo_code'         => $videoCode,
                            'release_at'         => $releaseAt,
                            'total'              => $picturesInSet,
                            'cover_img'          => $listImg[0] ?? null,
                            'sexual_orientation' => $sexualOrientation,
                            'status'             => 1,
                        ]
                    );

                    if (!empty($actorIds)) {
                        $photo->actors()->syncWithoutDetaching($actorIds);
                    }
                }
            });

            return ['status' => 'success', 'slug' => $slug];
        } catch (Throwable $e) {
            Log::error("视频 [{$slug}] 数据库入库失败: " . $e->getMessage());
            return ['status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /**
     * 重复演员判定与记录（完整继承旧代码业务逻辑）
     */
    protected function handleVideoRepeatActor(array $videoNode, Channel $channel, string $sourceUUID): bool
    {
        $actors = $videoNode['modelsSlugged'] ?? [];
        if (empty($actors)) {
            return false;
        }

        foreach ($actors as $actor) {
            $actorSlug = $actor['slugged'] ?? ($actor['slug'] ?? '');
            if (empty($actorSlug)) {
                continue;
            }

            try {
                // 1. 检查是否在延期观察名单中
                if (Redis::sismember('repeat-actor-delay-list', $actorSlug)) {
                    try {
                        DelayedProcessingRepeatActor::firstOrCreate(
                            ['video_slug' => $sourceUUID, 'type' => 0],
                            ['channel_id' => $channel->id, 'actor_slug' => $actorSlug, 'note' => '命中 repeat-actor-delay-list 延期名单']
                        );
                    } catch (Throwable $e) {}
                    return true;
                }

                // 2. 检查是否在同名不同人名单中
                if (Redis::sismember('repeat-actor-diff-people-with-the-same-name-list', $actorSlug)) {
                    try {
                        DelayedProcessingRepeatActor::firstOrCreate(
                            ['video_slug' => $sourceUUID, 'type' => 1],
                            ['channel_id' => $channel->id, 'actor_slug' => $actorSlug, 'note' => '命中 repeat-actor-diff-people-with-the-same-name-list 同名不同人名单']
                        );
                    } catch (Throwable $e) {}
                    return true;
                }

                // 3. 检查数据库中是否存在重复的演员记录
                if (Actor::where('slug', $actorSlug)->count() > 1) {
                    try {
                        DelayedProcessingRepeatActor::firstOrCreate(
                            ['video_slug' => $sourceUUID, 'type' => 2],
                            ['channel_id' => $channel->id, 'actor_slug' => $actorSlug, 'note' => '数据库中存在多条同 slug 演员记录']
                        );
                    } catch (Throwable $e) {}
                    return true;
                }
            } catch (Throwable $e) {
                // Redis 离线时安全跳过判定
            }
        }

        return false;
    }

    /**
     * 判断演员是否在延期名单中
     */
    protected function isActorInDelayList(string $actorSlug): bool
    {
        try {
            return (bool) Redis::sismember('repeat-actor-delay-list', $actorSlug);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * 生成规范的 video_code（如 Vixen.24.09.24.Ava.Addams.Title.Part）
     */
    protected function generateVideoCode(string $channelSlug, string $releaseAt, array $femaleActors, string $videoSlug): string
    {
        $code = Str::studly($channelSlug) . '.' . date('y.m.d', strtotime($releaseAt)) . '.';

        $actorParts = [];
        foreach ($femaleActors as $actor) {
            $name = $actor['name'] ?? '';
            if (!empty($name)) {
                $cleanName = str_replace(' ', '.', trim(str_replace('.', '', $name)));
                $actorParts[] = $cleanName;
            }
        }

        if (!empty($actorParts)) {
            $code .= implode('.And.', array_filter($actorParts)) . '.';
        }

        $parts = explode('-', $videoSlug);
        $titleParts = array_map(fn ($p) => ucfirst($p), $parts);
        $code .= implode('.', $titleParts);

        return $code;
    }

    /**
     * 处理演员图片结构
     */
    protected function processActorImages(array $babe, string $actorSlug, bool $downloadImages): array
    {
        $images = $babe['images']['listing'] ?? [];
        if (empty($images)) {
            return [];
        }

        $gallery = [];
        foreach ($images as $img) {
            // 🎯 仅读取 webp 格式的数据（彻底跳过外部低效 JPEG）
            $rawSrc = $img['webp']['src'] ?? ($img['src'] ?? '');
            $rawPlaceholder = $img['webp']['placeholder'] ?? ($img['placeholder'] ?? '');
            $rawHighdpi = $img['webp']['highdpi']['double'] ?? ($img['highdpi']['double'] ?? '');

            $srcUrl = $rawSrc;
            $placeholderUrl = $rawPlaceholder;
            $highdpiUrl = $rawHighdpi;

            if ($downloadImages && !empty($rawSrc)) {
                $fileHash = sha1(explode('?', $rawSrc)[0]);
                $subPath = "images/actors/{$actorSlug}/{$fileHash}.webp";

                $stored = $this->imageStorage->downloadOptimizeAndStore($rawSrc, $subPath, true);
                if ($stored) {
                    $srcUrl = $stored['path'];
                }

                if (!empty($rawPlaceholder)) {
                    $phHash = sha1(explode('?', $rawPlaceholder)[0]);
                    $phPath = "images/actors/{$actorSlug}/{$phHash}_ph.webp";
                    $storedPh = $this->imageStorage->downloadOptimizeAndStore($rawPlaceholder, $phPath, true);
                    if ($storedPh) {
                        $placeholderUrl = $storedPh['path'];
                    }
                }

                if (!empty($rawHighdpi)) {
                    $hdHash = sha1(explode('?', $rawHighdpi)[0]);
                    $hdPath = "images/actors/{$actorSlug}/{$hdHash}_hd.webp";
                    $storedHd = $this->imageStorage->downloadOptimizeAndStore($rawHighdpi, $hdPath, true);
                    if ($storedHd) {
                        $highdpiUrl = $storedHd['path'];
                    }
                }
            }

            $gallery[] = [
                'src'         => $srcUrl,
                'placeholder' => $placeholderUrl,
                'width'       => $img['width'] ?? 0,
                'height'      => $img['height'] ?? 0,
                'highdpi'     => [
                    'double' => $highdpiUrl,
                ],
            ];
        }

        return $gallery;
    }

    /**
     * 处理视频 list_img
     * 🎯 过滤规则：只读取横向/长方形图片（如 628x352，过滤掉 320x362 竖版图）
     * 🎯 结构优化：移除所有 _source 字段，移除嵌套 webp 对象，仅保留上一级 src, placeholder, width, height, highdpi
     */
    protected function processListImg(string $channelSlug, string $videoId, array $videoNode, bool $downloadImages): array
    {
        $listing = $videoNode['images']['listing'] ?? [];
        if (empty($listing)) {
            return [];
        }

        $listImgData = [];
        foreach ($listing as $img) {
            $width = (int) ($img['width'] ?? 0);
            $height = (int) ($img['height'] ?? 0);

            // 🎯 只读取长方形/横向图片（width > height，过滤 320x362 等竖版图片）
            if ($width <= $height) {
                continue;
            }

            // 🎯 核心逻辑：优先提取 webp 资源地址进行存储和展示
            $rawSrc = $img['webp']['src'] ?? ($img['src'] ?? '');
            $rawPlaceholder = $img['webp']['placeholder'] ?? ($img['placeholder'] ?? '');
            $rawDouble = $img['webp']['highdpi']['double'] ?? ($img['highdpi']['double'] ?? '');

            $srcUrl = $rawSrc;
            $placeholderUrl = $rawPlaceholder;
            $doubleUrl = $rawDouble;

            if ($downloadImages && !empty($rawSrc)) {
                $hash = sha1(explode('?', $rawSrc)[0]);
                $target = "images/{$channelSlug}/videos/{$videoId}/{$hash}.webp";
                $stored = $this->imageStorage->downloadOptimizeAndStore($rawSrc, $target, true);
                if ($stored) {
                    $srcUrl = $stored['path'];
                }

                if (!empty($rawPlaceholder)) {
                    $phHash = sha1(explode('?', $rawPlaceholder)[0]);
                    $phTarget = "images/{$channelSlug}/videos/{$videoId}/{$phHash}_ph.webp";
                    $storedPh = $this->imageStorage->downloadOptimizeAndStore($rawPlaceholder, $phTarget, true);
                    if ($storedPh) {
                        $placeholderUrl = $storedPh['path'];
                    }
                }

                if (!empty($rawDouble)) {
                    $doubleHash = sha1(explode('?', $rawDouble)[0]);
                    $doubleTarget = "images/{$channelSlug}/videos/{$videoId}/{$doubleHash}_double.webp";
                    $storedDouble = $this->imageStorage->downloadOptimizeAndStore($rawDouble, $doubleTarget, true);
                    if ($storedDouble) {
                        $doubleUrl = $storedDouble['path'];
                    }
                }
            }

            $listImgData[] = [
                'src'         => $srcUrl,
                'placeholder' => $placeholderUrl,
                'width'       => $width,
                'height'      => $height,
                'highdpi'     => [
                    'double' => $doubleUrl,
                ],
            ];
        }

        return $listImgData;
    }

    /**
     * 处理详情页头图 list_img_large_meta
     * 🎯 移除 _source 字段及嵌套 webp 数据，仅保留上一级 src, placeholder, width, height, breakpoint, media
     */
    protected function processListImgLargeMeta(string $channelSlug, string $videoId, array $detailData, bool $downloadImages): array
    {
        $sources = $detailData['videoImage']['sources'] ?? [];
        if (empty($sources)) {
            return [];
        }

        $result = [];
        foreach ($sources as $source) {
            $rawWebpSrc = $source['webp']['src'] ?? '';
            $rawWebpPlaceholder = $source['webp']['placeholderSrcSet'] ?? '';
            // 🎯 优先使用 webp 格式资源下载并优化
            $rawSrc = !empty($rawWebpSrc) ? $rawWebpSrc : ($source['src'] ?? '');
            $rawPlaceholder = !empty($rawWebpPlaceholder) ? $rawWebpPlaceholder : ($source['placeholderSrcSet'] ?? '');

            $srcUrl = $rawSrc;
            $placeholderUrl = $rawPlaceholder;

            if ($downloadImages && !empty($rawSrc)) {
                $hash = sha1(explode('?', $rawSrc)[0]);
                $target = "images/{$channelSlug}/videos/{$videoId}/large_{$hash}.webp";
                $stored = $this->imageStorage->downloadOptimizeAndStore($rawSrc, $target, true);
                if ($stored) {
                    $srcUrl = $stored['path'];
                }
            }

            $result[] = [
                'src'         => $srcUrl,
                'placeholder' => $placeholderUrl,
                'width'       => $source['width'] ?? 0,
                'height'      => $source['height'] ?? 0,
                'breakpoint'  => $source['breakpoint'] ?? 0,
                'media'       => $source['media'] ?? '',
            ];
        }

        return $result;
    }

    /**
     * 处理详情页片段轮播截图 screen_img
     * 🎯 键名精简化（缩短 key）：
     *    screen_img_default_url    -> url
     *    screen_img_default_width  -> width
     *    screen_img_default_height -> height
     *    screen_img_full_url       -> full_url
     *    screen_img_full_width     -> full_width
     *    screen_img_full_height    -> full_height
     * 🎯 移除所有 _source 字段
     */
    protected function processScreenImg(string $channelSlug, string $videoId, array $detailData, bool $downloadImages): array
    {
        $carousel = $detailData['carousel'] ?? [];
        if (empty($carousel)) {
            return [];
        }

        $screenImgs = [];
        foreach ($carousel as $idx => $item) {
            // 🎯 优先抓取 webp 格式截图
            $smRaw = $item['listing'][0]['webp']['src'] ?? ($item['listing'][0]['src'] ?? '');
            $xxRaw = $item['main'][0]['webp']['src'] ?? ($item['main'][0]['src'] ?? '');

            if (empty($smRaw) && empty($xxRaw)) {
                continue;
            }

            $smUrl = $smRaw;
            $xxUrl = $xxRaw;

            if ($downloadImages) {
                if (!empty($smRaw)) {
                    $smHash = sha1(explode('?', $smRaw)[0]);
                    $smTarget = "images/{$channelSlug}/videos/{$videoId}/screen_sm_{$idx}_{$smHash}.webp";
                    $storedSm = $this->imageStorage->downloadOptimizeAndStore($smRaw, $smTarget, true);
                    if ($storedSm) {
                        $smUrl = $storedSm['path'];
                    }
                }

                if (!empty($xxRaw)) {
                    $xxHash = sha1(explode('?', $xxRaw)[0]);
                    $xxTarget = "images/{$channelSlug}/videos/{$videoId}/screen_xx_{$idx}_{$xxHash}.webp";
                    $storedXx = $this->imageStorage->downloadOptimizeAndStore($xxRaw, $xxTarget, true);
                    if ($storedXx) {
                        $xxUrl = $storedXx['path'];
                    }
                }
            }

            $screenImgs[] = [
                'url'         => $smUrl,
                'width'       => $item['listing'][0]['width'] ?? 0,
                'height'      => $item['listing'][0]['height'] ?? 0,
                'full_url'    => $xxUrl,
                'full_width'  => $item['main'][0]['width'] ?? 0,
                'full_height' => $item['main'][0]['height'] ?? 0,
            ];
        }

        return $screenImgs;
    }

    /**
     * 生成前端卡片悬停多图轮播字段（preview 字段格式：3<url1,url2,url3...）
     */
    protected function generateMultiImagePreview(array $screenImg, array $listImg): string
    {
        $previewUrls = [];

        // 优先使用轮播图截图（screen_img 缩略图，体积小加载快）
        foreach ($screenImg as $item) {
            $url = $item['url'] ?? $item['screen_img_default_url'] ?? '';
            if (!empty($url)) {
                $previewUrls[] = $url;
            }
        }

        // 若无截图，降级复用列表封面 WebP 图
        if (empty($previewUrls)) {
            foreach ($listImg as $item) {
                $url = $item['src'] ?? '';
                if (!empty($url)) {
                    $previewUrls[] = $url;
                }
            }
        }

        if (empty($previewUrls)) {
            return '';
        }

        // 最多截取前 12 张图片以逗号分隔拼接为 3<url1,url2...
        $sliced = array_slice($previewUrls, 0, 12);

        return '3<' . implode(',', $sliced);
    }

    /**
     * 解析发布时间为上海时区
     */
    protected function parseReleaseAt(?string $dateStr): string
    {
        if (empty($dateStr)) {
            return now()->toDateTimeString();
        }

        try {
            return Carbon::parse($dateStr)->setTimezone('Asia/Shanghai')->toDateTimeString();
        } catch (Throwable $e) {
            return now()->toDateTimeString();
        }
    }

    /**
     * 校验是否为 4K 画质
     */
    protected function checkIs4k(array $downloadResolutions): bool
    {
        foreach ($downloadResolutions as $res) {
            $w = (int) ($res['width'] ?? 0);
            $h = (int) ($res['height'] ?? 0);
            if ($w >= 2160 || $h >= 2160) {
                return true;
            }
        }
        return false;
    }

    /**
     * 解析最高画质（例如 2160p, 1080p, 720p 等）
     */
    protected function parseMaxQuality(array $downloadResolutions): string
    {
        $maxDimension = 0;

        foreach ($downloadResolutions as $res) {
            $w = (int) ($res['width'] ?? 0);
            $h = (int) ($res['height'] ?? 0);
            // 分辨率通常以高度为画质标称 (如 3840x2160 为 2160p，1920x1080 为 1080p)
            $dimension = min($w, $h) ?: max($w, $h);
            if ($dimension > $maxDimension) {
                $maxDimension = $dimension;
            }
        }

        if ($maxDimension >= 2160) {
            return '2160p';
        }
        if ($maxDimension >= 1080) {
            return '1080p';
        }
        if ($maxDimension >= 720) {
            return '720p';
        }
        if ($maxDimension >= 480) {
            return '480p';
        }

        return $maxDimension > 0 ? "{$maxDimension}p" : '1080p';
    }

    /**
     * 根据片商 slug 判定性取向 (1-异性; 2-女同性恋; 0-未知)
     * Vixen 系列中 slayed 为全女同(Lesbian)站点，其余站点均为异性恋(Straight)
     */
    protected function determineSexualOrientation(string $channelSlug): int
    {
        return strtolower($channelSlug) === 'slayed' ? 2 : 1;
    }

    /**
     * 将 H:i:s 时长转换为秒
     */
    protected function parseMovieLength(?string $runLength): int
    {
        if (empty($runLength)) {
            return 0;
        }

        try {
            $parts = explode(':', $runLength);
            if (count($parts) === 3) {
                return ((int) $parts[0] * 3600) + ((int) $parts[1] * 60) + (int) $parts[2];
            }
            if (count($parts) === 2) {
                return ((int) $parts[0] * 60) + (int) $parts[1];
            }
        } catch (Throwable $e) {
            return 0;
        }

        return 0;
    }

    /**
     * 从 HTML 中提取 Next.js SSR 数据 JSON
     */
    public function extractNextData(string $html): ?array
    {
        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            $data = json_decode($matches[1], true);
            if (is_array($data) && isset($data['props'])) {
                return $data;
            }
        }
        return null;
    }

    /**
     * 抓取网页 HTML 内容（增强版：真实 Chrome 伪装、Cookie/cf_clearance 注入与 FlareSolverr 自动绕过）
     */
    protected function fetchHtml(string $url, array $params = []): string
    {
        $flaresolverrUrl = env('FLARESOLVERR_URL');

        try {
            $proxy = env('CRAWLER_PROXY') ?: (env('HTTP_PROXY') ?: env('HTTPS_PROXY'));
            $customUserAgent = env('CRAWLER_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');

            // 1. 获取 Cookie（支持直接配置 Cookie，或配置 cf_clearance，或读取 Redis 中 FlareSolverr 缓存的通行令牌）
            $cookie = env('VIXEN_COOKIE');
            if (empty($cookie)) {
                $cfClearance = env('VIXEN_CF_CLEARANCE');
                if (empty($cfClearance)) {
                    try {
                        $cfClearance = Redis::get('vixen_cf_clearance');
                    } catch (Throwable) {
                        $cfClearance = null;
                    }
                }
                if (!empty($cfClearance)) {
                    $cookie = 'cf_clearance=' . $cfClearance;
                }
            }

            // 2. 伪装完整的现代 Chrome 浏览器协议指纹
            $headers = [
                'user-agent'                => $customUserAgent,
                'accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'accept-language'           => 'en-US,en;q=0.9',
                'sec-ch-ua'                 => '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
                'sec-ch-ua-mobile'          => '?0',
                'sec-ch-ua-platform'        => '"macOS"',
                'sec-fetch-dest'            => 'document',
                'sec-fetch-mode'            => 'navigate',
                'sec-fetch-site'            => 'none',
                'sec-fetch-user'            => '?1',
                'upgrade-insecure-requests' => '1',
            ];

            if (!empty($cookie)) {
                $headers['cookie'] = $cookie;
            }

            $request = Http::timeout(25)->retry(2, 1000)->withHeaders($headers);

            if (!empty($proxy)) {
                $request = $request->withOptions(['proxy' => $proxy]);
            }

            $response = $request->get($url, $params);

            // 3. 检查是否被 Cloudflare 拦截（403 或页面内容包含 Just a moment... 挑战）
            if ($response->status() === 403 || str_contains($response->body(), 'Just a moment...')) {
                Log::warning("目标页面命中 Cloudflare 5秒盾阻断 [{$url}]");

                // 若配置了 FlareSolverr，自动调用无头浏览器集群突破验证
                if (!empty($flaresolverrUrl)) {
                    return $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
                }

                return '';
            }

            return $response->successful() ? $response->body() : '';
        } catch (Throwable $e) {
            // 网络异常或 cURL 错误时，若配置了 FlareSolverr 则降级尝试
            if (!empty($flaresolverrUrl)) {
                return $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
            }

            Log::error("请求 URL 失败 [{$url}]: " . $e->getMessage());
            return '';
        }
    }

    /**
     * 通过 FlareSolverr 自动执行 JS/Turnstile 穿透 Cloudflare 验证
     */
    protected function fetchViaFlareSolverr(string $flaresolverrUrl, string $url, array $params = []): string
    {
        try {
            if (!empty($params)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }

            Log::info("正在通过 FlareSolverr 自动过盾: {$url}");

            $postData = [
                'cmd'        => 'request.get',
                'url'        => $url,
                'maxTimeout' => 60000,
            ];

            $proxy = env('CRAWLER_PROXY') ?: (env('HTTP_PROXY') ?: env('HTTPS_PROXY'));
            if (!empty($proxy)) {
                $postData['proxy'] = ['url' => $proxy];
            }

            $cleanUrl = rtrim($flaresolverrUrl, '/');
            $endpoint = str_ends_with($cleanUrl, '/v1') ? $cleanUrl : "{$cleanUrl}/v1";

            $response = Http::timeout(65)->post($endpoint, $postData);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'ok' && !empty($data['solution']['response'])) {
                    Log::info("FlareSolverr 成功突破 Cloudflare 并获取网页！");

                    // 提取并自动缓存 cf_clearance Cookie 供后续直接请求复用（有效期2小时）
                    if (!empty($data['solution']['cookies'])) {
                        foreach ($data['solution']['cookies'] as $c) {
                            if (($c['name'] ?? '') === 'cf_clearance' && !empty($c['value'])) {
                                try {
                                    Redis::setex('vixen_cf_clearance', 7200, $c['value']);
                                } catch (Throwable) {
                                }
                            }
                        }
                    }

                    return $data['solution']['response'];
                }
            }

            Log::error("FlareSolverr 求解失败: " . $response->body());
            return '';
        } catch (Throwable $e) {
            Log::error("调用 FlareSolverr 发生异常: " . $e->getMessage());
            return '';
        }
    }
}

