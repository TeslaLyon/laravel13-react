<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\Channel;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoDetail;
use Carbon\Carbon;
use Closure;
use DateInterval;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class JulesJordanCrawlerService
{
    public const CHANNEL_SLUG = 'julesjordan';
    public const CHANNEL_NAME = 'Jules Jordan';
    public const BASE_URL = 'https://www.julesjordan.com';
    public const LIST_URL = 'https://www.julesjordan.com/trial/categories/movies.html';

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
            'error'   => Log::error("[JulesJordan] {$message}"),
            'warn'    => Log::warning("[JulesJordan] {$message}"),
            default   => Log::info("[JulesJordan] {$message}"),
        };
    }

    /**
     * 确保获取或创建 Jules Jordan 片商记录
     */
    public function ensureChannel(): Channel
    {
        if ($this->channel) {
            return $this->channel;
        }

        $this->channel = Channel::firstOrCreate(
            ['slug' => self::CHANNEL_SLUG],
            [
                'name'                 => self::CHANNEL_NAME,
                'slug'                 => self::CHANNEL_SLUG,
                'data_crawl_type'      => 1,
                'avatar'               => '/images/channels/julesjordan.avif',
                'logo'                 => '/images/channels/julesjordan.avif',
                'official_website_url' => self::BASE_URL,
            ]
        );

        return $this->channel;
    }

    /**
     * 构建分页列表 URL
     */
    public function buildPageUrl(int $page = 1): string
    {
        $page = max(1, $page);
        if ($page === 1) {
            return self::LIST_URL;
        }
        return "https://www.julesjordan.com/trial/categories/movies_{$page}_d.html";
    }

    /**
     * 解析视频列表页 HTML
     *
     * @param string $html
     * @return array{success: bool, total_pages: int, current_page: int, scenes: array}
     */
    public function parseListHtml(string $html): array
    {
        if (empty(trim($html))) {
            return ['success' => false, 'total_pages' => 0, 'current_page' => 1, 'scenes' => []];
        }

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // 1. 解析分页信息
        $totalPages = 1;
        $currentPage = 1;

        // 从 select 节点提取总页数
        $selectNodes = $xpath->query('//select[contains(@class, "jj-pag-select")]//option');
        if ($selectNodes && $selectNodes->length > 0) {
            $lastOption = $selectNodes->item($selectNodes->length - 1);
            if ($lastOption instanceof DOMElement) {
                $totalPages = (int) $lastOption->getAttribute('value');
            }
        } else {
            // 正则兜底解析 Page 1 of 93
            if (preg_match('/Page\s*<b>(\d+)<\/b>\s*of\s*<b>(\d+)<\/b>/i', $html, $matches)) {
                $currentPage = (int) $matches[1];
                $totalPages = (int) $matches[2];
            }
        }

        // 2. 解析视频卡片 (jj-content-card)
        $cardNodes = $xpath->query('//div[contains(@class, "jj-content-card")]');
        $scenes = [];

        foreach ($cardNodes as $card) {
            if (!$card instanceof DOMElement) {
                continue;
            }

            // 详情页链接与缩略图容器
            $thumbLink = $xpath->query('.//a[contains(@class, "jj-card-thumb")]', $card)->item(0);
            $detailUrl = '';
            $dataThumbs = [];
            $dataTrailers = [];

            if ($thumbLink instanceof DOMElement) {
                $detailUrl = $thumbLink->getAttribute('href');
                $rawThumbs = $thumbLink->getAttribute('data-thumbs');
                if (!empty($rawThumbs)) {
                    $dataThumbs = json_decode($rawThumbs, true) ?: [];
                }
                $rawTrailers = $thumbLink->getAttribute('data-trailers');
                if (!empty($rawTrailers)) {
                    $dataTrailers = json_decode($rawTrailers, true) ?: [];
                }
            }

            // 兜底从 body 的 onclick 提取详情页链接
            if (empty($detailUrl)) {
                $bodyNode = $xpath->query('.//div[contains(@class, "jj-card-body")]', $card)->item(0);
                if ($bodyNode instanceof DOMElement && preg_match("/window\.location=['\"]([^'\"]+)['\"]/i", $bodyNode->getAttribute('onclick'), $onclickMatches)) {
                    $detailUrl = $onclickMatches[1];
                }
            }

            if (empty($detailUrl)) {
                continue;
            }

            // 标题
            $titleNode = $xpath->query('.//h2[contains(@class, "jj-card-title")]', $card)->item(0);
            $title = $titleNode ? trim($titleNode->textContent) : '';

            // 封面图片
            $imgNode = $xpath->query('.//img[contains(@class, "jj-thumb-img")]', $card)->item(0);
            $listImg = '';
            $targetId = '';
            $thumbWidth = 489;
            $thumbHeight = 331;
            if ($imgNode instanceof DOMElement) {
                $listImg = $imgNode->getAttribute('src');
                $idAttr = $imgNode->getAttribute('id'); // 如 set-target-17411
                if (preg_match('/set-target-(\d+)/', $idAttr, $idMatches)) {
                    $targetId = $idMatches[1];
                }
                if ($imgNode->hasAttribute('width')) {
                    $thumbWidth = (int) $imgNode->getAttribute('width');
                }
                if ($imgNode->hasAttribute('height')) {
                    $thumbHeight = (int) $imgNode->getAttribute('height');
                }
            }
            if (empty($listImg) && !empty($dataThumbs)) {
                $listImg = $dataThumbs[0];
            }

            // 演员列表
            $modelNodes = $xpath->query('.//span[contains(@class, "update_models")]//a', $card);
            $actors = [];
            foreach ($modelNodes as $modelNode) {
                if ($modelNode instanceof DOMElement) {
                    $actorName = trim($modelNode->textContent);
                    if (!empty($actorName)) {
                        $actors[] = [
                            'name' => $actorName,
                            'url'  => $modelNode->getAttribute('href'),
                            'slug' => Str::slug($actorName),
                        ];
                    }
                }
            }

            // Facts (时长, 4K, 发布时间)
            $factsNodes = $xpath->query('.//div[contains(@class, "jj-card-facts")]//span', $card);
            $durationStr = '';
            $is4k = false;
            $releaseDateStr = '';
            $photosCount = 0;

            foreach ($factsNodes as $fact) {
                if (!$fact instanceof DOMElement) {
                    continue;
                }
                $text = trim($fact->textContent);
                if (str_contains($text, 'min')) {
                    $durationStr = $text;
                } elseif (stripos($text, '4K') !== false || $fact->getAttribute('class') === 'q4k') {
                    $is4k = true;
                } elseif (str_contains($text, 'photos')) {
                    if (preg_match('/(\d+)\s*photos/i', $text, $pMatches)) {
                        $photosCount = (int) $pMatches[1];
                    }
                } elseif (preg_match('/[A-Za-z]{3}\s+\d{1,2},\s+\d{4}/', $text)) {
                    $releaseDateStr = $text; // e.g. Oct 15, 2026
                }
            }

            // 构造前端悬停多图轮播 preview 字段 (格式：3<url1,url2,url3...)
            $preview = !empty($dataThumbs) ? '3<' . implode(',', array_slice($dataThumbs, 0, 12)) : null;

            // 提取唯一 source_uuid
            $sceneSlug = $this->extractSceneSlugFromUrl($detailUrl);
            $sourceUuid = self::CHANNEL_SLUG . ':' . $sceneSlug;

            $scenes[] = [
                'name'          => $title,
                'detail_url'    => $detailUrl,
                'slug'          => $sceneSlug,
                'source_uuid'   => $sourceUuid,
                'source_id'     => $targetId,
                'list_img'      => $listImg,
                'thumb_width'   => $thumbWidth,
                'thumb_height'  => $thumbHeight,
                'preview'       => $preview,
                'data_thumbs'   => $dataThumbs,
                'data_trailers' => $dataTrailers,
                'actors'        => $actors,
                'duration_str'  => $durationStr,
                'is_4k'         => $is4k,
                'photos_count'  => $photosCount,
                'released_date' => $releaseDateStr,
            ];
        }

        return [
            'success'      => true,
            'total_pages'  => $totalPages,
            'current_page' => $currentPage,
            'scenes'       => $scenes,
        ];
    }

    /**
     * 解析详情页 HTML (优先读取 JSON-LD VideoObject，结合 DOM 互补)
     *
     * @param string $html
     * @param string $detailUrl
     * @return array
     */
    public function parseDetailHtml(string $html, string $detailUrl): array
    {
        if (empty(trim($html))) {
            return [];
        }

        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // 1. 查找并解析 JSON-LD 结构化数据
        $scripts = $xpath->query('//script[@type="application/ld+json"]');
        $videoObject = null;

        foreach ($scripts as $script) {
            if (!$script instanceof DOMElement) {
                continue;
            }
            $json = json_decode(trim($script->textContent), true);
            if (isset($json['@type']) && $json['@type'] === 'VideoObject') {
                $videoObject = $json;
                break;
            }
        }

        // 2. 基础属性解析
        $title = $videoObject['name'] ?? '';
        if (empty($title)) {
            $h1Node = $xpath->query('//h1[contains(@class, "scene-title")]')->item(0);
            $title = $h1Node ? trim($h1Node->textContent) : '';
        }

        $description = $videoObject['description'] ?? '';
        if (empty($description)) {
            $descNode = $xpath->query('//div[contains(@class, "scene-desc")]')->item(0);
            $description = $descNode ? trim($descNode->textContent) : '';
        }

        // 时长计算 (PT29M50S -> 秒)
        $movieLength = 0;
        if (!empty($videoObject['duration'])) {
            try {
                $di = new DateInterval($videoObject['duration']);
                $movieLength = ($di->h * 3600) + ($di->i * 60) + $di->s;
            } catch (Throwable) {
                $movieLength = 0;
            }
        }

        // 发布时间
        $releaseAt = null;
        if (!empty($videoObject['uploadDate'])) {
            try {
                $releaseAt = Carbon::parse($videoObject['uploadDate'])->format('Y-m-d H:i:s');
            } catch (Throwable) {}
        }
        if (empty($releaseAt)) {
            // DOM released 节点
            $metaItems = $xpath->query('//div[contains(@class, "scene-meta")]//div[contains(@class, "meta-item")]');
            foreach ($metaItems as $item) {
                if (!$item instanceof DOMElement) {
                    continue;
                }
                $lblNode = $xpath->query('.//div[contains(@class, "lbl")]', $item)->item(0);
                if ($lblNode && stripos($lblNode->textContent, 'Released') !== false) {
                    $valNode = $xpath->query('.//div[contains(@class, "val")]', $item)->item(0);
                    if ($valNode) {
                        try {
                            $releaseAt = Carbon::parse(trim($valNode->textContent))->format('Y-m-d 00:00:00');
                        } catch (Throwable) {}
                    }
                }
            }
        }

        // 3. 演员抽取 (优先从 JSON-LD，其次从 DOM)
        $actors = [];
        if (!empty($videoObject['actor']) && is_array($videoObject['actor'])) {
            foreach ($videoObject['actor'] as $act) {
                $actName = trim($act['name'] ?? '');
                if (!empty($actName)) {
                    $actors[] = [
                        'name' => $actName,
                        'slug' => Str::slug($actName),
                        'url'  => $act['url'] ?? '',
                    ];
                }
            }
        }
        if (empty($actors)) {
            $modelLinks = $xpath->query('//div[contains(@class, "scene-meta")]//span[contains(@class, "update_models")]//a');
            foreach ($modelLinks as $link) {
                if ($link instanceof DOMElement) {
                    $actName = trim($link->textContent);
                    if (!empty($actName)) {
                        $actors[] = [
                            'name' => $actName,
                            'slug' => Str::slug($actName),
                            'url'  => $link->getAttribute('href'),
                        ];
                    }
                }
            }
        }

        // 4. 标签抽取 (genre / cat-tag)
        $tags = $videoObject['genre'] ?? [];
        if (empty($tags)) {
            $catNodes = $xpath->query('//div[contains(@class, "scene-cats")]//a[contains(@class, "cat-tag")]');
            foreach ($catNodes as $cat) {
                if ($cat instanceof DOMElement) {
                    $catName = trim($cat->textContent);
                    if (!empty($catName) && !in_array($catName, $tags, true)) {
                        $tags[] = $catName;
                    }
                }
            }
        }

        // 5. 播放器海报与预告片直链
        $playerPoster = '';
        $trailerSources = [];
        $videoPlayer = $xpath->query('//video[@id="video-player"]')->item(0);
        if ($videoPlayer instanceof DOMElement) {
            $playerPoster = $videoPlayer->getAttribute('poster');
            $sourceNodes = $xpath->query('.//source', $videoPlayer);
            foreach ($sourceNodes as $source) {
                if ($source instanceof DOMElement) {
                    $bitrate = $source->getAttribute('data-bitrate') ?: 'default';
                    $src = $source->getAttribute('src');
                    if (!empty($src)) {
                        $trailerSources[$bitrate] = $src;
                    }
                }
            }
        }

        // 选择最佳清晰度的预告片 URL (Trailer_1080 > Trailer_720 > trailer > Trailer_Mobile)
        $bestTrailerUrl = $trailerSources['Trailer_1080']
            ?? ($trailerSources['Trailer_720']
            ?? ($trailerSources['trailer']
            ?? ($trailerSources['Trailer_Mobile']
            ?? (reset($trailerSources) ?: null))));

        // 6. 剧照切片图 (Photo Strip)
        // 遵循 Project1 / ScreenImageMeta 规范：包含 source_url, width, height, full_source_url 等
        $screenImgs = [];
        $photoImgNodes = $xpath->query('//div[contains(@class, "tp-photos-strip")]//a//img');
        $seenScreenUrls = [];
        foreach ($photoImgNodes as $pImg) {
            if ($pImg instanceof DOMElement) {
                $imgSrc = $pImg->getAttribute('src');
                if (empty($imgSrc) || isset($seenScreenUrls[$imgSrc])) {
                    continue;
                }
                $seenScreenUrls[$imgSrc] = true;

                $width = $pImg->hasAttribute('width') ? (int) $pImg->getAttribute('width') : 259;
                $height = $pImg->hasAttribute('height') ? (int) $pImg->getAttribute('height') : 389;

                $screenImgs[] = [
                    'source_url'      => $imgSrc,
                    'width'           => $width,
                    'height'          => $height,
                    'full_source_url' => $imgSrc,
                    'full_width'      => 1920,
                    'full_height'     => 1080,
                    'url'             => $imgSrc,
                    'full_url'        => $imgSrc,
                ];
            }
        }

        // 清晰度判定
        $videoQuality = $videoObject['videoQuality'] ?? '2160p';
        $is4k = stripos($videoQuality, '2160') !== false || stripos($videoQuality, '4k') !== false;

        $sceneSlug = $this->extractSceneSlugFromUrl($detailUrl);
        $sourceUuid = self::CHANNEL_SLUG . ':' . $sceneSlug;

        return [
            'name'             => $title,
            'slug'             => $sceneSlug,
            'source_uuid'      => $sourceUuid,
            'detail_url'       => $detailUrl,
            'description'      => $description,
            'movie_length'     => $movieLength,
            'release_at'       => $releaseAt,
            'is_4k'            => $is4k,
            'max_quality'      => $is4k ? '2160p' : '1080p',
            'actors'           => $actors,
            'tags'             => $tags,
            'player_poster'    => $playerPoster,
            'trailer_url'      => $bestTrailerUrl,
            'trailer_sources'  => $trailerSources,
            'screen_img'       => $screenImgs,
        ];
    }

    /**
     * 将视频数据写入数据库（原子事务封装）
     *
     * @param array $listItem 列表项信息
     * @param array $detailItem 详情项信息
     * @return array
     */
    public function saveVideo(array $listItem, array $detailItem): array
    {
        $channel = $this->ensureChannel();
        $sourceUuid = $detailItem['source_uuid'] ?? $listItem['source_uuid'];
        $videoName = $detailItem['name'] ?? $listItem['name'];
        $slug = Str::slug($videoName);

        // 构造 video_code: JJ-YYYYMMDD-hash
        $videoCode = $this->buildVideoCode($listItem, $detailItem);

        // 封面与大图构造 (遵循 Project1 / ScreenImageMeta 规范结构)
        $smallThumbUrl = $listItem['list_img'] ?? '';
        $smallWidth = (int) ($listItem['thumb_width'] ?? 489);
        $smallHeight = (int) ($listItem['thumb_height'] ?? 331);
        $largePosterUrl = !empty($detailItem['player_poster']) ? $detailItem['player_poster'] : $smallThumbUrl;

        $listImg = $this->buildListImg($smallThumbUrl, $smallWidth, $smallHeight, $largePosterUrl);
        $listImgLargeMeta = $this->buildListImgLargeMeta($smallThumbUrl, $smallWidth, $smallHeight, $largePosterUrl);

        // 预览字段：保留前端悬停多图轮播 (3<url1,url2...)
        $preview = $listItem['preview'] ?? null;

        // 发布日期
        $releaseAt = $detailItem['release_at'] ?? null;
        if (empty($releaseAt) && !empty($listItem['released_date'])) {
            try {
                $releaseAt = Carbon::parse($listItem['released_date'])->format('Y-m-d 00:00:00');
            } catch (Throwable) {}
        }

        // 合并演员列表（去重）
        $rawActors = $detailItem['actors'] ?? ($listItem['actors'] ?? []);

        return DB::transaction(function () use (
            $channel, $sourceUuid, $videoCode, $videoName, $slug, $listImg,
            $listImgLargeMeta, $preview, $releaseAt, $detailItem, $rawActors
        ) {
            // 1. 处理 video_code 唯一冲突
            $conflictVideo = Video::where('video_code', $videoCode)->first();
            if ($conflictVideo && $conflictVideo->source_uuid !== $sourceUuid) {
                $videoCode .= '.' . substr(md5($sourceUuid), 0, 4);
            }

            // 2. 插入或增量补充 Video 主表记录（数据库中已有值的字段绝不覆盖）
            $videoData = [
                'name'               => $videoName,
                'slug'               => $slug,
                'channel_id'         => $channel->id,
                'video_code'         => $videoCode,
                'list_img'           => $listImg,
                'preview'            => $preview,
                'release_at'         => $releaseAt,
                'is_4k'              => $detailItem['is_4k'] ?? true,
                'is_vr'              => false,
                'sexual_orientation' => 1, // 异性恋厂牌
                'is_trans_model'     => false,
                'max_quality'        => $detailItem['max_quality'] ?? '2160p',
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

            // 3. 插入或增量补充 VideoDetail 详情表记录（数据库中已有值的字段绝不覆盖）
            $detailData = [
                'video_id'            => $video->id,
                'movie_length'        => $detailItem['movie_length'] ?? 0,
                'description'         => $detailItem['description'] ?? '',
                'video_urls'          => $detailItem['trailer_url'] ?? null,
                'screen_img'          => !empty($detailItem['screen_img']) ? $detailItem['screen_img'] : null,
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

            // 4. 【多级漏斗性别推导引擎】：只采用「1. 本地数据库反查」与「2. ThePornDB API」
            $actorIds = [];
            $hasTrans = false;

            foreach ($rawActors as $actorItem) {
                $actorName = trim($actorItem['name'] ?? '');
                if (empty($actorName)) {
                    continue;
                }

                $actorModel = $this->resolveAndPersistActor($actorName);
                if ($actorModel) {
                    $actorIds[] = $actorModel->id;
                    if ($actorModel->gender === TpdbApiService::GENDER_TRANS) {
                        $hasTrans = true;
                    }
                }
            }

            if (!empty($actorIds)) {
                $video->actors()->syncWithoutDetaching($actorIds);
            }

            if ($hasTrans) {
                $video->update(['is_trans_model' => true]);
            }

            // 5. 关联标签 Tags
            $tags = $detailItem['tags'] ?? [];
            if (!empty($tags)) {
                $tagIds = [];
                foreach ($tags as $tagName) {
                    $cleanTagName = trim($tagName);
                    if (empty($cleanTagName)) {
                        continue;
                    }
                    $tag = $this->resolveOrCreateTag($cleanTagName);
                    if ($tag) {
                        $tagIds[] = $tag->id;
                    }
                }
                if (!empty($tagIds)) {
                    $video->tags()->syncWithoutDetaching($tagIds);
                }
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

        // 1. 小尺寸项 (用于列表卡片展示，源自列表页 img)
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

        // 2. 高清大尺寸项 (用于高清设备或详情大图，源自播放器 Poster)
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

        // 移动端/窄屏配置 (<768px)
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

        // 桌面端/宽屏配置 (>=769px)
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
     * 严格遵守用户规则：
     * 1. 本地数据库反查 (Local Actors DB)
     * 2. 第三方权威成人库 API 自动查询 (ThePornDB)
     */
    public function resolveAndPersistActor(string $name): Actor
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

                // 1. 性别补充
                $gender = $this->tpdbService->extractGenderFromPerformer($performer);
                if ($gender > 0 && (int) $localActor->gender === TpdbApiService::GENDER_UNKNOWN) {
                    $updates['gender'] = $gender;
                    $updates['is_trans_model'] = ($gender === TpdbApiService::GENDER_TRANS);
                    $this->log("✨ [TPDB API] 为本地演员 [{$name}] 补齐性别: " . $this->formatGenderName($gender), 'info');
                }

                // 2. 🎯 头像 avatar 选用 TPDB 的 "face" 字段，且仅 avatar 转码 AVIF 上传至 R2（已有值绝不更新）
                if (empty($localActor->avatar)) {
                    $faceUrl = $performer['face'] ?? ($performer['image'] ?? null);
                    if (!empty($faceUrl)) {
                        $avifPath = $this->tpdbService->storeAvatarAsAvif($actorSlug, $faceUrl);
                        if ($avifPath) {
                            $updates['avatar'] = $avifPath;
                            $this->log("🖼️ [头像补充] 演员 [{$name}] 转码 face 字段为 AVIF 并入库 R2: {$avifPath}", 'info');
                        }
                    }
                }

                // 3. 🎯 封面/列表大图 booty_img 选用 TPDB 的 "image" 字段（不上传 R2，直存外部链接）
                if (empty($localActor->booty_img) && !empty($performer['image'])) {
                    $updates['booty_img'] = $this->tpdbService->buildBootyImg($performer['image']);
                }

                if (!empty($updates)) {
                    $localActor->update($updates);
                }

                $this->tpdbService->syncActorDetailsFromTpdb($localActor, $performer);
            }

            return $localActor;
        }

        // 步骤 2：本地无记录，请求 ThePornDB API 解析性别与元数据
        $performer = $this->tpdbService->searchPerformer($name);
        $gender = $performer ? $this->tpdbService->extractGenderFromPerformer($performer) : TpdbApiService::GENDER_UNKNOWN;

        // 🎯 核心规范：avatar 选用 "face" 字段，转码为 AVIF 上传至 R2
        $faceUrl = $performer['face'] ?? ($performer['image'] ?? null);
        $avatar = '';
        if (!empty($faceUrl)) {
            $avatar = $this->tpdbService->storeAvatarAsAvif($actorSlug, $faceUrl) ?: $faceUrl;
            $this->log("🖼️ [头像转码] 新演员 [{$name}] face 头像转码 AVIF 并入库 R2: {$avatar}", 'info');
        }

        // 🎯 核心规范：booty_img 选用 "image" 字段，保留原外部链接，不上传 R2
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
        $this->log("👤 [演员创建] [{$name}] -> 性别: {$genderText} (来源: " . ($gender > 0 ? 'ThePornDB API' : '未知') . ")", 'info');

        return $newActor;
    }

    /**
     * 常用标签中英文字典
     */
    protected array $tagDictionary = [
        '4k'                       => '4K超清',
        'big butts'                => '美臀',
        'big butt'                 => '美臀',
        'big ass'                  => '大屁股',
        'big cocks'                => '大屌',
        'big cock'                 => '大屌',
        'big tits'                 => '大胸',
        'huge tits'                => '超大胸',
        'natural tits'             => '自然美胸',
        'natural'                  => '自然巨乳',
        'small tits'               => '小胸贫乳',
        'blowjob'                  => '口交',
        'blowjobs'                 => '口交',
        'deep throat'              => '深喉',
        'deepthroat'               => '深喉',
        'facial'                   => '颜射',
        'brunette'                 => '黑发',
        'brunettes'                => '褐发',
        'blonde'                   => '金发',
        'blondes'                  => '金发',
        'redhead'                  => '红发',
        'lingerie'                 => '性感内衣',
        'milf'                     => '熟女',
        'creampie'                 => '内射',
        'anal'                     => '肛交',
        'doggystyle'               => '后入式',
        'missionary'               => '传教士体位',
        'cowgirl'                  => '女上位',
        'rough sex'                => '激烈性爱',
        'interracial'              => '跨种族',
        'ebony'                    => '黑人',
        'bbc'                      => '大黑屌',
        'threesome'                => '三人行',
        'group sex'                => '群交',
        'squirt'                   => '潮喷',
        'squirting'                => '潮喷',
        'hardcore'                 => '硬核',
        'pov'                      => '第一人称',
        'tattoo'                   => '纹身',
        'piercing'                 => '穿孔',
        'cumshot'                  => '射精',
        'cum on tits'              => '射在胸上',
        'gonzo'                    => '现场直击',
    ];

    /**
     * 解析标签中文翻译，无翻译时以原名兜底（绝不为空，防止触发 PostgreSQL tags_name_zh_unique 约束）
     */
    public function resolveChineseName(string $tagName): string
    {
        $clean = strtolower(trim($tagName));

        if (isset($this->tagDictionary[$clean])) {
            return $this->tagDictionary[$clean];
        }

        $norm = str_replace('-', ' ', $clean);
        if (isset($this->tagDictionary[$norm])) {
            return $this->tagDictionary[$norm];
        }

        return $tagName;
    }

    /**
     * 安全创建或获取 Tag（防止 PostgreSQL 唯一性约束冲突：name_zh_unique, slug_unique, name_unique）
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
            // 若现有标签没有中文翻译，尝试补充中文名
            if ((empty($existing->name_zh) || $existing->name_zh === $existing->name) && $nameZh !== $cleanTagName) {
                try {
                    $existing->update(['name_zh' => $nameZh]);
                } catch (Throwable) {}
            }
            return $existing;
        }

        // 2. 尝试安全创建
        try {
            return Tag::create([
                'name'              => $cleanTagName,
                'name_zh'           => $nameZh,
                'slug'              => $slug,
                'description'       => "包含与【{$nameZh}】相关的精彩内容。",
                'avatar'            => "/storage/tags/{$slug}_square.jpg",
                'avatar_vertical'   => "/storage/tags/{$slug}_vertical.jpg",
                'avatar_horizontal' => "/storage/tags/{$slug}_horizontal.jpg",
                'video_num'         => 0,
                'follow_num'        => 0,
                'photo_num'         => 0,
                'sort'              => 0,
            ]);
        } catch (Throwable) {
            // 3. 冲突容错：二次查询现有记录
            $existing = Tag::where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($cleanTagName)])
                ->orWhere('name_zh', $nameZh)
                ->first();

            if ($existing) {
                return $existing;
            }

            // 若 name_zh 或 slug 冲突，追加哈希后缀安全创建
            $fallbackSlug = $slug . '-' . substr(md5($cleanTagName), 0, 4);
            $fallbackNameZh = $nameZh . ' ' . substr(md5($cleanTagName), 0, 4);
            try {
                return Tag::create([
                    'name'              => $cleanTagName,
                    'name_zh'           => $fallbackNameZh,
                    'slug'              => $fallbackSlug,
                    'description'       => "包含与【{$nameZh}】相关的精彩内容。",
                    'avatar'            => "/storage/tags/{$fallbackSlug}_square.jpg",
                    'avatar_vertical'   => "/storage/tags/{$fallbackSlug}_vertical.jpg",
                    'avatar_horizontal' => "/storage/tags/{$fallbackSlug}_horizontal.jpg",
                    'video_num'         => 0,
                    'follow_num'        => 0,
                    'photo_num'         => 0,
                    'sort'              => 0,
                ]);
            } catch (Throwable $e) {
                $this->log("⚠️ 标签 [{$cleanTagName}] 创建失败: " . $e->getMessage(), 'warn');
                return null;
            }
        }
    }

    /**
     * 更新片商下的视频总数量
     */
    public function updateChannelVideoCount(): int
    {
        $channel = $this->ensureChannel();
        $count = Video::where('channel_id', $channel->id)->count();
        $channel->update(['video_num' => $count]);
        return $count;
    }

    /**
     * 构造压制组/种子标准命名规范的 video_code（Scene Release 格式）
     * 格式: JulesJordan.YY.MM.DD.Actor1.And.Actor2.Title.Words
     * 例如: JulesJordan.26.10.15.Gigi.Dior.And.Manuel.Ferrara.The.Milfomaniac.Sets.His.Sights.On.Busty.Milf.Gigi.Dior
     */
    protected function buildVideoCode(array $listItem, array $detailItem): string
    {
        $releaseAt = $detailItem['release_at'] ?? null;
        if (empty($releaseAt) && !empty($listItem['released_date'])) {
            try {
                $releaseAt = Carbon::parse($listItem['released_date'])->format('Y-m-d');
            } catch (Throwable) {}
        }

        $datePart = $releaseAt ? date('y.m.d', strtotime($releaseAt)) : date('y.m.d');
        $code = 'JulesJordan.' . $datePart;

        // 1. 拼接参演演员 (多个演员用 .And. 连接)
        $actors = $detailItem['actors'] ?? ($listItem['actors'] ?? []);
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

        // 2. 拼接标题单词（首字母大写，智能处理撇号缩写如 Sun's -> Suns）
        $title = $detailItem['name'] ?? ($listItem['name'] ?? '');
        if (!empty($title)) {
            $normalized = str_replace(["\xE2\x80\x99", "\xE2\x80\x98", "’", "‘", "“", "”", "'"], '', $title);
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
     * 从 URL 中提取 scene slug
     * 如 https://www.julesjordan.com/trial/scenes/Gigi-Dior-Big-Tits-MILF_vids.html -> gigi-dior-big-tits-milf
     */
    protected function extractSceneSlugFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $basename = basename($path);
        // 移除扩展名和 _vids
        $cleaned = preg_replace('/(_vids)?\.html$/i', '', $basename);
        return Str::slug($cleaned);
    }

    /**
     * 请求指定 URL 获取 HTML 内容（带 Header 仿真与 FlareSolverr 容错）
     */
    public function fetchHtml(string $url): string
    {
        $proxy = config('services.crawler.proxy');
        $userAgent = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
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
     * 格式化性别文本
     */
    protected function formatGenderName(int $gender): string
    {
        return match ($gender) {
            TpdbApiService::GENDER_MALE   => '1 - 男 (Male)',
            TpdbApiService::GENDER_FEMALE => '2 - 女 (Female)',
            TpdbApiService::GENDER_TRANS  => '3 - 变性人 (Transgender)',
            default                       => '0 - 未知 (Unknown)',
        };
    }
}

