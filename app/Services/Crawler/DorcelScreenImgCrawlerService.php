<?php

namespace App\Services\Crawler;

use App\Models\Channel;
use App\Models\Video;
use App\Models\VideoDetail;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class DorcelScreenImgCrawlerService
{
    /**
     * 主站：Ultra4kPorn (WordPress 架构高清相册)
     */
    public const ULTRA4KPORN_URL = 'https://www.ultra4kporn.com/';

    /**
     * 备选兜底：Perfect-Stockings
     */
    public const PERFECT_STOCKINGS_SEARCH_URL = 'https://www.perfect-stockings.com/search.php';

    /**
     * 默认渠道标识
     */
    public const CHANNEL_SLUG = 'dorcelclub';

    /**
     * 请求头
     */
    protected array $defaultHeaders = [
        'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
    ];

    /**
     * 获取待补充截图的目标视频 Query
     */
    public function getTargetVideosQuery(
        ?int $videoId = null,
        bool $force = false,
        string $channelSlug = self::CHANNEL_SLUG
    ): Builder {
        $channelId = Channel::where('slug', $channelSlug)->value('id');

        $query = Video::query()
            ->with(['videoDetail:id,video_id,screen_img', 'actors:id,name,slug', 'channel:id,name,slug'])
            ->when($channelId, fn($q) => $q->where('channel_id', $channelId))
            ->when($videoId, fn($q) => $q->where('id', $videoId));

        // 默认过滤掉采访花絮等非正片内容
        $query->where(function ($q) {
            $q->where('name', 'not like', '%Making-of%')
              ->where('name', 'not like', '%Making of%')
              ->where('name', 'not like', '%trailer%')
              ->where('name', 'not like', '%Revenge by%')
              ->where('name', 'not like', '%Interview%');
        });

        if (!$force && !$videoId) {
            // 筛选缺失 screen_img 的视频
            $query->where(function ($q) {
                $q->whereDoesntHave('videoDetail')
                  ->orWhereHas('videoDetail', function ($dq) {
                      $dq->whereNull('screen_img')
                         ->orWhere('screen_img', '[]')
                         ->orWhere('screen_img', '');
                  });
            });
        }

        return $query->orderByDesc('id');
    }

    /**
     * 针对单个视频执行完整的第三方多策略截图抓取与匹配
     */
    public function crawlScreenshotsForVideo(Video $video, ?string $proxy = null): array
    {
        $videoTitle = trim((string) $video->name);
        if (empty($videoTitle)) {
            return [
                'success' => false,
                'source'  => null,
                'images'  => [],
                'message' => '视频标题为空',
            ];
        }

        $actors = $video->relationLoaded('actors') ? $video->actors : $video->actors()->get();
        $actorNames = $actors->pluck('name')->filter()->values()->all();
        $primaryActor = $actorNames[0] ?? '';
        $actorsString = implode(' ', $actorNames);

        // 构造多级搜索词策略
        $searchQueries = $this->buildSearchQueries($videoTitle, $primaryActor, $actorsString);

        // =========================================================================
        // 策略 A: 尝试 ultra4kporn.com 检索
        // =========================================================================
        foreach ($searchQueries as $query) {
            $u4pResult = $this->searchUltra4kPorn($video, $query, $proxy);
            if ($u4pResult['success'] && !empty($u4pResult['images'])) {
                return $u4pResult;
            }
        }

        // =========================================================================
        // 策略 B: 备选兜底 perfect-stockings.com 检索
        // =========================================================================
        $cleanSearch = trim(preg_replace('/[^a-zA-Z0-9\s]+/', ' ', $videoTitle));
        $psResult = $this->searchPerfectStockings($video, $cleanSearch, $proxy);
        if ($psResult['success'] && !empty($psResult['images'])) {
            return $psResult;
        }

        return [
            'success' => false,
            'source'  => null,
            'images'  => [],
            'message' => '所有第三方源站点均未检索到匹配的剧照相册',
        ];
    }

    /**
     * 生成候选搜索策略词列表
     */
    protected function buildSearchQueries(string $title, string $primaryActor, string $allActors): array
    {
        $queries = [];

        // 1. 原片名 (如 A Shared Fantasy Between Friends)
        $queries[] = $title;

        // 2. 原片名 + 主要演员名 (如 A Shared Fantasy Between Friends Anna Polina)
        if (!empty($primaryActor)) {
            $queries[] = "{$title} {$primaryActor}";
        }

        // 3. 去除所有标点/单引号纯字母片名
        $cleanTitle = trim(preg_replace('/[^a-zA-Z0-9\s]+/', ' ', str_replace(["'s", "'"], ' ', $title)));
        $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle);
        if ($cleanTitle !== $title && !empty($cleanTitle)) {
            $queries[] = $cleanTitle;
            if (!empty($primaryActor)) {
                $queries[] = "{$cleanTitle} {$primaryActor}";
            }
        }

        // 4. 原片名 + 所有演员名
        if (!empty($allActors) && $allActors !== $primaryActor) {
            $queries[] = "{$cleanTitle} {$allActors}";
        }

        return array_values(array_unique(array_filter($queries)));
    }

    /**
     * 从 ultra4kporn.com 搜索并提取切片剧照相册
     */
    protected function searchUltra4kPorn(Video $video, string $keyword, ?string $proxy = null): array
    {
        $searchUrl = self::ULTRA4KPORN_URL . '?s=' . rawurlencode($keyword);
        $html = $this->fetchHtml($searchUrl, $proxy);
        if (empty($html)) {
            return ['success' => false, 'source' => 'ultra4kporn', 'images' => [], 'message' => '搜索响应为空'];
        }

        $detailUrls = $this->parseUltra4kPornSearchArticles($html);
        if (empty($detailUrls)) {
            return ['success' => false, 'source' => 'ultra4kporn', 'images' => [], 'message' => '未搜索到文章'];
        }

        // 遍历候选项文章详情页
        foreach ($detailUrls as $article) {
            $detailUrl = $article['url'];
            $articleTitle = $article['title'];

            // 标题相似度校验（避免无关匹配）
            if (!$this->isTitleMatch($video->name, $articleTitle)) {
                continue;
            }

            $detailHtml = $this->fetchHtml($detailUrl, $proxy);
            if (empty($detailHtml)) {
                continue;
            }

            $images = $this->parseUltra4kPornGallery($detailHtml);
            if (!empty($images)) {
                return [
                    'success'      => true,
                    'source'       => 'ultra4kporn',
                    'article_url'  => $detailUrl,
                    'article_name' => $articleTitle,
                    'images'       => $images,
                    'message'      => '命中 Ultra4kPorn 官方高清剧照相册',
                ];
            }
        }

        return ['success' => false, 'source' => 'ultra4kporn', 'images' => [], 'message' => '未命中符合标题的相册'];
    }

    /**
     * 解析 Ultra4kPorn 搜索结果中的文章列表
     */
    protected function parseUltra4kPornSearchArticles(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//article//a[contains(@href, "ultra4kporn.com")] | //*[@id="content_box"]//article//a');

        $articles = [];
        $seen = [];
        foreach ($nodes as $node) {
            $href = trim($node->getAttribute('href'));
            $title = trim($node->textContent);

            // 过滤无意义的分页或标签链接
            if (empty($href) || isset($seen[$href]) || str_contains($href, '/category/') || str_contains($href, '/tag/') || str_contains($href, '/page/')) {
                continue;
            }

            $seen[$href] = true;
            $articles[] = [
                'url'   => $href,
                'title' => $title ?: basename(rtrim($href, '/')),
            ];
        }

        return $articles;
    }

    /**
     * 解析 Ultra4kPorn 详情页内的 Gallery 剧照列表
     */
    protected function parseUltra4kPornGallery(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        // 匹配 WordPress 标准 gallery 结构
        $nodes = $xpath->query('//div[contains(@class, "gallery")]//a | //*[contains(@id, "gallery")]//a | //div[contains(@class, "gallery-item")]//a');

        $images = [];
        $seen = [];
        foreach ($nodes as $aNode) {
            $largeUrl = trim($aNode->getAttribute('href'));
            if (empty($largeUrl) || isset($seen[$largeUrl])) {
                continue;
            }

            $imgTag = $aNode->getElementsByTagName('img')->item(0);
            $thumbUrl = $imgTag ? trim($imgTag->getAttribute('src')) : $largeUrl;
            $thumbWidth = $imgTag && $imgTag->getAttribute('width') ? (int) $imgTag->getAttribute('width') : 350;
            $thumbHeight = $imgTag && $imgTag->getAttribute('height') ? (int) $imgTag->getAttribute('height') : 233;

            // 过滤非图片资源
            if (!preg_match('/\.(jpe?g|png|webp|avif)/i', $largeUrl)) {
                continue;
            }

            $seen[$largeUrl] = true;
            $images[] = [
                'url'                             => $thumbUrl,
                'width'                           => $thumbWidth,
                'height'                          => $thumbHeight,
                'full_url'                        => $largeUrl,
                'full_width'                      => 1920,
                'full_height'                     => 1080,
                'source_url'                      => $thumbUrl,
                'full_source_url'                 => $largeUrl,
                'screen_img_default_original_url' => $thumbUrl,
                'screen_img_full_original_url'    => $largeUrl,
            ];
        }

        return $images;
    }

    /**
     * 从 perfect-stockings.com 备选搜索并提取切片相册
     */
    protected function searchPerfectStockings(Video $video, string $keyword, ?string $proxy = null): array
    {
        $searchUrl = self::PERFECT_STOCKINGS_SEARCH_URL . '?search=' . rawurlencode($keyword);
        $html = $this->fetchHtml($searchUrl, $proxy);
        if (empty($html)) {
            return ['success' => false, 'source' => 'perfect-stockings', 'images' => [], 'message' => '搜索响应为空'];
        }

        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $links = $xpath->query('//a[contains(@class, "gal_link")] | //div[contains(@class, "content_wrapper")]//a');

        foreach ($links as $linkNode) {
            $href = trim($linkNode->getAttribute('href'));
            if (empty($href)) {
                continue;
            }

            // 规范化 URL
            if (str_starts_with($href, '//')) {
                $detailUrl = 'https:' . $href;
            } elseif (str_starts_with($href, '/')) {
                $detailUrl = 'https://www.perfect-stockings.com' . $href;
            } else {
                $detailUrl = $href;
            }

            // 日期校对（若存在）
            $dateNode = $xpath->query('.//div[contains(@class, "gal_date")]', $linkNode)->item(0);
            if ($dateNode && !empty($video->release_at)) {
                $dateStr = trim($dateNode->textContent);
                $remoteTimestamp = strtotime($dateStr);
                $videoTimestamp = strtotime((string) $video->release_at);
                // 允许前后 14 天时间误差
                if ($remoteTimestamp && $videoTimestamp && abs($remoteTimestamp - $videoTimestamp) > 86400 * 14) {
                    continue;
                }
            }

            // 抓取详情相册页
            $detailHtml = $this->fetchHtml($detailUrl, $proxy);
            if (empty($detailHtml)) {
                continue;
            }

            $images = $this->parsePerfectStockingsGallery($detailHtml, $detailUrl);
            if (!empty($images)) {
                return [
                    'success'      => true,
                    'source'       => 'perfect-stockings',
                    'article_url'  => $detailUrl,
                    'article_name' => basename(rtrim($detailUrl, '/')),
                    'images'       => $images,
                    'message'      => '命中 Perfect-Stockings 备用剧照相册',
                ];
            }
        }

        return ['success' => false, 'source' => 'perfect-stockings', 'images' => [], 'message' => '未命中 Perfect-Stockings 相册'];
    }

    /**
     * 解析 Perfect-Stockings 详情页相册结构
     */
    protected function parsePerfectStockingsGallery(string $html, string $detailUrl): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $items = $xpath->query('//a[contains(@class, "masonry-item")] | //div[contains(@class, "masonry")]//a');

        $baseDir = rtrim($detailUrl, '/') . '/';
        $images = [];
        $seen = [];

        foreach ($items as $node) {
            $href = trim($node->getAttribute('href'));
            if (empty($href)) {
                continue;
            }

            $largeUrl = str_starts_with($href, 'http') ? $href : $baseDir . ltrim($href, '/');
            if (isset($seen[$largeUrl])) {
                continue;
            }

            $imgNode = $node->getElementsByTagName('img')->item(0);
            $thumbRel = $imgNode ? ($imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('src')) : '';
            $thumbUrl = empty($thumbRel) ? $largeUrl : (str_starts_with($thumbRel, 'http') ? $thumbRel : $baseDir . ltrim($thumbRel, '/'));

            $seen[$largeUrl] = true;
            $images[] = [
                'url'                             => $thumbUrl,
                'width'                           => 350,
                'height'                          => 233,
                'full_url'                        => $largeUrl,
                'full_width'                      => 1920,
                'full_height'                     => 1080,
                'source_url'                      => $thumbUrl,
                'full_source_url'                 => $largeUrl,
                'screen_img_default_original_url' => $thumbUrl,
                'screen_img_full_original_url'    => $largeUrl,
            ];
        }

        return $images;
    }

    /**
     * 保存抓取到的截图到数据库 VideoDetail
     */
    public function saveScreenshots(Video $video, array $images, bool $dryRun = false): array
    {
        if (empty($images) || $dryRun) {
            return [
                'saved' => false,
                'count' => count($images),
            ];
        }

        VideoDetail::updateOrCreate(
            ['video_id' => $video->id],
            ['screen_img' => $images]
        );

        return [
            'saved' => true,
            'count' => count($images),
        ];
    }

    /**
     * 校验文章标题与视频片名是否匹配（宽松归一化比对）
     */
    protected function isTitleMatch(string $videoTitle, string $articleTitle): bool
    {
        $vWords = $this->extractKeyTokens($videoTitle);
        $aWords = $this->extractKeyTokens($articleTitle);

        if (empty($vWords)) {
            return false;
        }

        $matched = 0;
        foreach ($vWords as $vw) {
            if (in_array($vw, $aWords) || str_contains(strtolower($articleTitle), $vw)) {
                $matched++;
            }
        }

        // 核心片名词汇至少匹配 60%
        return ($matched / count($vWords)) >= 0.6;
    }

    /**
     * 提取关键词元列表（过滤常见虚词）
     */
    protected function extractKeyTokens(string $text): array
    {
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $text));
        $tokens = array_filter(explode(' ', $clean), fn($w) => strlen($w) >= 3);
        $stopwords = ['and', 'the', 'for', 'with', 'from', 'scene', 'first', 'part', 'club', 'dorcel'];

        return array_values(array_diff($tokens, $stopwords));
    }

    /**
     * 发送 HTTP 请求获取网页 HTML
     */
    protected function fetchHtml(string $url, ?string $proxy = null): string
    {
        try {
            $request = Http::timeout(25)->retry(2, 500)->withHeaders($this->defaultHeaders);

            if (!empty($proxy)) {
                $request = $request->withOptions([
                    'proxy'  => $proxy,
                    'verify' => false,
                ]);
            } else {
                $request = $request->withOptions([
                    'verify' => false,
                ]);
            }

            $response = $request->get($url);
            if ($response->successful()) {
                return $response->body();
            }
        } catch (Throwable $e) {
            Log::warning("抓取第三方剧照相册异常: {$url} - " . $e->getMessage());
        }

        return '';
    }
}

