<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\Category;
use App\Models\Channel;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoDetail;
use Carbon\Carbon;
use Closure;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TrueAnalCrawlerService
{
    public const CHANNEL_SLUG = 'trueanal';
    public const CHANNEL_NAME = 'TrueAnal';
    public const BASE_URL = 'https://tour.trueanal.com';
    public const LIST_URL = 'https://tour.trueanal.com/scenes';
    public const DEFAULT_MAX_PAGES = 39;

    /**
     * 控制台日志输出回调
     */
    protected ?Closure $logger = null;

    /**
     * 片商模型缓存
     */
    protected ?Channel $channel = null;

    /**
     * ThePornDB API 服务实例
     */
    protected TpdbApiService $tpdbService;

    /**
     * 常用标签英汉互译字典
     */
    protected array $tagDictionary = [
        '4k'                       => '4K超清',
        'anal'                     => '肛交',
        'anal gape'                => '扩肛',
        'anal play'                => '后庭玩弄',
        'anal pounding'            => '深插肛交',
        'anal rimming'             => '毒龙钻',
        'ass eating'               => '舔肛',
        'ass licking'              => '舔肛',
        'ass rimming'              => '毒龙钻',
        'ass to mouth'             => '肛交接吻',
        'ass to pussy'             => '肛交转阴',
        'asshole'                  => '后庭肛门',
        'asshole licking'          => '舔肛门',
        'ball sucking'             => '吸蛋',
        'big ass'                  => '大屁股',
        'big boobs'                => '大胸',
        'big cocks'                => '大屌',
        'big cock'                 => '大屌',
        'big dick'                 => '大屌',
        'big tits'                 => '大胸',
        'huge tits'                => '超大胸',
        'natural tits'             => '自然美胸',
        'blowjob'                  => '口交',
        'blowjobs'                 => '口交',
        'cocksucking'              => '含屌口交',
        'creampie'                 => '中出内射',
        'cumshot'                  => '射精',
        'deep throat'              => '深喉',
        'deepthroat'               => '深喉',
        'deepthroating'            => '深喉',
        'dicksucking'              => '口交',
        'exclusive'                => '独家',
        'facial'                   => '颜射',
        'hardcore'                 => '硬核',
        'brunette'                 => '黑发',
        'brunettes'                => '褐发',
        'blonde'                   => '金发',
        'blondes'                  => '金发',
        'redhead'                  => '红发',
        'lingerie'                 => '性感内衣',
        'masturbation'             => '自慰自渎',
        'milf'                     => '熟女人妻',
        'petite'                   => '娇小纤瘦',
        'shaved'                   => '白虎剃毛',
        'squirt'                   => '潮吹喷水',
        'squirting'                => '潮吹喷水',
        'tattoo'                   => '纹身刺青',
        'tattoos'                  => '纹身刺青',
        'piercing'                 => '穿孔穿钉',
        'piercings'                => '穿孔穿钉',
        'pov'                      => '第一视角',
        'rough sex'                => '粗暴激烈',
        'gap'                      => '扩肛大开',
        'gaping'                   => '肛门大张',
        'creampie anal'            => '肛交中出',
        'anal creampie'            => '肛交中出',
    ];

    public function __construct(TpdbApiService $tpdbService)
    {
        $this->tpdbService = $tpdbService;
    }

    /**
     * 设置控制台实时日志输出回调
     */
    public function setLogger(Closure $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * 记录日志（同时分发至控制台与 Laravel 日志）
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            ($this->logger)($message, $level);
        }

        match ($level) {
            'error'   => Log::error("[TrueAnal] {$message}"),
            'warn'    => Log::warning("[TrueAnal] {$message}"),
            default   => Log::info("[TrueAnal] {$message}"),
        };
    }

    /**
     * 确保获取或创建 TrueAnal 片商记录
     */
    public function ensureChannel(): Channel
    {
        if ($this->channel !== null) {
            return $this->channel;
        }

        $this->channel = Channel::firstOrCreate(
            ['slug' => self::CHANNEL_SLUG],
            [
                'name'                 => self::CHANNEL_NAME,
                'official_website_url' => self::BASE_URL,
                'data_crawl_type'      => 0,
                'avatar'               => '',
                'logo'                 => '',
            ]
        );

        return $this->channel;
    }

    /**
     * 构造指定分页的列表页 URL
     */
    public function buildPageUrl(int $page = 1): string
    {
        if ($page <= 1) {
            return self::LIST_URL;
        }

        return self::LIST_URL . '?page=' . $page;
    }

    /**
     * 规范化图片与静态资源 URL（清理多余斜杠）
     */
    public function normalizeUrl(?string $url): string
    {
        if (empty($url)) {
            return '';
        }

        $trimmed = trim($url);
        // 清理协议后出现的多余 //，如 https://c77661026d.mjedge.net//6/a/... -> https://c77661026d.mjedge.net/6/a/...
        return preg_replace('#(?<!:)//+#', '/', $trimmed);
    }

    /**
     * 解析列表页 HTML，优先读取 Next.js __NEXT_DATA__，无则优雅降级为 DOM 解析
     *
     * @param string $html
     * @return array{total: int, page: int, per_page: int, total_pages: int, scenes: array}
     */
    public function parseListHtml(string $html): array
    {
        // 1. 优先尝试从 Next.js SSR 内嵌 JSON (__NEXT_DATA__) 中完整解包
        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            $json = json_decode($matches[1], true);
            if (is_array($json)) {
                $contents = $json['props']['pageProps']['contents'] ?? null;
                if (is_array($contents) && isset($contents['data']) && is_array($contents['data'])) {
                    $scenes = [];
                    foreach ($contents['data'] as $item) {
                        $parsedScene = $this->transformSceneData($item);
                        if (!empty($parsedScene)) {
                            $scenes[] = $parsedScene;
                        }
                    }

                    return [
                        'total'       => (int) ($contents['total'] ?? count($scenes)),
                        'page'        => (int) ($contents['page'] ?? 1),
                        'per_page'    => (int) ($contents['per_page'] ?? count($scenes)),
                        'total_pages' => (int) ($contents['total_pages'] ?? 1),
                        'scenes'      => $scenes,
                    ];
                }
            }
        }

        // 2. DOM 备选降级解析
        $this->log("⚠️ 未在列表 HTML 中检测到 __NEXT_DATA__，启动 DOM 备用解析...", 'warn');
        return $this->parseListHtmlViaDom($html);
    }

    /**
     * 解析详情页 HTML
     *
     * @param string $html
     * @param string $detailUrl
     * @return array|null
     */
    public function parseDetailHtml(string $html, string $detailUrl = ''): ?array
    {
        // 1. 优先尝试从 Next.js SSR 内嵌 JSON 中提取 content
        if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            $json = json_decode($matches[1], true);
            if (is_array($json)) {
                $content = $json['props']['pageProps']['content'] ?? null;
                if (is_array($content) && !empty($content['id'])) {
                    return $this->transformSceneData($content, $detailUrl);
                }
            }
        }

        // 2. DOM 备选降级解析
        $this->log("⚠️ 未在详情 HTML 中检测到 __NEXT_DATA__，启动 DOM 详情备用解析...", 'warn');
        return $this->parseDetailHtmlViaDom($html, $detailUrl);
    }

    /**
     * 将 TrueAnal 的原始 JSON 节点转换为系统标准场景数据
     */
    public function transformSceneData(array $item, string $fallbackDetailUrl = ''): array
    {
        $id = (int) ($item['id'] ?? 0);
        $title = trim((string) ($item['title'] ?? ''));
        $slug = trim((string) ($item['slug'] ?? ''));
        if (empty($slug) && !empty($title)) {
            $slug = Str::slug($title);
        }

        $sourceUuid = self::CHANNEL_SLUG . ':' . ($id > 0 ? $id : $slug);
        $detailUrl = !empty($slug) ? self::BASE_URL . '/scenes/' . $slug : $fallbackDetailUrl;

        // 发布时间
        $publishDate = $item['publish_date'] ?? null;
        $releaseAt = null;
        if (!empty($publishDate)) {
            try {
                $releaseAt = Carbon::parse($publishDate)->format('Y-m-d H:i:s');
            } catch (Throwable) {}
        }

        // 时长
        $secondsDuration = (int) ($item['seconds_duration'] ?? 0);
        $videosDuration = (string) ($item['videos_duration'] ?? '');

        // 封面图与大图
        $thumb = $this->normalizeUrl($item['thumb'] ?? '');
        $trailerScreencap = $this->normalizeUrl($item['trailer_screencap'] ?? '');
        $trailerUrl = $this->normalizeUrl($item['trailer_url'] ?? '');

        // 截图组
        $extraThumbnails = [];
        foreach ($item['extra_thumbnails'] ?? [] as $imgUrl) {
            $normalized = $this->normalizeUrl($imgUrl);
            if (!empty($normalized)) {
                $extraThumbnails[] = $normalized;
            }
        }

        // 悬停轮播图组
        $thumbs = [];
        foreach ($item['thumbs'] ?? [] as $thumbUrl) {
            $normalized = $this->normalizeUrl($thumbUrl);
            if (!empty($normalized)) {
                $thumbs[] = $normalized;
            }
        }

        // 演员列表提取
        $actors = [];
        $modelsThumbsMap = [];
        foreach ($item['models_thumbs'] ?? [] as $mt) {
            $mName = trim((string) ($mt['name'] ?? ''));
            if (!empty($mName) && !empty($mt['thumb'])) {
                $modelsThumbsMap[$mName] = $this->normalizeUrl($mt['thumb']);
            }
        }

        $models = $item['models'] ?? [];
        if (is_array($models)) {
            foreach ($models as $modelName) {
                $cleanName = trim((string) $modelName);
                if (empty($cleanName)) {
                    continue;
                }
                $actors[] = [
                    'name'   => $cleanName,
                    'slug'   => Str::slug($cleanName),
                    'avatar' => $modelsThumbsMap[$cleanName] ?? null,
                ];
            }
        }

        // 画质与 4K 判定
        $videosObj = $item['videos'] ?? [];
        $orig = $videosObj['orig'] ?? [];
        $origHeight = (int) ($orig['height'] ?? 0);
        $origWidth = (int) ($orig['width'] ?? 0);

        $tags = (array) ($item['tags'] ?? []);
        $has4kTag = false;
        foreach ($tags as $t) {
            if (stripos($t, '4k') !== false) {
                $has4kTag = true;
                break;
            }
        }

        $is4k = $origHeight >= 2160 || $origWidth >= 3840 || $has4kTag;
        $maxQuality = $is4k ? '2160p' : (!empty($videosObj['hq']) ? '1080p' : '720p');

        return [
            'id'                 => $id,
            'source_uuid'        => $sourceUuid,
            'name'               => $title,
            'slug'               => $slug,
            'detail_url'         => $detailUrl,
            'release_at'         => $releaseAt,
            'movie_length'       => $secondsDuration,
            'videos_duration'    => $videosDuration,
            'thumb'              => $thumb,
            'trailer_screencap'  => $trailerScreencap,
            'trailer_url'        => $trailerUrl,
            'extra_thumbnails'   => $extraThumbnails,
            'thumbs'             => $thumbs,
            'tags'               => $tags,
            'actors'             => $actors,
            'is_4k'              => $is4k,
            'max_quality'        => $maxQuality,
            'description'        => trim((string) ($item['description'] ?? '')),
            'rating'             => $item['rating'] ?? 0,
        ];
    }

    /**
     * 将场景数据持久化至本地数据库（包含 Video, VideoDetail, Actor, Tag, Channel）
     *
     * @param array $sceneItem
     * @param bool $dryRun 是否为仅演练模式
     * @return array
     */
    public function saveVideo(array $sceneItem, bool $dryRun = false): array
    {
        $sourceUuid = $sceneItem['source_uuid'];
        $videoName = $sceneItem['name'];
        $slug = !empty($sceneItem['slug']) ? $sceneItem['slug'] : Str::slug($videoName);

        // 1. 构造标准 Scene Release 番号规范: TrueAnal.YY.MM.DD.Actor.Title
        $videoCode = $this->buildVideoCode($sceneItem);

        // 2. 封面图与大图响应式结构
        $smallThumbUrl = $sceneItem['thumb'] ?? '';
        $largePosterUrl = !empty($sceneItem['trailer_screencap'])
            ? $sceneItem['trailer_screencap']
            : (!empty($sceneItem['extra_thumbnails'][0]) ? $sceneItem['extra_thumbnails'][0] : $smallThumbUrl);

        $listImg = $this->buildListImg($smallThumbUrl, 489, 331, $largePosterUrl);
        $listImgLargeMeta = $this->buildListImgLargeMeta($smallThumbUrl, 489, 331, $largePosterUrl);

        // 3. 构造前端多图悬停轮播 preview 字段 (格式：3<url1,url2...)
        $thumbs = !empty($sceneItem['thumbs']) ? $sceneItem['thumbs'] : ($sceneItem['extra_thumbnails'] ?? []);
        $preview = !empty($thumbs) ? '3<' . implode(',', array_slice($thumbs, 0, 12)) : null;

        // 4. 构造详情页剧照轮播 screen_img 数组
        $screenImgs = [];
        foreach ($sceneItem['extra_thumbnails'] ?? [] as $imgUrl) {
            $screenImgs[] = [
                'source_url'      => $imgUrl,
                'width'           => 1920,
                'height'          => 1080,
                'full_source_url' => $imgUrl,
                'full_width'      => 1920,
                'full_height'     => 1080,
                'url'             => $imgUrl,
                'full_url'        => $imgUrl,
            ];
        }

        $actors = $sceneItem['actors'] ?? [];
        $tags = $sceneItem['tags'] ?? [];

        // 演练模式：仅打印解析结果，不写入数据库
        if ($dryRun) {
            return [
                'status'      => 'dry_run',
                'video_code'  => $videoCode,
                'slug'        => $slug,
                'source_uuid' => $sourceUuid,
                'name'        => $videoName,
                'release_at'  => $sceneItem['release_at'] ?? null,
                'is_4k'       => $sceneItem['is_4k'] ?? false,
                'max_quality' => $sceneItem['max_quality'] ?? '1080p',
                'actor_count' => count($actors),
                'actors'      => array_column($actors, 'name'),
                'tag_count'   => count($tags),
                'screen_imgs' => count($screenImgs),
            ];
        }

        $channel = $this->ensureChannel();

        // 1. 多级漏斗推导演员与建档（在事务外部执行：避免 TPDB API 网络请求及 R2 图片上传长时间占用数据库锁与事务）
        $actorIds = [];
        $hasTrans = false;

        foreach ($actors as $actorItem) {
            $actorName = trim($actorItem['name'] ?? '');
            if (empty($actorName)) {
                continue;
            }

            $actorModel = $this->resolveAndPersistActor($actorName, $actorItem['avatar'] ?? null);
            if ($actorModel) {
                $actorIds[] = $actorModel->id;
                if ($actorModel->gender === TpdbApiService::GENDER_TRANS) {
                    $hasTrans = true;
                }
            }
        }

        // 2. 预先解析/创建标签与分类（在事务外部执行：确保每个实体独立自动提交，杜绝 PostgreSQL 事务因唯一键冲突被 abort）
        $tagIds = [];
        $categoryIds = [];
        if (!empty($tags)) {
            foreach ($tags as $tagName) {
                $cleanTagName = trim($tagName);
                if (empty($cleanTagName)) {
                    continue;
                }
                $tag = $this->resolveOrCreateTag($cleanTagName);
                if ($tag) {
                    $tagIds[] = $tag->id;
                }

                $category = $this->resolveOrCreateCategory($cleanTagName);
                if ($category) {
                    $categoryIds[] = $category->id;
                }
            }
        }

        return DB::transaction(function () use (
            $channel, $sourceUuid, $videoCode, $videoName, $slug, $listImg,
            $listImgLargeMeta, $preview, $sceneItem, $screenImgs,
            $actorIds, $hasTrans, $tagIds, $categoryIds, $tags
        ) {
            // 1. 处理 video_code 唯一冲突
            $conflictVideo = Video::where('video_code', $videoCode)->first();
            if ($conflictVideo && $conflictVideo->source_uuid !== $sourceUuid) {
                $videoCode .= '.' . substr(md5($sourceUuid), 0, 4);
            }

            // 2. Video 主表插入或增量更新
            $videoData = [
                'name'               => $videoName,
                'slug'               => $slug,
                'channel_id'         => $channel->id,
                'video_code'         => $videoCode,
                'list_img'           => $listImg,
                'preview'            => $preview,
                'release_at'         => $sceneItem['release_at'] ?? null,
                'is_4k'              => $sceneItem['is_4k'] ?? true,
                'is_vr'              => false,
                'sexual_orientation' => 1, // 异性恋厂牌
                'is_trans_model'     => $hasTrans,
                'max_quality'        => $sceneItem['max_quality'] ?? '2160p',
                'status'             => 1,
            ];

            $existingVideo = Video::where('source_uuid', $sourceUuid)->first();
            if ($existingVideo) {
                $updateData = [];
                foreach ($videoData as $key => $val) {
                    if ($val !== null && $val !== '' && ($existingVideo->{$key} === null || $existingVideo->{$key} === '')) {
                        $updateData[$key] = $val;
                    }
                }
                if (!empty($updateData)) {
                    $existingVideo->update($updateData);
                }
                $video = $existingVideo;
            } else {
                $video = Video::create(array_merge(['source_uuid' => $sourceUuid], $videoData));
            }

            // 3. VideoDetail 详情表插入或增量更新
            $detailData = [
                'video_id'            => $video->id,
                'movie_length'        => $sceneItem['movie_length'] ?? 0,
                'description'         => $sceneItem['description'] ?? '',
                'video_urls'          => $sceneItem['trailer_url'] ?? null,
                'screen_img'          => !empty($screenImgs) ? $screenImgs : null,
                'list_img_large_meta' => !empty($listImgLargeMeta) ? $listImgLargeMeta : null,
            ];

            $existingDetail = VideoDetail::where('video_id', $video->id)->first();
            if ($existingDetail) {
                $updateDetailData = [];
                foreach ($detailData as $key => $val) {
                    if ($val !== null && $val !== '' && ($existingDetail->{$key} === null || $existingDetail->{$key} === '')) {
                        $updateDetailData[$key] = $val;
                    }
                }
                if (!empty($updateDetailData)) {
                    $existingDetail->update($updateDetailData);
                }
            } else {
                VideoDetail::create($detailData);
            }

            // 4. 同步关联演员
            if (!empty($actorIds)) {
                $video->actors()->syncWithoutDetaching($actorIds);
            }

            // 5. 同步关联标签 Tags 与 分类 Categories
            if (!empty($tagIds)) {
                $video->tags()->syncWithoutDetaching($tagIds);
            }
            if (!empty($categoryIds)) {
                $video->categories()->syncWithoutDetaching($categoryIds);
            }

            // 6. 更新 Channel 视频总数量
            $this->updateChannelVideoCount();

            return [
                'status'      => $video->wasRecentlyCreated ? 'created' : 'updated',
                'video'       => $video,
                'video_code'  => $videoCode,
                'actor_count' => count($actorIds),
                'tag_count'   => count($tags),
            ];
        });
    }

    /**
     * 构建符合 Project1 规范的标准 list_img 结构 (小卡片图与高清播放器 Poster)
     */
    public function buildListImg(string $smallUrl, int $smallWidth, int $smallHeight, string $largeUrl): array
    {
        $listImg = [];

        if (!empty($smallUrl)) {
            $listImg[] = [
                'src_source'         => $smallUrl,
                'placeholder_source' => '',
                'width'              => $smallWidth > 0 ? $smallWidth : 489,
                'height'             => $smallHeight > 0 ? $smallHeight : 331,
                'highdpi'            => [
                    'double_source' => !empty($largeUrl) ? $largeUrl : $smallUrl,
                ],
            ];
        }

        if (!empty($largeUrl)) {
            $listImg[] = [
                'src_source'         => $largeUrl,
                'placeholder_source' => '',
                'width'              => 1920,
                'height'             => 1080,
                'highdpi'            => [
                    'double_source' => $largeUrl,
                ],
            ];
        }

        return $listImg;
    }

    /**
     * 构建符合 Project1 规范的响应式 list_img_large_meta 结构
     */
    public function buildListImgLargeMeta(string $smallUrl, int $smallWidth, int $smallHeight, string $largeUrl): array
    {
        $meta = [];

        if (!empty($smallUrl)) {
            $meta[] = [
                'src_source'         => $smallUrl,
                'placeholder_source' => '',
                'width'              => $smallWidth > 0 ? $smallWidth : 489,
                'height'             => $smallHeight > 0 ? $smallHeight : 331,
                'media'              => '(max-width: 768px)',
                'highdpi'            => [
                    'double_source' => !empty($largeUrl) ? $largeUrl : $smallUrl,
                ],
            ];
        }

        if (!empty($largeUrl)) {
            $meta[] = [
                'src_source'         => $largeUrl,
                'placeholder_source' => '',
                'width'              => 1920,
                'height'             => 1080,
                'media'              => '(min-width: 769px)',
                'highdpi'            => [
                    'double_source' => $largeUrl,
                ],
            ];
        }

        return $meta;
    }

    /**
     * 核心：多级漏斗性别推导与演员建档
     */
    public function resolveAndPersistActor(string $name, ?string $fallbackAvatar = null): Actor
    {
        $actorSlug = Str::slug($name);

        // 步骤 1：本地数据库反查 (Local Actors DB)
        $localActor = Actor::where('slug', $actorSlug)
            ->orWhere('name', $name)
            ->first();

        if ($localActor) {
            $performer = $this->tpdbService->searchPerformer($name);
            if ($performer) {
                $updates = [];

                // 性别补充
                $gender = $this->tpdbService->extractGenderFromPerformer($performer);
                if ($gender > 0 && (int) $localActor->gender === TpdbApiService::GENDER_UNKNOWN) {
                    $updates['gender'] = $gender;
                    $updates['is_trans_model'] = ($gender === TpdbApiService::GENDER_TRANS);
                    $this->log("✨ [TPDB API] 为本地演员 [{$name}] 补齐性别: " . $this->formatGenderName($gender), 'info');
                }

                // 头像补充：选用 TPDB face 字段转码 AVIF，或 fallbackAvatar
                if (empty($localActor->avatar)) {
                    $faceUrl = $performer['face'] ?? ($performer['image'] ?? $fallbackAvatar);
                    if (!empty($faceUrl)) {
                        $avifPath = $this->tpdbService->storeAvatarAsAvif($actorSlug, $faceUrl);
                        if ($avifPath) {
                            $updates['avatar'] = $avifPath;
                            $this->log("🖼️ [头像补充] 演员 [{$name}] 转码 face 为 AVIF 入库 R2: {$avifPath}", 'info');
                        } elseif (!empty($fallbackAvatar)) {
                            $updates['avatar'] = $fallbackAvatar;
                        }
                    }
                }

                // 封面大图 booty_img
                if (empty($localActor->booty_img) && !empty($performer['image'])) {
                    $updates['booty_img'] = $this->tpdbService->buildBootyImg($performer['image']);
                }

                if (!empty($updates)) {
                    $localActor->update($updates);
                }

                $this->tpdbService->syncActorDetailsFromTpdb($localActor, $performer);
            } elseif (empty($localActor->avatar) && !empty($fallbackAvatar)) {
                $localActor->update(['avatar' => $fallbackAvatar]);
            }

            return $localActor;
        }

        // 步骤 2：本地无记录，请求 ThePornDB API 解析
        $performer = $this->tpdbService->searchPerformer($name);
        $gender = $performer ? $this->tpdbService->extractGenderFromPerformer($performer) : TpdbApiService::GENDER_FEMALE;

        $faceUrl = $performer['face'] ?? ($performer['image'] ?? $fallbackAvatar);
        $avatar = '';
        if (!empty($faceUrl)) {
            $avatar = $this->tpdbService->storeAvatarAsAvif($actorSlug, $faceUrl) ?: ($fallbackAvatar ?: $faceUrl);
            $this->log("🖼️ [头像转码] 新演员 [{$name}] face 头像入库 R2: {$avatar}", 'info');
        }

        $bootyImg = !empty($performer['image']) ? $this->tpdbService->buildBootyImg($performer['image']) : null;

        $newActor = Actor::create([
            'name'           => $name,
            'slug'           => $actorSlug,
            'gender'         => $gender,
            'is_trans_model' => ($gender === TpdbApiService::GENDER_TRANS),
            'avatar'         => $avatar ?: '',
            'booty_img'      => $bootyImg,
        ]);

        if ($performer) {
            $this->tpdbService->syncActorDetailsFromTpdb($newActor, $performer);
        }

        $genderText = $this->formatGenderName($gender);
        $this->log("👤 [演员创建] [{$name}] -> 性别: {$genderText} (来源: " . ($performer ? 'ThePornDB API' : '默认女性') . ")", 'info');

        return $newActor;
    }

    /**
     * 常用标签中文翻译转换
     */
    public function resolveChineseName(string $tagName): string
    {
        $normalized = strtolower(trim($tagName));
        if (isset($this->tagDictionary[$normalized])) {
            return $this->tagDictionary[$normalized];
        }

        foreach ($this->tagDictionary as $en => $zh) {
            if (str_contains($normalized, $en)) {
                return $zh;
            }
        }

        return $tagName;
    }

    /**
     * 安全创建或获取 Tag（防止 PostgreSQL 唯一性约束冲突）
     */
    public function resolveOrCreateTag(string $tagName): ?Tag
    {
        $cleanTagName = trim($tagName);
        if (empty($cleanTagName)) {
            return null;
        }

        $slug = Str::slug($cleanTagName);
        $nameZh = $this->resolveChineseName($cleanTagName);

        // 1. 查询现有标签（无论 slug、英文名、中文名相同均复用）
        $existing = Tag::where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [strtolower($cleanTagName)])
            ->orWhere('name_zh', $nameZh)
            ->first();

        if ($existing) {
            // 若现有标签没有中文翻译，尝试补充中文名，但需确保全局 name_zh 唯一性
            if ((empty($existing->name_zh) || $existing->name_zh === $existing->name) && $nameZh !== $cleanTagName) {
                $isNameZhUsed = Tag::where('name_zh', $nameZh)->where('id', '!=', $existing->id)->exists();
                if (!$isNameZhUsed) {
                    try {
                        $existing->update(['name_zh' => $nameZh]);
                    } catch (Throwable) {}
                }
            }
            return $existing;
        }

        // 2. 避免唯一键冲突：预先处理 name_zh、slug、name 重复
        $targetNameZh = $nameZh;
        if (Tag::where('name_zh', $targetNameZh)->exists()) {
            $targetNameZh = $cleanTagName;
            if (Tag::where('name_zh', $targetNameZh)->exists()) {
                $targetNameZh = $cleanTagName . ' ' . substr(md5($cleanTagName), 0, 4);
            }
        }

        $targetSlug = $slug;
        if (Tag::where('slug', $targetSlug)->exists()) {
            $targetSlug = $slug . '-' . substr(md5($cleanTagName), 0, 4);
        }

        $targetName = $cleanTagName;
        if (Tag::where('name', $targetName)->exists()) {
            $targetName = $cleanTagName . ' ' . substr(md5($cleanTagName), 0, 4);
        }

        try {
            return Tag::create([
                'name'              => $targetName,
                'name_zh'           => $targetNameZh,
                'slug'              => $targetSlug,
                'description'       => "包含与【{$nameZh}】相关的精彩内容。",
                'avatar'            => "/storage/tags/{$targetSlug}_square.jpg",
                'avatar_vertical'   => "/storage/tags/{$targetSlug}_vertical.jpg",
                'avatar_horizontal' => "/storage/tags/{$targetSlug}_horizontal.jpg",
                'video_num'         => 0,
                'follow_num'        => 0,
                'photo_num'         => 0,
                'sort'              => 0,
            ]);
        } catch (Throwable $e) {
            $existing = Tag::where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($cleanTagName)])
                ->orWhere('name_zh', $nameZh)
                ->first();

            if ($existing) {
                return $existing;
            }

            $this->log("⚠️ 标签 [{$cleanTagName}] 创建失败: " . $e->getMessage(), 'warn');
            return null;
        }
    }

    /**
     * 安全创建或获取 Category（防止 PostgreSQL 唯一性约束冲突）
     */
    public function resolveOrCreateCategory(string $categoryName): ?Category
    {
        $cleanCategoryName = trim($categoryName);
        if (empty($cleanCategoryName)) {
            return null;
        }

        $slug = Str::slug($cleanCategoryName);
        $nameZh = $this->resolveChineseName($cleanCategoryName);

        // 1. 查询现有分类
        $existing = Category::where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [strtolower($cleanCategoryName)])
            ->orWhere('name_zh', $nameZh)
            ->first();

        if ($existing) {
            if ((empty($existing->name_zh) || $existing->name_zh === $existing->name) && $nameZh !== $cleanCategoryName) {
                $isNameZhUsed = Category::where('name_zh', $nameZh)->where('id', '!=', $existing->id)->exists();
                if (!$isNameZhUsed) {
                    try {
                        $existing->update(['name_zh' => $nameZh]);
                    } catch (Throwable) {}
                }
            }
            return $existing;
        }

        // 2. 避免唯一键冲突：预先处理 name_zh、slug、name 重复
        $targetNameZh = $nameZh;
        if (Category::where('name_zh', $targetNameZh)->exists()) {
            $targetNameZh = $cleanCategoryName;
            if (Category::where('name_zh', $targetNameZh)->exists()) {
                $targetNameZh = $cleanCategoryName . ' ' . substr(md5($cleanCategoryName), 0, 4);
            }
        }

        $targetSlug = $slug;
        if (Category::where('slug', $targetSlug)->exists()) {
            $targetSlug = $slug . '-' . substr(md5($cleanCategoryName), 0, 4);
        }

        $targetName = $cleanCategoryName;
        if (Category::where('name', $targetName)->exists()) {
            $targetName = $cleanCategoryName . ' ' . substr(md5($cleanCategoryName), 0, 4);
        }

        try {
            return Category::create([
                'name'              => $targetName,
                'name_zh'           => $targetNameZh,
                'slug'              => $targetSlug,
                'description'       => "包含与【{$nameZh}】相关的精彩内容。",
                'avatar'            => "/storage/categories/{$targetSlug}_square.jpg",
                'avatar_vertical'   => "/storage/categories/{$targetSlug}_vertical.jpg",
                'avatar_horizontal' => "/storage/categories/{$targetSlug}_horizontal.jpg",
                'video_num'         => 0,
                'follow_num'        => 0,
                'photo_num'         => 0,
                'sort'              => 0,
            ]);
        } catch (Throwable $e) {
            $existing = Category::where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($cleanCategoryName)])
                ->orWhere('name_zh', $nameZh)
                ->first();

            if ($existing) {
                return $existing;
            }

            $this->log("⚠️ 分类 [{$cleanCategoryName}] 创建失败: " . $e->getMessage(), 'warn');
            return null;
        }
    }
    public function updateChannelVideoCount(): int
    {
        $channel = $this->ensureChannel();
        $count = Video::where('channel_id', $channel->id)->count();
        $channel->update(['video_num' => $count]);
        return $count;
    }

    /**
     * 构造标准 Scene Release 格式番号: TrueAnal.YY.MM.DD.Actor.Title
     */
    public function buildVideoCode(array $sceneItem): string
    {
        $releaseAt = $sceneItem['release_at'] ?? null;
        $datePart = $releaseAt ? date('y.m.d', strtotime($releaseAt)) : date('y.m.d');
        $code = 'TrueAnal.' . $datePart;

        // 拼接演员
        $actors = $sceneItem['actors'] ?? [];
        $actorParts = [];
        foreach ($actors as $actor) {
            $name = trim($actor['name'] ?? '');
            if (!empty($name)) {
                $clean = preg_replace('/[^a-zA-Z0-9\s]+/', '', $name);
                $actorParts[] = str_replace(' ', '.', trim(preg_replace('/\s+/', ' ', $clean)));
            }
        }
        if (!empty($actorParts)) {
            $code .= '.' . implode('.And.', array_filter($actorParts));
        }

        // 拼接标题单词
        $title = $sceneItem['name'] ?? '';
        if (!empty($title)) {
            $normalized = str_replace(["\xE2\x80\x99", "\xE2\x80\x98", "’", "‘", "“", "”", "'", "?"], '', $title);
            $cleanTitle = preg_replace('/[^a-zA-Z0-9\s]+/', ' ', $normalized);
            $words = array_filter(explode(' ', trim($cleanTitle)));
            $titleWords = array_map(fn ($w) => ucfirst(strtolower($w)), $words);
            if (!empty($titleWords)) {
                $code .= '.' . implode('.', $titleWords);
            }
        }

        return $code;
    }

    /**
     * 请求指定 URL 获取 HTML 内容（带 Header 仿真与 FlareSolverr 容错）
     */
    public function fetchHtml(string $url): string
    {
        $proxy = config('services.crawler.proxy');
        $userAgent = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36');
        $flaresolverrUrl = config('services.flaresolverr.url', env('FLARESOLVERR_URL', 'http://flaresolverr:8191/v1'));

        $headers = [
            'user-agent'                => $userAgent,
            'accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'accept-language'           => 'en-US,en;q=0.9',
            'upgrade-insecure-requests' => '1',
        ];

        try {
            $req = Http::timeout(20)
                ->retry(2, 500, throw: false)
                ->withHeaders($headers)
                ->withOptions(['verify' => false]);

            if (!empty($proxy)) {
                $req = $req->withOptions(['proxy' => $proxy]);
            }

            $response = $req->get($url);
            $status = $response->status();
            $body = $response->body();

            if ($status === 200 && !empty($body) && !str_contains($body, 'Just a moment...') && !str_contains($body, 'cf-browser-verification')) {
                return $body;
            }
        } catch (Throwable $e) {
            $this->log("⚠️ 直接请求 [{$url}] 异常: {$e->getMessage()}，准备调用 FlareSolverr...", 'warn');
        }

        // 调用 FlareSolverr 解盾
        try {
            $this->log("🛡️ 调用 FlareSolverr 请求: {$url} ...", 'info');
            $fsResponse = Http::timeout(60)->post($flaresolverrUrl, [
                'cmd'        => 'request.get',
                'url'        => $url,
                'maxTimeout' => 45000,
            ]);

            if ($fsResponse->successful()) {
                $resData = $fsResponse->json();
                if (($resData['status'] ?? '') === 'ok') {
                    return $resData['solution']['response'] ?? '';
                }
            }
        } catch (Throwable $e) {
            $this->log("❌ FlareSolverr 请求异常: {$e->getMessage()}", 'error');
        }

        return '';
    }

    /**
     * DOM 备选降级解析列表页 HTML（优先采用 PHP 8.4 原生 HTML5 \Dom\HTMLDocument 特性与 CSS 选择器）
     */
    protected function parseListHtmlViaDom(string $html): array
    {
        $scenes = [];

        // 1. 优先使用 PHP 8.4 全新 HTML5 解析引擎与原生 CSS 选择器
        if (class_exists(\Dom\HTMLDocument::class)) {
            $doc = \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR);
            $items = $doc->querySelectorAll('.content-item-large');

            foreach ($items as $itemNode) {
                $linkNode = $itemNode->querySelector('a.big-img');
                $href = $linkNode?->getAttribute('href') ?? '';
                $imgNode = $linkNode?->querySelector('img') ?? $itemNode->querySelector('img');
                $title = $imgNode?->getAttribute('alt') ?? '';
                $thumb = $imgNode?->getAttribute('src') ?? '';

                $slug = basename($href);
                if (empty($title)) {
                    $title = ucwords(str_replace('-', ' ', $slug));
                }

                if (!empty($slug)) {
                    $scenes[] = [
                        'id'                => 0,
                        'source_uuid'       => self::CHANNEL_SLUG . ':' . $slug,
                        'name'              => $title,
                        'slug'              => $slug,
                        'detail_url'        => self::BASE_URL . '/scenes/' . $slug,
                        'release_at'        => null,
                        'movie_length'      => 0,
                        'videos_duration'   => '',
                        'thumb'             => $this->normalizeUrl($thumb),
                        'trailer_screencap' => '',
                        'trailer_url'       => '',
                        'extra_thumbnails'  => [],
                        'thumbs'            => [],
                        'tags'              => [],
                        'actors'            => [],
                        'is_4k'             => true,
                        'max_quality'       => '2160p',
                        'description'       => '',
                        'rating'            => 0,
                    ];
                }
            }

            return [
                'total'       => count($scenes),
                'page'        => 1,
                'per_page'    => count($scenes),
                'total_pages' => 1,
                'scenes'      => $scenes,
            ];
        }

        // 2. 传统 DOMDocument 兼容降级方案（严格 instanceof DOMElement 杜绝 IDE 警告）
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $items = $xpath->query('//div[contains(@class, "content-item-large")]');

        foreach ($items as $itemNode) {
            $linkItem = $xpath->query('.//a[contains(@class, "big-img")]', $itemNode)->item(0);
            $linkElement = $linkItem instanceof DOMElement ? $linkItem : null;
            $href = $linkElement ? $linkElement->getAttribute('href') : '';

            $imgItem = $xpath->query('.//img', $linkElement ?: $itemNode)->item(0);
            $imgElement = $imgItem instanceof DOMElement ? $imgItem : null;
            $title = $imgElement ? $imgElement->getAttribute('alt') : '';
            $thumb = $imgElement ? $imgElement->getAttribute('src') : '';

            $slug = basename($href);
            if (empty($title)) {
                $title = ucwords(str_replace('-', ' ', $slug));
            }

            if (!empty($slug)) {
                $scenes[] = [
                    'id'                => 0,
                    'source_uuid'       => self::CHANNEL_SLUG . ':' . $slug,
                    'name'              => $title,
                    'slug'              => $slug,
                    'detail_url'        => self::BASE_URL . '/scenes/' . $slug,
                    'release_at'        => null,
                    'movie_length'      => 0,
                    'videos_duration'   => '',
                    'thumb'             => $this->normalizeUrl($thumb),
                    'trailer_screencap' => '',
                    'trailer_url'       => '',
                    'extra_thumbnails'  => [],
                    'thumbs'            => [],
                    'tags'              => [],
                    'actors'            => [],
                    'is_4k'             => true,
                    'max_quality'       => '2160p',
                    'description'       => '',
                    'rating'            => 0,
                ];
            }
        }

        return [
            'total'       => count($scenes),
            'page'        => 1,
            'per_page'    => count($scenes),
            'total_pages' => 1,
            'scenes'      => $scenes,
        ];
    }

    /**
     * DOM 备选降级解析详情页 HTML（优先采用 PHP 8.4 原生 HTML5 \Dom\HTMLDocument 特性与 CSS 选择器）
     */
    protected function parseDetailHtmlViaDom(string $html, string $detailUrl): ?array
    {
        $slug = basename(parse_url($detailUrl, PHP_URL_PATH) ?: $detailUrl);

        // 1. 优先使用 PHP 8.4 全新 HTML5 解析引擎与原生 CSS 选择器
        if (class_exists(\Dom\HTMLDocument::class)) {
            $doc = \Dom\HTMLDocument::createFromString($html, \LIBXML_NOERROR);
            $titleNode = $doc->querySelector('h1');
            $title = $titleNode ? trim($titleNode->textContent) : ucwords(str_replace('-', ' ', $slug));

            $descNode = $doc->querySelector('.desc, .description, .scene-description');
            $description = $descNode ? trim($descNode->textContent) : '';

            return [
                'id'                => 0,
                'source_uuid'       => self::CHANNEL_SLUG . ':' . $slug,
                'name'              => $title,
                'slug'              => $slug,
                'detail_url'        => $detailUrl,
                'release_at'        => null,
                'movie_length'      => 0,
                'videos_duration'   => '',
                'thumb'             => '',
                'trailer_screencap' => '',
                'trailer_url'       => '',
                'extra_thumbnails'  => [],
                'thumbs'            => [],
                'tags'              => [],
                'actors'            => [],
                'is_4k'             => true,
                'max_quality'       => '2160p',
                'description'       => $description,
                'rating'            => 0,
            ];
        }

        // 2. 传统 DOMDocument 兼容降级方案
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new DOMXPath($dom);

        $titleNode = $xpath->query('//h1')->item(0);
        $title = $titleNode ? trim($titleNode->textContent) : ucwords(str_replace('-', ' ', $slug));

        $descNode = $xpath->query('//div[contains(@class, "desc") or contains(@class, "description")]')->item(0);
        $description = $descNode ? trim($descNode->textContent) : '';

        return [
            'id'                => 0,
            'source_uuid'       => self::CHANNEL_SLUG . ':' . $slug,
            'name'              => $title,
            'slug'              => $slug,
            'detail_url'        => $detailUrl,
            'release_at'        => null,
            'movie_length'      => 0,
            'videos_duration'   => '',
            'thumb'             => '',
            'trailer_screencap' => '',
            'trailer_url'       => '',
            'extra_thumbnails'  => [],
            'thumbs'            => [],
            'tags'              => [],
            'actors'            => [],
            'is_4k'             => true,
            'max_quality'       => '2160p',
            'description'       => $description,
            'rating'            => 0,
        ];
    }

    /**
     * 格式化性别文字
     */
    protected function formatGenderName(int $gender): string
    {
        return match ($gender) {
            TpdbApiService::GENDER_MALE   => '男',
            TpdbApiService::GENDER_FEMALE => '女',
            TpdbApiService::GENDER_TRANS  => '变性人',
            default                       => '未知',
        };
    }
}
