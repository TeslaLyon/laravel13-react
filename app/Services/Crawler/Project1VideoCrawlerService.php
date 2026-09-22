<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\Category;
use App\Models\Channel;
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
     * 执行指定片商或所有片商的视频列表爬取任务
     *
     * @param string|null $channelSlug 片商 slug（若为空则爬取所有 data_crawl_type=1 的片商）
     * @param int $limit 每次拉取视频条数
     * @param string|null $explicitToken 显式传入的 token，未传则从 Redis/Cache 读取或自动从官网抓取
     * @param bool $forceRefreshToken 是否强制刷新 Token
     * @return array 任务结果统计
     */
    public function crawlVideos(?string $channelSlug = null, int $limit = 24, ?string $explicitToken = null, bool $forceRefreshToken = false): array
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

            return $this->crawlSingleChannel($channel, $limit, $explicitToken, $forceRefreshToken);
        }

        // 未指定片商时，遍历所有配置为 data_crawl_type = 1 的片商
        $channels = Channel::where('data_crawl_type', 1)->get();
        $totalStats = [
            'total_channels'     => $channels->count(),
            'channels_processed' => 0,
            'success_count'      => 0,
            'failed_count'       => 0,
            'skipped_count'      => 0,
        ];

        foreach ($channels as $channel) {
            $result = $this->crawlSingleChannel($channel, $limit, $explicitToken, $forceRefreshToken);
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
    public function crawlSingleChannel(Channel $channel, int $limit = 24, ?string $explicitToken = null, bool $forceRefreshToken = false): array
    {
        $token = $explicitToken ?: $this->resolveChannelToken($channel, $forceRefreshToken);

        if (empty($token)) {
            $msg = "片商 [{$channel->slug}] 无法获取到有效的 API Token（自动抓取失败且无可用缓存）";
            Log::warning($msg);
            return [
                'success'       => false,
                'channel'       => $channel->slug,
                'message'       => $msg,
                'success_count' => 0,
                'failed_count'  => 0,
                'skipped_count' => 0,
            ];
        }

        Log::info("开始爬取片商 [{$channel->slug}] 视频列表，Limit: {$limit}");

        $res = $this->requestApiReleases($channel, $token, $limit);

        // 如果未通过认证 (401 或 403) 且非显式指定的 Token，自动刷新一次 Token 并重试
        if (!$explicitToken && in_array($res['status'], [401, 403])) {
            Log::warning("片商 [{$channel->slug}] Token 失效或过期 (HTTP {$res['status']})，正在自动刷新并重试...");
            $token = $this->resolveChannelToken($channel, true);
            if (!empty($token)) {
                $res = $this->requestApiReleases($channel, $token, $limit);
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
            // 过滤非 scene 类型或 gay 视频
            if (($item['type'] ?? '') !== 'scene' || ($item['sexualOrientation'] ?? '') === 'gay') {
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
                $failedCount++;
            }
        }

        Log::info("片商 [{$channel->slug}] 爬取完成！成功: {$successCount}, 失败: {$failedCount}, 跳过: {$skippedCount}");

        return [
            'success'       => true,
            'channel'       => $channel->slug,
            'total_items'   => count($data['result']),
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
        $slug = Str::slug($title);
        $sourceId = (string) $item['id'];
        $sourceUUID = $this->handleSourceUUID($sourceId, $slug);
        $releaseAt = $this->handleReleaseAt($item['dateReleased'] ?? null);

        $actorsData = $item['actors'] ?? [];
        $isTransModel = false;
        $femaleActors = [];

        foreach ($actorsData as $actor) {
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

        // 生成视频代码 (Video Code)
        $videoCode = $this->handleVideoCode($item, $femaleActors, $slug, $releaseAt);

        // 整理 videos 主表数据
        $videoData = [
            'name'               => $title,
            'slug'               => $slug,
            'source_uuid'        => $sourceUUID,
            'preview'            => $this->handleVideoPreview($item),
            'release_at'         => $releaseAt,
            'channel_id'         => $channel->id,
            'video_code'         => $videoCode,
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

        $listImgLargeMeta = $this->handleListImgLargeMeta($item, $channel->slug, $sourceId);
        if (!empty($listImgLargeMeta)) {
            $videoDetailData['list_img_large_meta'] = $listImgLargeMeta;
        }

        $screenImg = $this->handleScreenImg($item, $channel->slug, $sourceId);
        if (!empty($screenImg)) {
            $videoDetailData['screen_img'] = $screenImg;
        }

        return DB::transaction(function () use ($sourceUUID, $videoData, $videoDetailData, $femaleActors, $item) {
            // 1. 写入或更新主视频记录
            $video = Video::updateOrCreate(
                ['source_uuid' => $sourceUUID],
                $videoData
            );

            // 2. 写入或更新详情附表
            $videoDetailData['video_id'] = $video->id;
            VideoDetail::updateOrCreate(
                ['video_id' => $video->id],
                $videoDetailData
            );

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
            $tags = $item['tags'] ?? [];
            foreach ($tags as $tag) {
                if (empty($tag['name'])) {
                    continue;
                }
                $catSlug = Str::slug($tag['name']);
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

            return $video;
        });
    }

    /**
     * 请求 Project1Service API 接口
     */
    protected function requestApiReleases(Channel $channel, string $token, int $limit): array
    {
        try {
            $origin = $channel->official_website_url ?: 'https://www.project1service.com';

            $response = Http::withHeaders([
                'user-agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'referer'         => $origin,
                'origin'          => $origin,
                'instance'        => $token,
                'accept-language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                'accept'          => 'application/json, text/plain, */*',
                'authority'       => 'site-api.project1service.com',
            ])->timeout(30)->get('https://site-api.project1service.com/v2/releases', [
                'orderBy' => '-dateReleased',
                'type'    => 'scene',
                'limit'   => $limit,
                'offset'  => 0,
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

    protected function handleSourceUUID(string $id, string $slug): string
    {
        return $id . ':' . $slug;
    }

    protected function handleReleaseAt(?string $utc): string
    {
        if (empty($utc)) {
            return now()->toDateTimeString();
        }

        return Carbon::parse($utc)->setTimezone('Asia/Shanghai')->toDateTimeString();
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
        $collectionName = $item['collections'][0]['name'] ?? 'P1S';
        $collectionSlug = Str::slug($collectionName);
        $videoCode = Str::studly($collectionSlug) . '.' . date('y.m.d', strtotime($releaseAt)) . '.';

        $slugWithPoint = [];
        foreach ($femaleActors as $actor) {
            if (($actor['gender'] ?? 0) !== 1) { // 非男性
                $slugWithPoint[] = str_replace(' ', '.', trim(str_replace('.', '', $actor['name'] ?? '')));
            }
        }

        if (!empty($slugWithPoint)) {
            $videoCode .= implode('.And.', array_filter($slugWithPoint)) . '.';
        }

        $parts = explode('-', $slug);
        $convertedParts = array_map(fn ($p) => ucfirst($p), $parts);
        $videoCode .= implode('.', $convertedParts);

        return $videoCode;
    }

    /**
     * 计算并格式化文件相对与本地路径（去除 Query 参数并使用哈希存储）
     */
    protected function handleFilesUrl(string $url, string $channel, string $sourceId): array
    {
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

        $xsSrc = $this->handleFilesUrl($poster['xs']['url'], $channel, $sourceId);
        $mdSrc = $this->handleFilesUrl($poster['md']['url'], $channel, $sourceId);
        $smSrc = $this->handleFilesUrl($poster['sm']['url'], $channel, $sourceId);
        $lgSrc = $this->handleFilesUrl($poster['lg']['url'], $channel, $sourceId);
        $xlSrc = $this->handleFilesUrl($poster['xl']['url'], $channel, $sourceId);

        $xsWebp = $this->handleFilesUrl($poster['xs']['urls']['webp'] ?? $poster['xs']['url'], $channel, $sourceId);
        $mdWebp = $this->handleFilesUrl($poster['md']['urls']['webp'] ?? $poster['md']['url'], $channel, $sourceId);
        $smWebp = $this->handleFilesUrl($poster['sm']['urls']['webp'] ?? $poster['sm']['url'], $channel, $sourceId);
        $lgWebp = $this->handleFilesUrl($poster['lg']['urls']['webp'] ?? $poster['lg']['url'], $channel, $sourceId);
        $xlWebp = $this->handleFilesUrl($poster['xl']['urls']['webp'] ?? $poster['xl']['url'], $channel, $sourceId);

        return [
            [
                'src'                => $smSrc['dbUrl'],
                'src_source'         => $poster['sm']['url'],
                'placeholder'        => $xsSrc['dbUrl'],
                'placeholder_source' => $poster['xs']['url'],
                'width'              => $poster['sm']['width'] ?? 0,
                'height'             => $poster['sm']['height'] ?? 0,
                'highdpi'            => [
                    'double'        => $mdSrc['dbUrl'],
                    'double_source' => $poster['md']['url'],
                ],
                'webp'               => [
                    'src'                => $smWebp['dbUrl'],
                    'src_source'         => $poster['sm']['urls']['webp'] ?? '',
                    'placeholder'        => $xsWebp['dbUrl'],
                    'placeholder_source' => $poster['xs']['urls']['webp'] ?? '',
                    'highdpi'            => [
                        'double'        => $mdWebp['dbUrl'],
                        'double_source' => $poster['md']['urls']['webp'] ?? '',
                    ],
                ],
            ],
            [
                'src'                => $lgSrc['dbUrl'],
                'src_source'         => $poster['lg']['url'],
                'placeholder'        => $xsSrc['dbUrl'],
                'placeholder_source' => $poster['xs']['url'],
                'width'              => $poster['lg']['width'] ?? 0,
                'height'             => $poster['lg']['height'] ?? 0,
                'highdpi'            => [
                    'double'        => $xlSrc['dbUrl'],
                    'double_source' => $poster['xl']['url'],
                ],
                'webp'               => [
                    'src'                => $lgWebp['dbUrl'],
                    'src_source'         => $poster['lg']['urls']['webp'] ?? '',
                    'placeholder'        => $xsWebp['dbUrl'],
                    'placeholder_source' => $poster['xs']['urls']['webp'] ?? '',
                    'highdpi'            => [
                        'double'        => $xlWebp['dbUrl'],
                        'double_source' => $poster['xl']['urls']['webp'] ?? '',
                    ],
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

        $xsSrc = $this->handleFilesUrl($poster['xs']['url'], $channel, $sourceId);
        $smSrc = $this->handleFilesUrl($poster['sm']['url'], $channel, $sourceId);
        $mdSrc = $this->handleFilesUrl($poster['md']['url'], $channel, $sourceId);
        $xxSrc = $this->handleFilesUrl($poster['xx']['url'], $channel, $sourceId);

        $xsWebp = $this->handleFilesUrl($poster['xs']['urls']['webp'] ?? $poster['xs']['url'], $channel, $sourceId);
        $smWebp = $this->handleFilesUrl($poster['sm']['urls']['webp'] ?? $poster['sm']['url'], $channel, $sourceId);
        $mdWebp = $this->handleFilesUrl($poster['md']['urls']['webp'] ?? $poster['md']['url'], $channel, $sourceId);
        $xxWebp = $this->handleFilesUrl($poster['xx']['urls']['webp'] ?? $poster['xx']['url'], $channel, $sourceId);

        return [
            [
                'src'         => $smSrc['dbUrl'],
                'src_source'  => $poster['sm']['url'],
                'placeholder' => $xsSrc['dbUrl'],
                'width'       => $poster['sm']['width'] ?? 0,
                'height'      => $poster['sm']['height'] ?? 0,
                'media'       => '(max-width: 360px)',
                'webp'        => [
                    'src'         => $smWebp['dbUrl'],
                    'src_source'  => $poster['sm']['urls']['webp'] ?? '',
                    'placeholder' => $xsWebp['dbUrl'],
                ],
            ],
            [
                'src'         => $mdSrc['dbUrl'],
                'src_source'  => $poster['md']['url'],
                'placeholder' => $xsSrc['dbUrl'],
                'width'       => $poster['md']['width'] ?? 0,
                'height'      => $poster['md']['height'] ?? 0,
                'media'       => '(min-width: 361px) and (max-width: 768px)',
                'webp'        => [
                    'src'         => $mdWebp['dbUrl'],
                    'src_source'  => $poster['md']['urls']['webp'] ?? '',
                    'placeholder' => $xsWebp['dbUrl'],
                ],
            ],
            [
                'src'         => $xxSrc['dbUrl'],
                'src_source'  => $poster['xx']['url'],
                'placeholder' => $xsSrc['dbUrl'],
                'width'       => $poster['xx']['width'] ?? 0,
                'height'      => $poster['xx']['height'] ?? 0,
                'media'       => '(min-width: 769px)',
                'webp'        => [
                    'src'         => $xxWebp['dbUrl'],
                    'src_source'  => $poster['xx']['urls']['webp'] ?? '',
                    'placeholder' => $xsWebp['dbUrl'],
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
                $smSrc = $this->handleFilesUrl($posters[$i]['sm']['urls']['webp'], $channel, $sourceId);
                $xxSrc = $this->handleFilesUrl($posters[$i]['xx']['urls']['webp'], $channel, $sourceId);

                $screenImgArr[] = [
                    'screen_img_default_url'        => $smSrc['dbUrl'],
                    'screen_img_default_source_url' => $posters[$i]['sm']['urls']['webp'],
                    'screen_img_default_width'      => $posters[$i]['sm']['width'] ?? 0,
                    'screen_img_default_height'     => $posters[$i]['sm']['height'] ?? 0,
                    'screen_img_full_url'           => $xxSrc['dbUrl'],
                    'screen_img_full_width'         => $posters[$i]['xx']['width'] ?? 0,
                    'screen_img_full_height'        => $posters[$i]['xx']['height'] ?? 0,
                    'screen_img_full_source_url'    => $posters[$i]['xx']['urls']['webp'],
                    'screen_img_full_source_width'  => $posters[$i]['xx']['width'] ?? 0,
                    'screen_img_full_source_height' => $posters[$i]['xx']['height'] ?? 0,
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
}
