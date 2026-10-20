<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Site;
use App\Models\Video;
use App\Models\VideoDetail;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class Project1VideoCrawlerService
{
    /**
     * 官方支持的 Project1 体系片商配置字典（单一真理源 Single Source of Truth）
     * 增添新平台时只需在此登记 slug 与配置，定时任务、命令、后台等全系统自动生效
     */
    public const SUPPORTED_CHANNELS = [
        'brazzers' => [
            'name'         => 'Brazzers',
            'official_url' => 'https://www.brazzers.com',
            'has_site_prefix' => true,
        ],
        'bangbros' => [
            'name'         => 'BangBros',
            'official_url' => 'https://bangbros.com',
            'has_site_prefix' => true,
        ],
        'realitykings' => [
            'name'         => 'Reality Kings',
            'official_url' => 'https://www.realitykings.com',
            'has_site_prefix' => true,
        ],
        'mofos' => [
            'name'         => 'Mofos',
            'official_url' => 'https://www.mofos.com',
            'has_site_prefix' => true,
        ],
        'digitalplayground' => [
            'name'         => 'Digital Playground',
            'official_url' => 'https://www.digitalplayground.com',
            'has_site_prefix' => true,
        ],
        'twistys' => [
            'name'         => 'Twistys',
            'official_url' => 'https://www.twistys.com',
            'has_site_prefix' => true,
        ],
        'babes' => [
            'name'         => 'Babes',
            'official_url' => 'https://www.babes.com',
            'has_site_prefix' => true,
        ],
        'fakehub' => [
            'name'         => 'FakeHub',
            'official_url' => 'https://www.fakehub.com',
            'has_site_prefix' => true,
        ],
        'propertysex' => [
            'name'         => 'PropertySex',
            'official_url' => 'https://www.propertysex.com',
            'has_site_prefix' => true,
        ],
    ];

    /**
     * 获取所有受支持的 Project1 片商 slug 列表
     *
     * @return array<string>
     */
    public static function getSupportedSlugs(): array
    {
        return array_keys(self::SUPPORTED_CHANNELS);
    }

    /**
     * 检查指定片商 slug 是否属于 Project1 体系
     */
    public static function isSupported(?string $slug): bool
    {
        if (empty($slug)) {
            return false;
        }

        return isset(self::SUPPORTED_CHANNELS[strtolower($slug)]);
    }

    /**
     * 获取指定片商配置
     */
    public static function getChannelConfig(string $slug): ?array
    {
        return self::SUPPORTED_CHANNELS[strtolower($slug)] ?? null;
    }

    /**
     * 执行指定片商或所有片商的视频列表爬取任务
     *
     * @param string|null $channelSlug 片商 slug（若为空则爬取所有支持的 Project1 片商）
     * @param int $limit 每次拉取视频条数
     * @param string|null $explicitToken 显式传入的 token，未传则从 Redis/Cache 读取或自动从官网抓取
     * @param bool $forceRefreshToken 是否强制刷新 Token
     * @param int $page 指定抓取页码（从 1 开始）
     * @return array 任务结果统计
     */
    public function crawlVideos(?string $channelSlug = null, int $limit = 24, ?string $explicitToken = null, bool $forceRefreshToken = false, int $page = 1): array
    {
        if (!empty($channelSlug)) {
            $channel = Channel::where('slug', $channelSlug)
                ->where('data_crawl_type', 1)
                ->first();

            if (!$channel) {
                $msg = "片商 [{$channelSlug}] 未找到或 data_crawl_type 不为 1";
                Log::warning($msg);
                return ['success' => false, 'message' => $msg, 'stats' => []];
            }

            return $this->crawlSingleChannel($channel, $limit, $explicitToken, $forceRefreshToken, $page);
        }

        // 未指定片商时，遍历属于 Project1 体系的全部受支持片商（基于 SUPPORTED_CHANNELS 单一真理源）
        $supportedSlugs = self::getSupportedSlugs();
        $channels = Channel::where('data_crawl_type', 1)
            ->whereIn('slug', $supportedSlugs)
            ->get();
        $totalStats = [
            'total_channels'     => $channels->count(),
            'channels_processed' => 0,
            'success_count'      => 0,
            'failed_count'       => 0,
            'skipped_count'      => 0,
        ];

        foreach ($channels as $channel) {
            $result = $this->crawlSingleChannel($channel, $limit, $explicitToken, $forceRefreshToken, $page);
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
     * 爬取单个片商的数据
     */
    public function crawlSingleChannel(Channel $channel, int $limit = 24, ?string $explicitToken = null, bool $forceRefreshToken = false, int $page = 1): array
    {
        $page = max(1, $page);
        $token = $explicitToken ?: $this->resolveChannelToken($channel, $forceRefreshToken);

        if (empty($token)) {
            $msg = "片商 [{$channel->slug}] 无法获取到有效的 API Token（自动抓取失败且无可用缓存）";
            Log::warning($msg);
            return [
                'success'       => false,
                'channel'       => $channel->slug,
                'page'          => $page,
                'message'       => $msg,
                'success_count' => 0,
                'failed_count'  => 0,
                'skipped_count' => 0,
            ];
        }

        Log::info("开始爬取片商 [{$channel->slug}] 视频列表，第 {$page} 页，Limit: {$limit}");

        $res = $this->requestApiReleases($channel, $token, $limit, $page);

        // 如果未通过认证 (401 或 403) 且非显式指定的 Token，自动刷新一次 Token 并重试
        if (!$explicitToken && in_array($res['status'], [401, 403])) {
            Log::warning("片商 [{$channel->slug}] Token 失效或过期 (HTTP {$res['status']})，正在自动刷新并重试...");
            $token = $this->resolveChannelToken($channel, true);
            if (!empty($token)) {
                $res = $this->requestApiReleases($channel, $token, $limit, $page);
            }
        }

        if (!$res['successful']) {
            Log::error("片商 [{$channel->slug}] API 请求失败 [{$res['status']}]: " . Str::limit($res['body'], 300));
            return [
                'success'       => false,
                'channel'       => $channel->slug,
                'message'       => "API 请求失败 [HTTP {$res['status']}]",
                'success_count' => 0,
                'failed_count'  => 1,
                'skipped_count' => 0,
            ];
        }

        $data = json_decode($res['body'], true);
        if (!isset($data['result']) || !is_array($data['result'])) {
            Log::error("片商 [{$channel->slug}] API 返回缺少 result 字段: " . Str::limit($res['body'], 200));
            return [
                'success'       => false,
                'channel'       => $channel->slug,
                'message'       => '返回数据结构异常，缺少 result 节点',
                'success_count' => 0,
                'failed_count'  => 1,
                'skipped_count' => 0,
            ];
        }

        $successCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($data['result'] as $item) {
            $type = (string) ($item['type'] ?? '');
            $orientation = strtolower((string) ($item['sexualOrientation'] ?? ''));

            // 1. 过滤非正片类型（预告片、花絮、剪辑合集等）
            if ($type !== 'scene') {
                $skippedCount++;
                continue;
            }

            // 2. 过滤 Gay 同性题材
            if ($orientation === 'gay') {
                $skippedCount++;
                continue;
            }

            // 3. 过滤 Trans（变性）题材：针对 sexualOrientation 为 trans/transgender 或演员列表中标注 trans 的视频
            $isTrans = in_array($orientation, ['trans', 'transgender'], true);
            if (!$isTrans && !empty($item['actors']) && is_array($item['actors'])) {
                foreach ($item['actors'] as $actor) {
                    if (is_array($actor) && strtolower((string) ($actor['gender'] ?? '')) === 'trans') {
                        $isTrans = true;
                        break;
                    }
                }
            }

            if ($isTrans) {
                $skippedCount++;
                continue;
            }

            try {
                $this->saveVideoItem($channel, $item);
                $successCount++;
            } catch (Throwable $e) {
                Log::error("处理视频 [{$item['id']}] 异常: " . $e->getMessage(), [
                    'item_id' => $item['id'] ?? null,
                    'exception' => $e,
                ]);
                $this->recordFailedItem($channel, $item, $e, $page);
                $failedCount++;
            }
        }

        $meta = $data['meta'] ?? [];
        $total = isset($meta['total']) ? (int) $meta['total'] : null;
        $totalPages = ($total !== null && $limit > 0) ? (int) ceil($total / $limit) : null;

        if ($successCount > 0) {
            $channel->update(['video_num' => Video::where('channel_id', $channel->id)->count()]);
        }

        Log::info("片商 [{$channel->slug}] 第 {$page} 页爬取完成！成功: {$successCount}, 失败: {$failedCount}, 跳过: {$skippedCount}" . ($total !== null ? " (总计 {$total} 条 / {$totalPages} 页)" : ""));

        return [
            'success'       => true,
            'channel'       => $channel->slug,
            'page'          => $page,
            'limit'         => $limit,
            'total_items'   => count($data['result']),
            'total'         => $total,
            'total_pages'   => $totalPages,
            'meta'          => $meta,
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'skipped_count' => $skippedCount,
        ];
    }

    /**
     * 单个视频及其关联数据的持久化入库
     */
    protected function saveVideoItem(Channel $channel, array $item): Video
    {
        $title = trim($item['title'] ?? '');
        $sourceSlug = Str::slug($title);
        $sourceId = (string) ($item['id'] ?? '');
        if ($sourceId === '') {
            throw new \InvalidArgumentException("视频缺少有效 id 字段");
        }
        $sourceUUID = $this->handleSourceUUID($channel->slug, $sourceId, $sourceSlug);
        $releaseAt = $this->handleReleaseAt($item['dateReleased'] ?? null);

        $actorsData = is_array($item['actors'] ?? null) ? $item['actors'] : [];
        $isTransModel = false;
        $femaleActors = [];

        foreach ($actorsData as $actor) {
            if (!is_array($actor)) {
                continue;
            }
            $gender = $actor['gender'] ?? '';
            if ($gender === 'trans') {
                $isTransModel = true;
            }
            $femaleActors[] = [
                'id'     => $actor['id'] ?? 0,
                'name'   => $actor['name'] ?? '',
                'slug'   => Str::slug($actor['name'] ?? ''),
                'gender' => $this->handleGender($gender),
            ];
        }

        // 生成视频代码 (Video Code)，基于 sourceSlug
        $videoCode = $this->handleVideoCode($item, $femaleActors, $sourceSlug, $releaseAt);

        // 统一 slug 格式：片商 slug + '-' + 源 id + '-' + 源 slug
        $slug = "{$channel->slug}-{$sourceId}-{$sourceSlug}";

        // 整理 videos 主表数据
        $videoData = [
            'name'               => $title,
            'slug'               => $slug,
            'source_uuid'        => $sourceUUID,
            'preview'            => $this->handleVideoPreview($item),
            'release_at'         => $releaseAt,
            'channel_id'         => $channel->id,
            'video_code'         => $videoCode,
            'is_4k'              => $this->handleIs4k($item),
            'is_trans_model'     => $isTransModel,
            'sexual_orientation' => $this->handleSexualOrientation($item['sexualOrientation'] ?? null),
            'status'             => 1,
        ];

        $listImg = $this->handleListImg($item, $channel->slug, $sourceId);
        if (!empty($listImg)) {
            $videoData['list_img'] = $listImg;
        }

        // 整理 video_details 附表数据
        $videoDetailData = [
            'video_urls'  => $this->handleVideoUrls($item),
            'description' => $item['description'] ?? '',
        ];

        $movieLength = $this->handleMovieLength($item);
        if ($movieLength > 0) {
            $videoDetailData['movie_length'] = $movieLength;
        }

        $listImgLargeMeta = $this->handleListImgLargeMeta($item, $channel->slug, $sourceId);
        if (!empty($listImgLargeMeta)) {
            $videoDetailData['list_img_large_meta'] = $listImgLargeMeta;
        }

        $screenImg = $this->handleScreenImg($item, $channel->slug, $sourceId);
        if (!empty($screenImg)) {
            $videoDetailData['screen_img'] = $screenImg;
        }

        return DB::transaction(function () use ($sourceUUID, $videoData, $videoDetailData, $femaleActors, $item, $title, $channel, $sourceId, $sourceSlug, $videoCode, $listImg, $releaseAt, $isTransModel) {
            // 1. 严格以 source_uuid 作为唯一业务标识查询现有视频记录（兼容新规范及旧版本无片商前缀格式）
            $existingVideo = Video::where('channel_id', $channel->id)
                ->where(function ($q) use ($sourceUUID, $sourceId) {
                    $q->where('source_uuid', $sourceUUID)
                      ->orWhere('source_uuid', 'LIKE', "{$sourceId}:%")
                      ->orWhere('source_uuid', $sourceId);
                })
                ->first();

            if ($existingVideo) {
                $targetPrefix = "{$channel->slug}-{$sourceId}-";
                $updates = [];

                // 1) 确保统一规范化升级 source_uuid（补齐片商前缀）
                if ($existingVideo->source_uuid !== $sourceUUID) {
                    $updates['source_uuid'] = $sourceUUID;
                }

                // 2) 标题 (name) 绝对保护：
                // 库中已有有效名称坚决不覆盖（防止被接口空串、截断或脏数据污染）；仅在库中为空时补充
                if (empty(trim($existingVideo->name)) && !empty(trim($title))) {
                    $updates['name'] = $title;
                }

                // 3) slug 规范前缀对齐保护：保留旧 slug 的核心部分，仅对齐规范前缀
                if (empty($existingVideo->slug)) {
                    $updates['slug'] = $videoData['slug'];
                } elseif (!str_starts_with($existingVideo->slug, $targetPrefix)) {
                    $rawSlug = preg_replace('/^(?:' . preg_quote($channel->slug, '/') . '-)?(?:' . preg_quote($sourceId, '/') . '-)?/i', '', $existingVideo->slug);
                    $updates['slug'] = "{$channel->slug}-{$sourceId}-{$rawSlug}";
                }

                // 4) video_code：新编号规则有效时才进行升级，防止退化为 P1S 默认编号
                if (!empty($videoCode) && $existingVideo->video_code !== $videoCode) {
                    if (!str_starts_with($videoCode, 'P1S.') || empty($existingVideo->video_code)) {
                        $updates['video_code'] = $videoCode;
                    }
                }

                // 5) 封面图片 (list_img)：库中已有有效图片坚决不覆盖；库中为空且新数据有效时补充
                if (empty($existingVideo->list_img) && !empty($listImg)) {
                    $updates['list_img'] = $listImg;
                }

                // 6) 视频预览 (preview)：库中已有预览坚决不覆盖；库中为空且新数据有效时补充
                if (empty($existingVideo->preview) && !empty($videoData['preview'])) {
                    $updates['preview'] = $videoData['preview'];
                }

                // 7) 发布时间 (release_at)：仅在库中为空且新数据有效时补充
                if (empty($existingVideo->release_at) && !empty($releaseAt)) {
                    $updates['release_at'] = $releaseAt;
                }

                // 8) 4K 画质 (is_4k)：单向升级，绝不从 true 降级为 false
                if (!$existingVideo->is_4k && !empty($videoData['is_4k'])) {
                    $updates['is_4k'] = true;
                }

                // 9) 变性人演员 (is_trans_model)：单向升级，绝不从 true 降级为 false
                if (!$existingVideo->is_trans_model && !empty($isTransModel)) {
                    $updates['is_trans_model'] = true;
                }

                // 10) 性取向 (sexual_orientation)：库中为未知 (0) 时补充有效值
                if (($existingVideo->sexual_orientation === 0 || empty($existingVideo->sexual_orientation)) && !empty($videoData['sexual_orientation'])) {
                    $updates['sexual_orientation'] = $videoData['sexual_orientation'];
                }

                if (!empty($updates)) {
                    $existingVideo->update($updates);
                }
                $video = $existingVideo;
            } else {
                // 新建记录时若标题为空，兜底使用 video_code
                if (empty(trim($title))) {
                    $videoData['name'] = $videoData['video_code'] ?? $sourceUUID;
                    $videoData['slug'] = "{$channel->slug}-{$sourceId}-" . Str::slug($videoData['name']);
                }
                $video = Video::create($videoData);
            }

            // 2. 写入或更新详情附表（逐字段防御性补充）
            $existingDetail = VideoDetail::where('video_id', $video->id)->first();
            if ($existingDetail) {
                $detailUpdates = [];

                // 简介保护：库中已有简介坚决不覆盖（防止冲掉已翻译或更完整的旧简介）；库中为空才补充
                if (empty(trim($existingDetail->description ?? '')) && !empty(trim($videoDetailData['description'] ?? ''))) {
                    $detailUpdates['description'] = $videoDetailData['description'];
                }

                // 视频时长：库中若缺失 (0) 且新数据 > 0 时才更新补充
                if ((empty($existingDetail->movie_length) || $existingDetail->movie_length == 0) && !empty($videoDetailData['movie_length'])) {
                    $detailUpdates['movie_length'] = $videoDetailData['movie_length'];
                }

                // 播放直链：库中为空且新数据有效时才补充
                if (empty($existingDetail->video_urls) && !empty($videoDetailData['video_urls'])) {
                    $detailUpdates['video_urls'] = $videoDetailData['video_urls'];
                }

                // 剧照列表：库中已有剧照绝不被空数组覆盖；仅在库中为空且新剧照有效时补充
                if (empty($existingDetail->screen_img) && !empty($videoDetailData['screen_img'])) {
                    $detailUpdates['screen_img'] = $videoDetailData['screen_img'];
                }

                // 大图元数据：库中为空且新数据有效时才补充
                if (empty($existingDetail->list_img_large_meta) && !empty($videoDetailData['list_img_large_meta'])) {
                    $detailUpdates['list_img_large_meta'] = $videoDetailData['list_img_large_meta'];
                }

                if (!empty($detailUpdates)) {
                    $existingDetail->update($detailUpdates);
                }
            } else {
                $videoDetailData['video_id'] = $video->id;
                VideoDetail::create($videoDetailData);
            }

            // 3. 处理关联演员
            $actorIds = [];
            foreach ($femaleActors as $actor) {
                if (empty($actor['name'])) {
                    continue;
                }
                $cleanName = preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', trim($actor['name']));
                $dbActor = Actor::firstOrCreate(
                    ['slug' => $actor['slug']],
                    [
                        'name'           => $cleanName,
                        'slug'           => $actor['slug'],
                        'original_id'    => (int) $actor['id'],
                        'gender'         => $actor['gender'],
                        'is_trans_model' => ($actor['gender'] === 3),
                    ]
                );
                $actorIds[] = $dbActor->id;
            }
            if (!empty($actorIds)) {
                $video->actors()->syncWithoutDetaching($actorIds);
            }

            // 4. 处理关联分类 / 标签
            $categoryIds = [];
            $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
            foreach ($tags as $tag) {
                if (!is_array($tag) || empty($tag['name'])) {
                    continue;
                }
                $catSlug = Str::slug($tag['name']);
                if (empty($catSlug)) {
                    continue;
                }
                $dbCategory = Category::firstOrCreate(
                    ['slug' => $catSlug],
                    [
                        'name'    => $tag['name'],
                        'name_zh' => $tag['name'],
                        'slug'    => $catSlug,
                    ]
                );
                $categoryIds[] = $dbCategory->id;
            }
            if (!empty($categoryIds)) {
                $video->categories()->syncWithoutDetaching($categoryIds);
            }

            // 5. 处理关联站点 / 系列 (Site)
            $collections = is_array($item['collections'] ?? null) ? $item['collections'] : [];
            $siteIds = [];
            foreach ($collections as $collection) {
                if (!is_array($collection) || empty($collection['name'])) {
                    continue;
                }
                $siteName = trim($collection['name']);
                $siteSlug = Str::slug($siteName);
                if (empty($siteSlug)) {
                    continue;
                }

                $site = Site::firstOrCreate(
                    [
                        'channel_id' => $channel->id,
                        'slug'       => $siteSlug,
                    ],
                    [
                        'name'        => $siteName,
                        'short_name'  => $collection['shortName'] ?? null,
                        'original_id' => $collection['id'] ?? null,
                        'custom_url'  => $collection['customUrl'] ?? null,
                        'status'      => 1,
                    ]
                );

                $siteUpdates = [];
                if (empty($site->original_id) && !empty($collection['id'])) {
                    $siteUpdates['original_id'] = $collection['id'];
                }
                if (empty($site->short_name) && !empty($collection['shortName'])) {
                    $siteUpdates['short_name'] = $collection['shortName'];
                }
                if (empty($site->custom_url) && !empty($collection['customUrl'])) {
                    $siteUpdates['custom_url'] = $collection['customUrl'];
                }
                if (!empty($siteUpdates)) {
                    $site->update($siteUpdates);
                }

                $siteIds[] = $site->id;
            }
            if (!empty($siteIds)) {
                $video->sites()->syncWithoutDetaching($siteIds);
                foreach ($siteIds as $siteId) {
                    $count = DB::table('site_video')->where('site_id', $siteId)->count();
                    Site::where('id', $siteId)->update(['video_num' => $count]);
                }
            }

            return $video;
        });
    }

    /**
     * 请求 Project1Service API 接口
     */
    protected function requestApiReleases(Channel $channel, string $token, int $limit, int $page = 1): array
    {
        try {
            $origin = rtrim($channel->official_website_url ?: 'https://www.project1service.com', '/');
            $page = max(1, $page);
            $offset = ($page - 1) * $limit;

            $response = Http::withHeaders([
                'user-agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'referer'         => $origin . '/',
                'origin'          => $origin,
                'instance'        => $token,
                'accept-language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                'accept'          => 'application/json, text/plain, */*',
                'authority'       => 'site-api.project1service.com',
            ])->timeout(30)->get('https://site-api.project1service.com/v2/releases', [
                'adaptiveStreamingOnly' => 'false',
                'orderBy'               => '-dateReleased',
                'type'                  => 'scene',
                'limit'                 => $limit,
                'offset'                => $offset,
            ]);

            return [
                'successful' => $response->successful(),
                'status'     => $response->status(),
                'body'       => $response->body(),
            ];
        } catch (Throwable $e) {
            Log::error("Project1 API 网络请求异常: " . $e->getMessage());
            return [
                'successful' => false,
                'status'     => 500,
                'body'       => '',
            ];
        }
    }

    /**
     * 解析片商 Token
     * 优先级：缓存/Redis -> 自动从片商官网页面抓取 JWT
     *
     * @param Channel|string $channel 片商实例或 slug
     * @param bool $forceRefresh 是否强制从官网重新抓取
     * @return string|null
     */
    public function resolveChannelToken(Channel|string $channel, bool $forceRefresh = false): ?string
    {
        $channelModel = is_string($channel)
            ? Channel::where('slug', $channel)->where('data_crawl_type', 1)->first()
            : $channel;

        if (!$channelModel) {
            return null;
        }

        $channelSlug = $channelModel->slug;
        $key = "{$channelSlug}:token";

        if (!$forceRefresh) {
            // 1. 尝试从 Redis 读取
            try {
                if (class_exists(Redis::class)) {
                    $token = Redis::get($key);
                    if (!empty($token)) {
                        return $token;
                    }
                }
            } catch (Throwable $e) {
                // Redis 异常时平滑降级
            }

            // 2. 尝试从通用 Cache 读取
            $token = Cache::get($key);
            if (!empty($token)) {
                return $token;
            }
        }

        // 3. 缓存不存在或强制刷新，从官网抓取
        return $this->fetchAndStoreChannelToken($channelModel);
    }

    /**
     * 从片商官方站点抓取 JWT Token 并按有效期自动缓存
     */
    public function fetchAndStoreChannelToken(Channel $channel): ?string
    {
        $siteUrl = $channel->official_website_url;
        if (empty($siteUrl)) {
            Log::error("片商 [{$channel->slug}] 未配置 official_website_url，无法自动提取 Token");
            return null;
        }

        Log::info("正在从片商 [{$channel->slug}] 官网 ({$siteUrl}) 获取 API Token...");

        try {
            $response = Http::retry(3, 200)
                ->withHeaders([
                    'user-agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                    'accept-language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'Connection'      => 'keep-alive',
                ])
                ->timeout(20)
                ->get($siteUrl);

            if (!$response->successful()) {
                Log::error("请求片商 [{$channel->slug}] 官网失败 [{$response->status()}]");
                return null;
            }

            $htmlCode = $response->body();

            // 正则匹配 HTML 中的 jwt
            if (!preg_match('/"jwt"\s*:\s*"([^"]+)"/', $htmlCode, $matches)) {
                Log::error("片商 [{$channel->slug}] 官网页面中未匹配到 jwt 字段");
                return null;
            }

            $token = $matches[1];

            // 解析 JWT Payload 获取 exp 过期时间
            $expiredSecond = 7200; // 默认 2 小时
            $tokenParts = explode('.', $token);
            if (isset($tokenParts[1])) {
                $payloadBase64 = strtr($tokenParts[1], '-_', '+/');
                $payload = json_decode(base64_decode($payloadBase64), true);
                if (isset($payload['exp']) && is_numeric($payload['exp'])) {
                    $remaining = (int) $payload['exp'] - time();
                    if ($remaining > 21600) {
                        // 提前 6 小时过期留足缓冲
                        $expiredSecond = $remaining - 21600;
                    } elseif ($remaining > 300) {
                        $expiredSecond = (int) ($remaining * 0.8);
                    } else {
                        $expiredSecond = 3600;
                    }
                }
            }

            // 存入 Redis 与 Cache
            $key = "{$channel->slug}:token";
            try {
                if (class_exists(Redis::class)) {
                    Redis::setex($key, $expiredSecond, $token);
                }
            } catch (Throwable $e) {
                // Redis 异常时静默降级
            }
            Cache::put($key, $token, $expiredSecond);

            Log::info("片商 [{$channel->slug}] Token 抓取成功并已缓存，有效期 {$expiredSecond} 秒");

            return $token;
        } catch (Throwable $e) {
            Log::error("提取片商 [{$channel->slug}] Token 异常: " . $e->getMessage());
            return null;
        }
    }

    protected function handleSourceUUID(string $channelSlug, string $id, string $slug): string
    {
        return "{$channelSlug}:{$id}:{$slug}";
    }

    protected function handleReleaseAt(?string $utc): string
    {
        if (empty($utc)) {
            return now()->toDateTimeString();
        }

        try {
            return Carbon::parse($utc)->setTimezone('Asia/Shanghai')->toDateTimeString();
        } catch (Throwable $e) {
            return now()->toDateTimeString();
        }
    }

    protected function handleGender(?string $gender): int
    {
        return match ($gender) {
            'male'   => 1,
            'female' => 2,
            'trans'  => 3,
            default  => 0,
        };
    }

    protected function handleSexualOrientation(?string $orientation): int
    {
        return match ($orientation) {
            'straight' => 1,
            'lesbian'  => 2,
            default    => 0,
        };
    }

    protected function handleVideoCode(array $item, array $femaleActors, string $slug, string $releaseAt): string
    {
        $rawCollectionName = $item['collections'][0]['name'] ?? 'P1S';
        // 移除非字母数字字符（如逗号、引号、空格等）并保留原始大小写
        // 例如 "Big Tits, Round Asses" -> "BigTitsRoundAsses", "Dad's Love Porn" -> "DadsLovePorn", "BangBros 18" -> "BangBros18"
        $collectionPrefix = preg_replace('/[^\p{L}\p{N}]+/u', '', $rawCollectionName);
        if (empty($collectionPrefix)) {
            $collectionPrefix = 'P1S';
        }
        $videoCode = $collectionPrefix . '.' . date('y.m.d', strtotime($releaseAt)) . '.';

        $slugWithPoint = [];
        foreach ($femaleActors as $actor) {
            if (($actor['gender'] ?? 0) !== 1) { // 非男性
                $name = trim($actor['name'] ?? '');
                if (!empty($name)) {
                    $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', '', $name);
                    $slugWithPoint[] = str_replace(' ', '.', trim(preg_replace('/\s+/', ' ', $clean)));
                }
            }
        }

        if (!empty($slugWithPoint)) {
            $videoCode .= implode('.And.', array_filter($slugWithPoint)) . '.';
        }

        $parts = explode('-', $slug);
        $convertedParts = [];
        foreach ($parts as $p) {
            $cleanPart = preg_replace('/[^\p{L}\p{N}]+/u', '', $p);
            if (!empty($cleanPart)) {
                $convertedParts[] = ucfirst($cleanPart);
            }
        }
        $videoCode .= implode('.', $convertedParts);

        return $videoCode;
    }

    /**
     * 提取视频播放时长（秒）
     */
    protected function handleMovieLength(array $item): int
    {
        return (int) ($item['videos']['mediabook']['length'] ?? $item['length'] ?? 0);
    }

    /**
     * 判断视频是否支持 4K 超高清
     */
    protected function handleIs4k(array $item): bool
    {
        // 1. 检查标签列表
        $tags = $item['tags'] ?? [];
        if (is_array($tags)) {
            foreach ($tags as $tag) {
                $tagName = strtolower(trim($tag['name'] ?? ''));
                if ($tagName === '4k' || str_contains($tagName, '4k') || str_contains($tagName, '2160p')) {
                    return true;
                }
            }
        }

        // 2. 检查视频文件格式
        $videos = $item['videos'] ?? [];
        if (is_array($videos)) {
            foreach ($videos as $video) {
                $files = $video['files'] ?? [];
                if (is_array($files)) {
                    foreach (array_keys($files) as $quality) {
                        if (stripos((string) $quality, '4k') !== false || stripos((string) $quality, '2160') !== false) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * 计算并格式化文件相对与本地路径（去除 Query 参数并使用哈希存储）
     */
    protected function handleFilesUrl(?string $url, string $channel, string $sourceId): array
    {
        if (empty($url)) {
            return [
                'dbUrl'    => '',
                'localUrl' => '',
            ];
        }

        $cleanUrl = explode('?', $url)[0];
        $fileName = sha1($cleanUrl);
        $fileExt = pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION) ?: 'jpg';
        $subPath = "{$channel}/{$sourceId}/{$fileName}.{$fileExt}";

        return [
            'dbUrl'    => "/images/videos/{$subPath}",
            'localUrl' => public_path("images/videos/{$subPath}"),
        ];
    }

    protected function handleListImg(array $item, string $channel, string $sourceId): array
    {
        $poster = $item['images']['poster'][0] ?? null;
        if (!$poster || !isset($poster['xs'], $poster['md'], $poster['sm'], $poster['lg'], $poster['xl'])) {
            return [];
        }

        $xsWebp = $poster['xs']['urls']['webp'] ?? $poster['xs']['url'];
        $mdWebp = $poster['md']['urls']['webp'] ?? $poster['md']['url'];
        $smWebp = $poster['sm']['urls']['webp'] ?? $poster['sm']['url'];
        $lgWebp = $poster['lg']['urls']['webp'] ?? $poster['lg']['url'];
        $xlWebp = $poster['xl']['urls']['webp'] ?? $poster['xl']['url'];

        return [
            [
                'src_source'         => $smWebp,
                'placeholder_source' => $xsWebp,
                'width'              => $poster['sm']['width'] ?? 0,
                'height'             => $poster['sm']['height'] ?? 0,
                'highdpi'            => [
                    'double_source' => $mdWebp,
                ],
            ],
            [
                'src_source'         => $lgWebp,
                'placeholder_source' => $xsWebp,
                'width'              => $poster['lg']['width'] ?? 0,
                'height'             => $poster['lg']['height'] ?? 0,
                'highdpi'            => [
                    'double_source' => $xlWebp,
                ],
            ],
        ];
    }

    protected function handleListImgLargeMeta(array $item, string $channel, string $sourceId): array
    {
        $poster = $item['images']['poster'][0] ?? null;
        if (!$poster || !isset($poster['xs'], $poster['md'], $poster['sm'], $poster['xx'])) {
            return [];
        }

        $xsWebp = $poster['xs']['urls']['webp'] ?? $poster['xs']['url'];
        $smWebp = $poster['sm']['urls']['webp'] ?? $poster['sm']['url'];
        $mdWebp = $poster['md']['urls']['webp'] ?? $poster['md']['url'];
        $xxWebp = $poster['xx']['urls']['webp'] ?? $poster['xx']['url'];

        return [
            [
                'src_source'         => $smWebp,
                'placeholder_source' => $xsWebp,
                'width'              => $poster['sm']['width'] ?? 0,
                'height'             => $poster['sm']['height'] ?? 0,
                'media'              => '(max-width: 360px)',
                'highdpi'            => [
                    'double_source' => $mdWebp,
                ],
            ],
            [
                'src_source'         => $mdWebp,
                'placeholder_source' => $xsWebp,
                'width'              => $poster['md']['width'] ?? 0,
                'height'             => $poster['md']['height'] ?? 0,
                'media'              => '(min-width: 361px) and (max-width: 768px)',
                'highdpi'            => [
                    'double_source' => $xxWebp,
                ],
            ],
            [
                'src_source'         => $xxWebp,
                'placeholder_source' => $xsWebp,
                'width'              => $poster['xx']['width'] ?? 0,
                'height'             => $poster['xx']['height'] ?? 0,
                'media'              => '(min-width: 769px)',
                'highdpi'            => [
                    'double_source' => $xxWebp,
                ],
            ],
        ];
    }

    protected function handleScreenImg(array $item, string $channel, string $sourceId): array
    {
        $posters = $item['images']['poster'] ?? [];
        if (empty($posters) || !is_array($posters)) {
            return [];
        }

        $screenImgArr = [];
        for ($i = 0; $i <= 5; $i++) {
            if (isset($posters[$i]['sm']['urls']['webp'], $posters[$i]['xx']['urls']['webp'])) {
                // 🎯 project1Video 源站直链免防盗链，不存储本地/云盘，仅保留 source 字段与宽高，彻底移除冗余 screen_img_ 前缀与空本地字段
                $screenImgArr[] = [
                    'source_url'      => $posters[$i]['sm']['urls']['webp'],
                    'width'           => $posters[$i]['sm']['width'] ?? 0,
                    'height'          => $posters[$i]['sm']['height'] ?? 0,
                    'full_source_url' => $posters[$i]['xx']['urls']['webp'],
                    'full_width'      => $posters[$i]['xx']['width'] ?? 0,
                    'full_height'     => $posters[$i]['xx']['height'] ?? 0,
                ];
            }
        }

        return count($screenImgArr) >= 5 ? $screenImgArr : [];
    }

    protected function handleVideoPreview(array $item): string
    {
        if (isset($item['videos']['mediabook']['files']['320p']['urls']['view'])) {
            return '1<' . $item['videos']['mediabook']['files']['320p']['urls']['view'];
        }

        $cardMain = $item['images']['card_main_rect'] ?? [];
        if (count($cardMain) >= 2) {
            $previewImages = [];
            $max = min(6, count($cardMain));
            for ($i = 0; $i < $max; $i++) {
                if (isset($cardMain[$i]['lg']['urls']['webp'])) {
                    $previewImages[] = $cardMain[$i]['lg']['urls']['webp'];
                }
            }
            if (!empty($previewImages)) {
                return '3<' . implode(',', $previewImages);
            }
        }

        return '';
    }

    protected function handleVideoUrls(array $item): string
    {
        return $item['videos']['mediabook']['files']['720p']['urls']['view']
            ?? $item['videos']['mediabook']['files']['320p']['urls']['view']
            ?? '';
    }

    /**
     * 持久化记录抓取或入库失败的视频原始 Payload 到独立文件
     */
    protected function recordFailedItem(Channel $channel, array $item, Throwable $exception, int $page): void
    {
        try {
            $dir = storage_path('logs/crawler_failures');
            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $filePath = "{$dir}/{$channel->slug}.jsonl";
            $record = [
                'time'       => now()->toDateTimeString(),
                'channel'    => $channel->slug,
                'page'       => $page,
                'item_id'    => $item['id'] ?? null,
                'item_title' => $item['title'] ?? null,
                'error'      => $exception->getMessage(),
                'raw_item'   => $item,
            ];

            @file_put_contents($filePath, json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        } catch (Throwable $e) {
            // 忽略持久化失败本身，避免二次中断
        }
    }

    /**
     * 严格校验片商是否符合 Project1 体系规范（自检 / 冒烟测试）
     *
     * @param Channel|string $channel 片商模型或 slug
     * @return array{
     *     success: bool,
     *     slug: string,
     *     checks: array<string, array{status: bool, message: string}>,
     *     error: ?string
     * }
     */
    public function verifyChannel(Channel|string $channel): array
    {
        $slug = $channel instanceof Channel ? $channel->slug : (string) $channel;
        $channelModel = $channel instanceof Channel ? $channel : Channel::where('slug', $slug)->first();

        $results = [
            'success' => false,
            'slug'    => $slug,
            'checks'  => [],
            'error'   => null,
        ];

        // 检查项 1：是否在受支持字典中登记
        $isRegistered = self::isSupported($slug);
        $results['checks']['registered'] = [
            'status'  => $isRegistered,
            'message' => $isRegistered ? "已在 SUPPORTED_CHANNELS 单一真理源登记" : "未在 SUPPORTED_CHANNELS 登记",
        ];
        if (!$isRegistered) {
            $results['error'] = "片商 [{$slug}] 未在 SUPPORTED_CHANNELS 字典中注册";
            return $results;
        }

        // 检查项 2：数据库记录是否存在
        if (!$channelModel) {
            $results['checks']['db_record'] = ['status' => false, 'message' => "数据库 channels 表未找到 slug=[{$slug}] 的记录"];
            $results['error'] = "数据库 channels 表中无此片商，请先插入记录";
            return $results;
        }
        $results['checks']['db_record'] = ['status' => true, 'message' => "数据库记录存在 (ID: {$channelModel->id}, 名称: {$channelModel->name})"];

        // 检查项 3：data_crawl_type 是否为 1
        $isType1 = (int) $channelModel->data_crawl_type === 1;
        $results['checks']['crawl_type'] = [
            'status'  => $isType1,
            'message' => $isType1 ? "采集模式正确 (data_crawl_type = 1: 直链源站)" : "采集模式不匹配 (当前为 {$channelModel->data_crawl_type}，必须为 1)",
        ];
        if (!$isType1) {
            $results['error'] = "片商 [{$slug}] 的 data_crawl_type 必须设置为 1";
            return $results;
        }

        // 检查项 4：official_website_url 是否有效
        $siteUrl = trim((string) $channelModel->official_website_url);
        $hasUrl = !empty($siteUrl) && filter_var($siteUrl, FILTER_VALIDATE_URL) !== false;
        $results['checks']['website_url'] = [
            'status'  => $hasUrl,
            'message' => $hasUrl ? "官网 URL 合法 ({$siteUrl})" : "未配置有效的 official_website_url",
        ];
        if (!$hasUrl) {
            $results['error'] = "片商 [{$slug}] 未配置有效的 official_website_url，Token 提取将无法执行";
            return $results;
        }

        // 检查项 5：官网连通性与 JWT Token 提取测试
        $token = $this->fetchAndStoreChannelToken($channelModel);
        $hasToken = !empty($token);
        $results['checks']['jwt_token'] = [
            'status'  => $hasToken,
            'message' => $hasToken ? "官网 Token 提取成功 (JWT 长度: " . strlen($token) . ")" : "未能从官网提取到 JWT Token",
        ];
        if (!$hasToken) {
            $results['error'] = "无法从官网 ({$siteUrl}) 提取到有效 JWT Token，请检查网页结构或防爬机制";
            return $results;
        }

        // 检查项 6：API 接口实际连通性与数据探测 (releases limit=1)
        try {
            $apiRes = $this->requestApiReleases($channelModel, $token, limit: 1, page: 1);

            // 如果首次请求遇到 401/403，尝试强制刷新官网 Token 并重试一次
            if (in_array($apiRes['status'], [401, 403])) {
                $newToken = $this->resolveChannelToken($channelModel, true);
                if (!empty($newToken)) {
                    $token = $newToken;
                    $apiRes = $this->requestApiReleases($channelModel, $token, limit: 1, page: 1);
                }
            }

            if (!$apiRes['successful']) {
                $snippet = Str::limit(strip_tags($apiRes['body']), 120);
                $results['checks']['api_connection'] = [
                    'status'  => false,
                    'message' => "API 请求失败 [HTTP {$apiRes['status']}]" . ($snippet ? ": {$snippet}" : ''),
                ];
                $results['error'] = "Project1 API 请求未通过 [HTTP {$apiRes['status']}]";
                return $results;
            }

            $data = json_decode($apiRes['body'], true);
            $hasApiReleases = is_array($data) && isset($data['result']) && is_array($data['result']);
            $count = $hasApiReleases ? count($data['result']) : 0;
            $metaTotal = $data['meta']['total'] ?? null;
            $totalDesc = $metaTotal !== null ? "，该片商源站共有约 " . number_format($metaTotal) . " 部视频" : "";

            $results['checks']['api_connection'] = [
                'status'  => $hasApiReleases,
                'message' => $hasApiReleases ? "Project1 API 探测成功（冒烟探活返回 {$count} 条样本{$totalDesc}）" : "Project1 API 响应未包含 result 列表: " . Str::limit($apiRes['body'], 120),
            ];
            if (!$hasApiReleases) {
                $results['error'] = "Project1 API 请求未返回有效数据，请检查片商权限或接口限制";
                return $results;
            }
        } catch (Throwable $e) {
            $results['checks']['api_connection'] = ['status' => false, 'message' => "API 请求发生异常: " . $e->getMessage()];
            $results['error'] = "API 连通测试异常: " . $e->getMessage();
            return $results;
        }

        $results['success'] = true;
        return $results;
    }
}
