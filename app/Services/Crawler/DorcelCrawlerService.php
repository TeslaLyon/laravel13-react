<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\Channel;
use App\Models\DelayedProcessingRepeatActor;
use App\Models\Video;
use App\Models\VideoDetail;
use Carbon\Carbon;
use Closure;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class DorcelCrawlerService
{
    public const CHANNEL_SLUG = 'dorcelclub';
    public const CHANNEL_NAME = 'DorcelClub';
    public const BASE_URL = 'https://www.dorcelclub.com';
    public const LIST_URL = 'https://www.dorcelclub.com/en/news-videos-x-marc-dorcel';

    /**
     * 控制台日志输出回调
     */
    protected ?Closure $logger = null;

    /**
     * 片商模型缓存
     */
    protected ?Channel $channel = null;

    /**
     * FlareSolverr 会话状态标记
     */
    protected array $readyFlareSolverrSessions = [];

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
            'error'   => Log::error("[DorcelCrawler] {$message}"),
            'warn'    => Log::warning("[DorcelCrawler] {$message}"),
            'debug'   => Log::debug("[DorcelCrawler] {$message}"),
            default   => Log::info("[DorcelCrawler] {$message}"),
        };
    }

    /**
     * 确保获取或创建 DorcelClub 片商记录
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
                'avatar'               => '/images/channels/dorcelclub-avatar.avif',
                'logo'                 => '/images/channels/dorcelclub-logo.jpg',
                'official_website_url' => self::BASE_URL,
            ]
        );

        // 若数据库中已存在旧记录，同步确保字段最新
        $updates = [];
        if ($this->channel->data_crawl_type !== 1) {
            $updates['data_crawl_type'] = 1;
        }
        if (empty($this->channel->avatar) || $this->channel->avatar !== '/images/channels/dorcelclub-avatar.avif') {
            $updates['avatar'] = '/images/channels/dorcelclub-avatar.avif';
        }
        if (empty($this->channel->logo) || $this->channel->logo !== '/images/channels/dorcelclub-logo.jpg') {
            $updates['logo'] = '/images/channels/dorcelclub-logo.jpg';
        }
        if (!empty($updates)) {
            $this->channel->update($updates);
        }

        return $this->channel;
    }

    /**
     * 安全提取 DOM 节点属性值（同时兼容 PHP 8.4 \Dom\Element 与经典 \DOMElement，彻底消除 IDE 误报）
     *
     * @param mixed $node
     * @param string $name
     * @param string $default
     * @return string
     */
    protected function getAttr(mixed $node, string $name, string $default = ''): string
    {
        if ($node instanceof \Dom\Element || $node instanceof \DOMElement) {
            $val = $node->getAttribute($name);
            return $val !== '' ? trim($val) : $default;
        }

        if (is_object($node) && method_exists($node, 'getAttribute')) {
            /** @var \DOMElement $node */
            $val = $node->getAttribute($name);
            return $val !== '' ? trim($val) : $default;
        }

        return $default;
    }

    /**
     * 抓取视频列表页（使用 PHP 8.4 原生 Dom\HTMLDocument 与现代 CSS 选择器）
     *
     * @param int $page 页码
     * @param string|null $localHtml 本地离线 HTML 内容
     * @return array
     */
    public function crawlListPage(int $page = 1, ?string $localHtml = null): array
    {
        $page = max(1, $page);
        $channel = $this->ensureChannel();

        if ($localHtml !== null) {
            $html = $localHtml;
        } else {
            $requestParams = [
                'sorting' => 'new',
                'page'    => $page,
            ];
            $html = $this->fetchHtml(self::LIST_URL, $requestParams);
        }

        if (empty($html)) {
            return [
                'success'     => false,
                'page'        => $page,
                'scenes'      => [],
                'total_items' => 0,
                'last_page'   => $page,
                'message'     => "抓取 DorcelClub 列表第 {$page} 页失败：响应内容为空",
            ];
        }

        try {
            // 🌟 优先使用 PHP 8.4 原生 WHATWG HTML5 规范解析器 \Dom\HTMLDocument
            if (class_exists(\Dom\HTMLDocument::class)) {
                return $this->parseListPageWithPhp84Dom($html, $page);
            }

            // 经典 DOMDocument / XPath 兼容回退
            return $this->parseListPageWithClassicDom($html, $page);
        } catch (Throwable $e) {
            $this->log("❌ 解析列表 HTML 发生异常: " . $e->getMessage(), 'error');
            return [
                'success'     => false,
                'page'        => $page,
                'scenes'      => [],
                'total_items' => 0,
                'last_page'   => $page,
                'message'     => '解析异常: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 【PHP 8.4 新特性驱动】使用 \Dom\HTMLDocument 解析列表页
     */
    protected function parseListPageWithPhp84Dom(string $html, int $page): array
    {
        $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);

        // 1. 尝试匹配场景卡片节点
        $cardElements = $doc->querySelectorAll('#scenes > div > div:nth-child(2) > div:nth-child(2) > div');
        if ($cardElements->count() === 0) {
            $cardElements = $doc->querySelectorAll('#scenes div.scene-box, #scenes div.scene-item, div.scenes-list > div');
        }

        $scenes = [];

        if ($cardElements->count() > 0) {
            foreach ($cardElements as $card) {
                $scene = $this->extractSceneFromPhp84Card($card);
                if ($scene) {
                    $scenes[] = $scene;
                }
            }
        } else {
            // 降级兜底：直接按所有标题链接回溯其容器
            $titleLinks = $doc->querySelectorAll('#scenes a.title, a.title');
            foreach ($titleLinks as $titleEl) {
                $card = $titleEl->closest('#scenes > div > div:nth-child(2) > div:nth-child(2) > div')
                    ?? $titleEl->closest('div.scene-box, div.scene-item')
                    ?? $titleEl->parentElement?->parentElement
                    ?? $titleEl->parentElement;

                if ($card) {
                    $scene = $this->extractSceneFromPhp84Card($card, $titleEl);
                    if ($scene) {
                        $scenes[] = $scene;
                    }
                }
            }
        }

        // 提取总页码
        $lastPage = $page;
        $pageLinks = $doc->querySelectorAll('ul.pagination a, .pagination a');
        foreach ($pageLinks as $link) {
            $text = trim($link->textContent);
            if (is_numeric($text)) {
                $lastPage = max($lastPage, (int) $text);
            }
        }

        return [
            'success'     => true,
            'page'        => $page,
            'scenes'      => $scenes,
            'total_items' => count($scenes),
            'last_page'   => $lastPage,
            'message'     => 'success',
        ];
    }

    /**
     * 从单个 PHP 8.4 DOM 卡片元素提取视频基础数据
     */
    protected function extractSceneFromPhp84Card(object $card, ?object $titleEl = null): ?array
    {
        $titleEl = $titleEl ?? $card->querySelector('a.title');
        if (!$titleEl) {
            return null;
        }

        $videoName = trim($titleEl->textContent);
        if (empty($videoName) || str_contains($videoName, 'Coming soon')) {
            return null;
        }

        $detailUrl = $this->getAttr($titleEl, 'href');
        if (empty($detailUrl)) {
            return null;
        }

        // 格式如: /en/scene/5248/video-slug-title
        $detailUrlStr = str_replace('/en/scene/', '', ltrim($detailUrl, '/'));
        $detailParts = explode('/', $detailUrlStr);
        $videoSourceId = $detailParts[0] ?? '';
        $videoSourceSlug = $detailParts[1] ?? Str::slug($videoName);

        if (empty($videoSourceId)) {
            return null;
        }

        $sourceUuid = self::CHANNEL_SLUG . ':' . $videoSourceId;

        // 演员提取
        $actors = [];
        $actorsEl = $card->querySelector('div.textual > div.actors, div.actors');
        if ($actorsEl) {
            $rawActors = array_map('trim', explode('|', trim($actorsEl->textContent)));
            foreach ($rawActors as $rawActor) {
                if (!empty($rawActor)) {
                    $actors[] = [
                        'name' => $rawActor,
                        'slug' => Str::slug($rawActor),
                    ];
                }
            }
        }

        // 封面图片提取（存原始 URL）
        $listImg = [];
        $sources = $card->querySelectorAll('a.thumb picture source, picture source');
        $pattern = '/(https?:\/\/[^\s]+) \d+x/';

        if ($sources->count() > 0) {
            // 小图 (370x231)
            $smallNode = ($sources->count() >= 5) ? $sources->item(4) : $sources->item($sources->count() - 1);
            if ($smallNode) {
                $srcset = $this->getAttr($smallNode, 'data-srcset') ?: $this->getAttr($smallNode, 'srcset');
                if ($srcset && preg_match_all($pattern, $srcset, $matches) && count($matches[1]) >= 2) {
                    $smValue = $matches[1][0];
                    $mdValue = $matches[1][1];

                    $listImg[] = [
                        'src_source'         => $smValue,
                        'placeholder_source' => '',
                        'width'              => 370,
                        'height'             => 231,
                        'highdpi'            => [
                            'double_source' => $mdValue,
                        ],
                    ];
                }
            }

            // 大图 (575x360)
            $bigNode = ($sources->count() >= 3) ? $sources->item(2) : $sources->item(0);
            if ($bigNode) {
                $srcset = $this->getAttr($bigNode, 'data-srcset') ?: $this->getAttr($bigNode, 'srcset');
                if ($srcset && preg_match_all($pattern, $srcset, $matches) && count($matches[1]) >= 2) {
                    $lgValue = $matches[1][0];
                    $xlValue = $matches[1][1];

                    $listImg[] = [
                        'src_source'         => $lgValue,
                        'placeholder_source' => '',
                        'width'              => 575,
                        'height'             => 360,
                        'highdpi'            => [
                            'double_source' => $xlValue,
                        ],
                    ];
                }
            }
        }

        // 若 picture source 匹配为空，尝试降级提取 img 节点
        if (empty($listImg)) {
            $imgNode = $card->querySelector('a.thumb img, img');
            if ($imgNode) {
                $imgUrl = $this->getAttr($imgNode, 'data-src') ?: $this->getAttr($imgNode, 'src');
                if (!empty($imgUrl)) {
                    $listImg = $this->buildListImgFromSingleUrl($imgUrl);
                }
            }
        }

        // 动态预览提取
        $preview = null;
        $previewEl = $card->querySelector('span.thumb-ratio, a.thumb');
        if ($previewEl) {
            $previewValue = $this->getAttr($previewEl, 'data-hq-preview');
            if (!empty($previewValue)) {
                $preview = '2>' . $previewValue;
            }
        }

        return [
            'source_id'   => $videoSourceId,
            'source_slug' => $videoSourceSlug,
            'source_uuid' => $sourceUuid,
            'name'        => $videoName,
            'detail_url'  => $detailUrl,
            'actors'      => $actors,
            'list_img'    => $listImg,
            'preview'     => $preview,
        ];
    }

    /**
     * 抓取单个视频详情页信息（结合 PHP 8.4 Dom\HTMLDocument 快速解析）
     *
     * @param string $detailUrl 详情页相对路径或完整链接
     * @param string|null $localDetailHtml 本地离线 HTML 内容
     * @return array|null
     */
    public function crawlSceneDetail(string $detailUrl, ?string $localDetailHtml = null): ?array
    {
        if ($localDetailHtml !== null) {
            $html = $localDetailHtml;
        } else {
            $url = str_starts_with($detailUrl, 'http') ? $detailUrl : self::BASE_URL . '/' . ltrim($detailUrl, '/');
            $html = $this->fetchHtml($url);
        }

        if (empty($html)) {
            $this->log("⚠️ 详情页响应为空: {$detailUrl}", 'warn');
            return null;
        }

        try {
            if (class_exists(\Dom\HTMLDocument::class)) {
                return $this->parseDetailWithPhp84Dom($html);
            }

            return $this->parseDetailWithClassicDom($html);
        } catch (Throwable $e) {
            $this->log("❌ 解析详情页 HTML 异常 [{$detailUrl}]: " . $e->getMessage(), 'error');
            return null;
        }
    }

    /**
     * 【PHP 8.4 新特性驱动】使用 \Dom\HTMLDocument 解析详情页
     */
    protected function parseDetailWithPhp84Dom(string $html): array
    {
        $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $detailData = [];

        // 1. 发布时间
        $publishDateEl = $doc->querySelector('span.publish_date, .publish_date');
        if ($publishDateEl) {
            $publishDateText = trim($publishDateEl->textContent);
            $timestamp = strtotime($publishDateText);
            if ($timestamp) {
                try {
                    $date = Carbon::createFromFormat('F d, Y', $publishDateText);
                    $detailData['release_at'] = $date->format('Y-m-d H:i:s');
                    $detailData['release_timestamp'] = $timestamp;
                } catch (Throwable) {
                    $detailData['release_at'] = date('Y-m-d H:i:s', $timestamp);
                    $detailData['release_timestamp'] = $timestamp;
                }
            }
        }

        // 2. 视频时长
        $durationEl = $doc->querySelector('span.duration, .duration');
        if ($durationEl) {
            $detailData['movie_length'] = $this->convertToSeconds(trim($durationEl->textContent));
        } else {
            $detailData['movie_length'] = 0;
        }

        // 3. 剧情描述
        $descriptionEl = $doc->querySelector('div.content-description > .content-text, .content-description, div.description');
        if ($descriptionEl) {
            $detailData['description'] = trim($descriptionEl->textContent);
        } else {
            $detailData['description'] = '';
        }

        // 4. 详情页大图 Meta（严格对齐 Project1VideoCrawlerService 结构）
        $detailData['list_img_large_meta'] = $this->extractLargeImgMetaPhp84($doc);

        return $detailData;
    }

    /**
     * 【PHP 8.4 新特性驱动】从详情页解析大图 Meta
     * 字段与结构严格对齐 Project1VideoCrawlerService
     */
    protected function extractLargeImgMetaPhp84(object $doc): array
    {
        $largeImgMeta = [];
        $sourceConfigs = [
            ['key' => 0, 'width' => 360, 'height' => 240, 'media' => '(max-width: 360px)'],
            ['key' => 2, 'width' => 575, 'height' => 383, 'media' => '(min-width: 361px) and (max-width: 768px)'],
            ['key' => 4, 'width' => 773, 'height' => 515, 'media' => '(min-width: 769px)'],
        ];

        $largeSources = $doc->querySelectorAll('div.player_container picture source, div.player picture source, picture source');
        $pattern = '/(https?:\/\/[^\s]+) \d+x/';

        if ($largeSources->count() > 0) {
            foreach ($sourceConfigs as $config) {
                if ($largeSources->count() > $config['key']) {
                    $sourceNode = $largeSources->item($config['key']);
                    $srcset = $this->getAttr($sourceNode, 'data-srcset') ?: $this->getAttr($sourceNode, 'srcset');
                    if ($srcset && preg_match_all($pattern, $srcset, $matches) && count($matches[1]) >= 2) {
                        $smUrl = $matches[1][0];
                        $doubleUrl = $matches[1][1];

                        $largeImgMeta[] = [
                            'src_source'         => $smUrl,
                            'placeholder_source' => '',
                            'width'              => $config['width'],
                            'height'             => $config['height'],
                            'media'              => $config['media'],
                            'highdpi'            => [
                                'double_source' => $doubleUrl,
                            ],
                        ];
                    }
                }
            }
        }

        return $largeImgMeta;
    }

    /**
     * 兼容回退模式：经典 DOMDocument / DOMXPath 解析列表页
     */
    protected function parseListPageWithClassicDom(string $html, int $page): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $encodedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML($encodedHtml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $videoNodes = $xpath->query('//*[@id="scenes"]/div/div[2]/div[2]/div');
        if (!$videoNodes || $videoNodes->length === 0) {
            $videoNodes = $xpath->query('//*[@id="scenes"]//div[.//a[contains(@class, "title")]]');
        }

        $scenes = [];
        if ($videoNodes && $videoNodes->length > 0) {
            foreach ($videoNodes as $node) {
                $titleNodes = $xpath->query('.//a[contains(@class, "title")]', $node);
                if (!$titleNodes || $titleNodes->length === 0) {
                    continue;
                }

                $titleEl = $titleNodes->item(0);
                $videoName = trim($titleEl->textContent);
                if (empty($videoName) || str_contains($videoName, 'Coming soon')) {
                    continue;
                }

                $detailUrl = $this->getAttr($titleEl, 'href');
                if (empty($detailUrl)) {
                    continue;
                }

                $detailUrlStr = str_replace('/en/scene/', '', ltrim($detailUrl, '/'));
                $detailParts = explode('/', $detailUrlStr);
                $videoSourceId = $detailParts[0] ?? '';
                $videoSourceSlug = $detailParts[1] ?? Str::slug($videoName);

                if (empty($videoSourceId)) {
                    continue;
                }

                $sourceUuid = self::CHANNEL_SLUG . ':' . $videoSourceId;

                $actors = [];
                $actorsNodes = $xpath->query('.//div[contains(@class, "actors")]', $node);
                if ($actorsNodes && $actorsNodes->length > 0) {
                    $rawActors = array_map('trim', explode('|', trim($actorsNodes->item(0)->textContent)));
                    foreach ($rawActors as $rawActor) {
                        if (!empty($rawActor)) {
                            $actors[] = ['name' => $rawActor, 'slug' => Str::slug($rawActor)];
                        }
                    }
                }

                $listImg = $this->extractListImagesClassic($xpath, $node);

                $preview = null;
                $previewNodes = $xpath->query('.//span[contains(@class, "thumb-ratio")]', $node);
                if ($previewNodes && $previewNodes->length > 0) {
                    $previewValue = $this->getAttr($previewNodes->item(0), 'data-hq-preview');
                    if (!empty($previewValue)) {
                        $preview = '2>' . $previewValue;
                    }
                }

                $scenes[] = [
                    'source_id'   => $videoSourceId,
                    'source_slug' => $videoSourceSlug,
                    'source_uuid' => $sourceUuid,
                    'name'        => $videoName,
                    'detail_url'  => $detailUrl,
                    'actors'      => $actors,
                    'list_img'    => $listImg,
                    'preview'     => $preview,
                ];
            }
        }

        $lastPage = $page;
        $pageLinks = $xpath->query('//ul[contains(@class, "pagination")]//a | //div[contains(@class, "pagination")]//a');
        if ($pageLinks && $pageLinks->length > 0) {
            foreach ($pageLinks as $link) {
                $text = trim($link->textContent);
                if (is_numeric($text)) {
                    $lastPage = max($lastPage, (int) $text);
                }
            }
        }

        return [
            'success'     => true,
            'page'        => $page,
            'scenes'      => $scenes,
            'total_items' => count($scenes),
            'last_page'   => $lastPage,
            'message'     => 'success',
        ];
    }

    /**
     * 经典 DOMDocument / DOMXPath 解析详情页
     */
    protected function parseDetailWithClassicDom(string $html): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $encodedHtml = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
        @$dom->loadHTML($encodedHtml, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $detailData = [];

        $publishDateNodes = $xpath->query('//span[contains(@class, "publish_date")] | //*[contains(@class, "publish_date")]');
        if ($publishDateNodes && $publishDateNodes->length > 0) {
            $publishDateText = trim($publishDateNodes->item(0)->textContent);
            $timestamp = strtotime($publishDateText);
            if ($timestamp) {
                try {
                    $date = Carbon::createFromFormat('F d, Y', $publishDateText);
                    $detailData['release_at'] = $date->format('Y-m-d H:i:s');
                    $detailData['release_timestamp'] = $timestamp;
                } catch (Throwable) {
                    $detailData['release_at'] = date('Y-m-d H:i:s', $timestamp);
                    $detailData['release_timestamp'] = $timestamp;
                }
            }
        }

        $durationNodes = $xpath->query('//span[contains(@class, "duration")] | //*[contains(@class, "duration")]');
        if ($durationNodes && $durationNodes->length > 0) {
            $detailData['movie_length'] = $this->convertToSeconds(trim($durationNodes->item(0)->textContent));
        } else {
            $detailData['movie_length'] = 0;
        }

        $descriptionNodes = $xpath->query('//div[contains(@class, "content-description")]//*[contains(@class, "content-text")] | //div[contains(@class, "content-description")]');
        if ($descriptionNodes && $descriptionNodes->length > 0) {
            $detailData['description'] = trim($descriptionNodes->item(0)->textContent);
        } else {
            $detailData['description'] = '';
        }

        $detailData['list_img_large_meta'] = $this->extractLargeImgMetaClassic($xpath);

        return $detailData;
    }

    /**
     * 经典 XPath 提取封面图
     */
    protected function extractListImagesClassic(DOMXPath $xpath, DOMNode $node): array
    {
        $listImgData = [];
        $pattern = '/(https?:\/\/[^\s]+) \d+x/';
        $sources = $xpath->query('.//a[contains(@class, "thumb")]//picture//source | .//picture//source', $node);

        if ($sources && $sources->length > 0) {
            $smallNode = ($sources->length >= 5) ? $sources->item(4) : $sources->item($sources->length - 1);
            if ($smallNode) {
                $srcset = $this->getAttr($smallNode, 'data-srcset') ?: $this->getAttr($smallNode, 'srcset');
                if ($srcset && preg_match_all($pattern, $srcset, $matches) && count($matches[1]) >= 2) {
                    $smValue = $matches[1][0];
                    $mdValue = $matches[1][1];
                    $listImgData[] = [
                        'src_source'         => $smValue,
                        'placeholder_source' => '',
                        'width'              => 370,
                        'height'             => 231,
                        'highdpi'            => ['double_source' => $mdValue],
                    ];
                }
            }

            $bigNode = ($sources->length >= 3) ? $sources->item(2) : $sources->item(0);
            if ($bigNode) {
                $srcset = $this->getAttr($bigNode, 'data-srcset') ?: $this->getAttr($bigNode, 'srcset');
                if ($srcset && preg_match_all($pattern, $srcset, $matches) && count($matches[1]) >= 2) {
                    $lgValue = $matches[1][0];
                    $xlValue = $matches[1][1];
                    $listImgData[] = [
                        'src_source'         => $lgValue,
                        'placeholder_source' => '',
                        'width'              => 575,
                        'height'             => 360,
                        'highdpi'            => ['double_source' => $xlValue],
                    ];
                }
            }
        }

        // 若 picture source 匹配为空，尝试降级提取 img 节点
        if (empty($listImgData)) {
            $imgNodes = $xpath->query('.//a[contains(@class, "thumb")]//img | .//img', $node);
            if ($imgNodes && $imgNodes->length > 0) {
                $imgUrl = $this->getAttr($imgNodes->item(0), 'data-src') ?: $this->getAttr($imgNodes->item(0), 'src');
                if (!empty($imgUrl)) {
                    $listImgData = $this->buildListImgFromSingleUrl($imgUrl);
                }
            }
        }

        return $listImgData;
    }

    /**
     * 经典 XPath 提取大图 Meta（严格对齐 Project1VideoCrawlerService 结构）
     */
    protected function extractLargeImgMetaClassic(DOMXPath $xpath): array
    {
        $imgData = [];
        $pattern = '/(https?:\/\/[^\s]+) \d+x/';
        $sourceConfigs = [
            ['key' => 0, 'width' => 360, 'height' => 240, 'media' => '(max-width: 360px)'],
            ['key' => 2, 'width' => 575, 'height' => 383, 'media' => '(min-width: 361px) and (max-width: 768px)'],
            ['key' => 4, 'width' => 773, 'height' => 515, 'media' => '(min-width: 769px)'],
        ];

        $sources = $xpath->query('//div[contains(@class, "player_container")]//picture//source | //div[contains(@class, "player")]//picture//source');

        if ($sources && $sources->length > 0) {
            foreach ($sourceConfigs as $config) {
                if ($sources->length > $config['key']) {
                    $sourceNode = $sources->item($config['key']);
                    $srcset = $this->getAttr($sourceNode, 'data-srcset') ?: $this->getAttr($sourceNode, 'srcset');
                    if ($srcset && preg_match_all($pattern, $srcset, $matches) && count($matches[1]) >= 2) {
                        $smUrl = $matches[1][0];
                        $doubleUrl = $matches[1][1];
                        $imgData[] = [
                            'src_source'         => $smUrl,
                            'placeholder_source' => '',
                            'width'              => $config['width'],
                            'height'             => $config['height'],
                            'media'              => $config['media'],
                            'highdpi'            => [
                                'double_source' => $doubleUrl,
                            ],
                        ];
                    }
                }
            }
        }

        return $imgData;
    }

    /**
     * 将解析出的列表项与详情项持久化至数据库
     */
    public function syncSceneToDatabase(array $listItem, ?array $detailItem, bool $dryRun = false): array
    {
        $channel = $this->ensureChannel();
        $sourceUuid = $listItem['source_uuid'];
        $actors = $listItem['actors'] ?? [];

        // 1. 重复/冲突演员延期策略检测
        if (!empty($actors) && $this->isRepeatActorConflict($actors, $sourceUuid, $channel->id)) {
            return [
                'status'  => 'delayed',
                'message' => '触发重复/同名演员冲突判定，已录入延期观察表 (DelayedProcessingRepeatActor)',
                'video'   => null,
            ];
        }

        // 2. 构造 video_code 编码
        $videoCode = $this->buildVideoCode($listItem, $detailItem);

        // 3. 组装视频基础数据
        $videoData = [
            'channel_id'         => $channel->id,
            'name'               => $listItem['name'],
            'slug'               => $listItem['source_slug'],
            'status'             => 1, // 默认上架
            'sexual_orientation' => 1, // 异性
        ];

        if (!empty($listItem['list_img'])) {
            $videoData['list_img'] = $listItem['list_img'];
        }

        if (!empty($listItem['preview'])) {
            $videoData['preview'] = $listItem['preview'];
        }

        if (!empty($detailItem['release_at'])) {
            $videoData['release_at'] = $detailItem['release_at'];
        }

        // 详情大图元数据：优先使用详情页解析的 list_img_large_meta，若无则从 list_img 自动派生构造（严格参照 Project1 模式）
        $largeImgMeta = !empty($detailItem['list_img_large_meta'])
            ? $detailItem['list_img_large_meta']
            : $this->buildLargeImgMetaFromListImg($listItem['list_img'] ?? []);

        // 演练模式：直接返回模拟结果
        if ($dryRun) {
            return [
                'status'              => 'dry_run',
                'video_code'          => $videoCode,
                'source_uuid'         => $sourceUuid,
                'actors'              => array_column($actors, 'name'),
                'list_img_count'      => count($listItem['list_img'] ?? []),
                'large_meta_count'    => count($largeImgMeta),
                'list_img'            => $listItem['list_img'] ?? [],
                'list_img_large_meta' => $largeImgMeta,
                'video'               => null,
            ];
        }

        // 4. 正式入库：事务保证原子性
        return DB::transaction(function () use ($sourceUuid, $videoCode, $videoData, $detailItem, $actors, $largeImgMeta) {
            $conflictVideo = Video::where('video_code', $videoCode)->first();
            if ($conflictVideo && $conflictVideo->source_uuid !== $sourceUuid) {
                $videoCode .= '.' . substr(md5($sourceUuid), 0, 4);
            }
            $videoData['video_code'] = $videoCode;

            $video = Video::updateOrCreate(
                ['source_uuid' => $sourceUuid],
                $videoData
            );

            // 5. 写入详情表
            $detailData = [
                'video_id'     => $video->id,
                'movie_length' => $detailItem['movie_length'] ?? 0,
                'description'  => $detailItem['description'] ?? '',
            ];

            if (!empty($largeImgMeta)) {
                $detailData['list_img_large_meta'] = $largeImgMeta;
            }

            VideoDetail::updateOrCreate(
                ['video_id' => $video->id],
                $detailData
            );

            // 6. 关联演员
            $actorIds = [];
            foreach ($actors as $actorItem) {
                $actorName = trim($actorItem['name']);
                $actorSlug = trim($actorItem['slug']);
                if (empty($actorName) || empty($actorSlug)) {
                    continue;
                }

                $actorModel = Actor::firstOrCreate(
                    ['slug' => $actorSlug],
                    [
                        'name'   => $actorName,
                        'slug'   => $actorSlug,
                        'gender' => 2,
                    ]
                );
                $actorIds[] = $actorModel->id;
            }

            if (!empty($actorIds)) {
                $video->actors()->syncWithoutDetaching($actorIds);
            }

            return [
                'status'     => $video->wasRecentlyCreated ? 'created' : 'updated',
                'video'      => $video,
                'video_code' => $videoCode,
                'actor_count'=> count($actorIds),
            ];
        });
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
     * 构造 DorcelClub 规范格式的 video_code
     */
    public function buildVideoCode(array $listItem, ?array $detailItem): string
    {
        $releaseTimestamp = $detailItem['release_timestamp'] ?? null;
        if ($releaseTimestamp) {
            $datePart = date('y.m.d', $releaseTimestamp);
        } else {
            $datePart = date('y.m.d');
        }

        $code = 'DorcelClub.' . $datePart;

        $actorNamesWithDot = [];
        foreach ($listItem['actors'] ?? [] as $actor) {
            $actorNamesWithDot[] = str_replace(' ', '.', trim($actor['name']));
        }
        if (!empty($actorNamesWithDot)) {
            $code .= '.' . implode('.And.', $actorNamesWithDot);
        }

        $slug = $listItem['source_slug'] ?? Str::slug($listItem['name']);
        $code .= '.' . $this->handleNameInVideoCode($slug);

        return $code;
    }

    /**
     * 将 slug 转换成点号连接的首字母大写单词
     */
    public function handleNameInVideoCode(string $slug): string
    {
        $parts = explode('-', $slug);
        $converted = array_map(function ($part) {
            return ucfirst($part);
        }, $parts);

        return implode('.', $converted);
    }

    /**
     * 重复/冲突演员延期策略检测
     */
    protected function isRepeatActorConflict(array $actors, string $sourceUuid, int $channelId): bool
    {
        foreach ($actors as $actor) {
            $actorSlug = $actor['slug'];

            // 1. 延期观察列表 (Redis Set)
            try {
                if (Redis::sismember('repeat-actor-delay-list', $actorSlug)) {
                    if (Video::where('source_uuid', $sourceUuid)->doesntExist()) {
                        DelayedProcessingRepeatActor::firstOrCreate([
                            'channel_id' => $channelId,
                            'video_slug' => $sourceUuid,
                            'actor_slug' => $actorSlug,
                            'type'       => 0,
                        ]);
                    }
                    return true;
                }
            } catch (Throwable) {}

            // 2. 同名不同人列表 (Redis Set)
            try {
                if (Redis::sismember('repeat-actor-diff-people-with-the-same-name-list', $actorSlug)) {
                    if (Video::where('source_uuid', $sourceUuid)->doesntExist()) {
                        DelayedProcessingRepeatActor::firstOrCreate([
                            'channel_id' => $channelId,
                            'video_slug' => $sourceUuid,
                            'actor_slug' => $actorSlug,
                            'type'       => 1,
                        ]);
                    }
                    return true;
                }
            } catch (Throwable) {}

            // 3. 数据库中同 slug 存在多位女性演员
            try {
                if (Actor::where('slug', $actorSlug)->where('gender', 2)->count() > 1) {
                    if (Video::where('source_uuid', $sourceUuid)->doesntExist()) {
                        DelayedProcessingRepeatActor::firstOrCreate([
                            'channel_id' => $channelId,
                            'video_slug' => $sourceUuid,
                            'actor_slug' => $actorSlug,
                            'type'       => 2,
                        ]);
                    }
                    return true;
                }
            } catch (Throwable) {}
        }

        return false;
    }

    /**
     * 时长文本转秒数（支持 24m35, 1h15m20, 1h15, 45:20 等）
     */
    public function convertToSeconds(string $timeString): int
    {
        $timeString = trim($timeString);
        if (empty($timeString)) {
            return 0;
        }

        // 格式 1: 1h15m20 或 1h15
        if (preg_match('/(\d+)\s*h\s*(\d+)?\s*(m)?\s*(\d+)?/i', $timeString, $matches)) {
            $hours = (int) $matches[1];
            $minutes = isset($matches[2]) ? (int) $matches[2] : 0;
            $seconds = isset($matches[4]) ? (int) $matches[4] : 0;
            return ($hours * 3600) + ($minutes * 60) + $seconds;
        }

        // 格式 2: 24m35 或 24m
        if (preg_match('/(\d+)\s*m\s*(\d+)?/i', $timeString, $matches)) {
            $minutes = (int) $matches[1];
            $seconds = isset($matches[2]) ? (int) $matches[2] : 0;
            return ($minutes * 60) + $seconds;
        }

        // 格式 3: HH:MM:SS 或 MM:SS
        if (str_contains($timeString, ':')) {
            $parts = array_map('intval', explode(':', $timeString));
            if (count($parts) === 3) {
                return ($parts[0] * 3600) + ($parts[1] * 60) + $parts[2];
            }
            if (count($parts) === 2) {
                return ($parts[0] * 60) + $parts[1];
            }
        }

        return 0;
    }

    /**
     * 发起网络请求获取 HTML（直连优先，若遭遇 WAF / 403 阻断自动调度 FlareSolverr 求解）
     */
    public function fetchHtml(string $url, array $params = []): string
    {
        $proxy = config('services.crawler.proxy');
        $userAgent = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
        $flaresolverrUrl = config('services.flaresolverr.url', env('FLARESOLVERR_URL', 'http://flaresolverr:8191/v1'));

        $headers = [
            'user-agent'                => $userAgent,
            'accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'accept-language'           => 'en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7',
            'sec-ch-ua'                 => '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
            'sec-ch-ua-mobile'          => '?0',
            'sec-ch-ua-platform'        => '"macOS"',
            'sec-fetch-dest'            => 'document',
            'sec-fetch-mode'            => 'navigate',
            'sec-fetch-site'            => 'none',
            'sec-fetch-user'            => '?1',
            'upgrade-insecure-requests' => '1',
        ];

        try {
            $request = Http::timeout(20)
                ->retry(2, 500, throw: false)
                ->withHeaders($headers)
                ->withOptions(['verify' => false]);

            if (!empty($proxy)) {
                $request = $request->withOptions(['proxy' => $proxy]);
            }

            $response = $request->get($url, $params);
            $statusCode = $response->status();
            $body = $response->body();

            $isBlocked = $statusCode === 403
                || $statusCode === 503
                || str_contains($body, 'not allowed by policy')
                || str_contains($body, 'Just a moment...')
                || str_contains($body, 'Attention Required!');

            if (!$isBlocked && $response->successful() && strlen($body) > 1000) {
                return $body;
            }

            if ($isBlocked && !empty($flaresolverrUrl)) {
                $this->log("⚠️ 目标页面遭遇防护阻断 (HTTP {$statusCode})，调度 FlareSolverr 穿透: {$url}", 'warn');
                return $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
            }

            return $response->successful() ? $body : '';
        } catch (Throwable $e) {
            $this->log("⚠️ HTTP 直连异常 [{$url}]: " . $e->getMessage() . "，尝试调度 FlareSolverr...", 'warn');
            if (!empty($flaresolverrUrl)) {
                return $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
            }
            return '';
        }
    }

    /**
     * 调度 FlareSolverr 求解并获取页面 HTML
     */
    protected function fetchViaFlareSolverr(string $flaresolverrUrl, string $url, array $params = []): string
    {
        $cleanUrl = rtrim($flaresolverrUrl, '/');
        $endpoint = str_ends_with($cleanUrl, '/v1') ? $cleanUrl : "{$cleanUrl}/v1";
        $sessionId = 'sess_dorcel';

        if (!empty($params)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
        }

        try {
            if (!isset($this->readyFlareSolverrSessions[$sessionId])) {
                $postSession = ['cmd' => 'sessions.create', 'session' => $sessionId];
                $proxy = config('services.crawler.proxy');
                if (!empty($proxy)) {
                    $postSession['proxy'] = ['url' => $proxy];
                }
                $sessRes = Http::timeout(25)->post($endpoint, $postSession);
                if ($sessRes->successful()) {
                    $this->readyFlareSolverrSessions[$sessionId] = true;
                }
            }

            $postData = [
                'cmd'        => 'request.get',
                'url'        => $url,
                'session'    => $sessionId,
                'maxTimeout' => 45000,
            ];
            $proxy = config('services.crawler.proxy');
            if (!empty($proxy)) {
                $postData['proxy'] = ['url' => $proxy];
            }

            $response = Http::timeout(55)->post($endpoint, $postData);
            $data = $response->json() ?? [];

            if (($data['status'] ?? '') === 'ok' && !empty($data['solution']['response'])) {
                return $data['solution']['response'];
            }

            $this->destroyFlareSolverrSession($endpoint, $sessionId);
            return '';
        } catch (Throwable $e) {
            $this->log("❌ FlareSolverr 请求异常: " . $e->getMessage(), 'error');
            $this->destroyFlareSolverrSession($endpoint, $sessionId);
            return '';
        }
    }

    /**
     * 销毁 FlareSolverr 会话
     */
    protected function destroyFlareSolverrSession(string $endpoint, string $sessionId): void
    {
        unset($this->readyFlareSolverrSessions[$sessionId]);
        try {
            Http::timeout(10)->post($endpoint, [
                'cmd'     => 'sessions.destroy',
                'session' => $sessionId,
            ]);
        } catch (Throwable) {}
    }

    /**
     * 根据 list_img 生成标准 list_img_large_meta 结构
     * 字段与结构严格对齐 Project1VideoCrawlerService::handleListImgLargeMeta
     */
    public function buildLargeImgMetaFromListImg(array $listImg): array
    {
        if (empty($listImg)) {
            return [];
        }

        $itemSm = $listImg[0] ?? [];
        $itemLg = $listImg[1] ?? $itemSm;

        $smSrc = $itemSm['src'] ?? '';
        $smSource = $itemSm['src_source'] ?? '';
        $placeholderSource = $itemSm['placeholder_source'] ?? '';

        $mdSource = $itemSm['highdpi']['double_source'] ?? ($itemLg['src_source'] ?? $smSource);
        $xxSource = $itemLg['highdpi']['double_source'] ?? ($itemLg['src_source'] ?? $mdSource);

        return [
            [
                'src_source'         => $smSource,
                'placeholder_source' => $placeholderSource,
                'width'              => 360,
                'height'             => 240,
                'media'              => '(max-width: 360px)',
                'highdpi'            => [
                    'double_source' => $mdSource,
                ],
            ],
            [
                'src_source'         => $mdSource,
                'placeholder_source' => $placeholderSource,
                'width'              => 575,
                'height'             => 383,
                'media'              => '(min-width: 361px) and (max-width: 768px)',
                'highdpi'            => [
                    'double_source' => $xxSource,
                ],
            ],
            [
                'src_source'         => $xxSource,
                'placeholder_source' => $placeholderSource,
                'width'              => 773,
                'height'             => 515,
                'media'              => '(min-width: 769px)',
                'highdpi'            => [
                    'double_source' => $xxSource,
                ],
            ],
        ];
    }

    /**
     * 单张图片降级构造标准 list_img 结构
     * 字段与结构严格对齐 Project1VideoCrawlerService::handleListImg
     */
    public function buildListImgFromSingleUrl(string $url): array
    {
        return [
            [
                'src_source'         => $url,
                'placeholder_source' => '',
                'width'              => 370,
                'height'             => 231,
                'highdpi'            => [
                    'double_source' => $url,
                ],
            ],
            [
                'src_source'         => $url,
                'placeholder_source' => '',
                'width'              => 575,
                'height'             => 360,
                'highdpi'            => [
                    'double_source' => $url,
                ],
            ],
        ];
    }
}

