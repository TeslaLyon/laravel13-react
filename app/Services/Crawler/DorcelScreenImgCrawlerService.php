<?php

namespace App\Services\Crawler;

use App\Models\Channel;
use App\Models\Video;
use App\Models\VideoDetail;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
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
        string $channelSlug = self::CHANNEL_SLUG,
        string $orderBy = 'release_at'
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

        if ($orderBy === 'id') {
            $query->orderByDesc('id');
            return $query;
        }

        // 默认优先按发布时间倒序（最新视频优先，无发布日期的排在末尾），日期相同时按 ID 倒序
        $query->orderByRaw('release_at DESC NULLS LAST')->orderByDesc('id');
        return $query;
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
            if (!$this->isTitleMatch($video->name, $articleTitle, $video)) {
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
            if (!$node instanceof DOMElement) {
                continue;
            }
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
            if (!$aNode instanceof DOMElement) {
                continue;
            }
            $largeUrl = trim($aNode->getAttribute('href'));
            if (empty($largeUrl) || isset($seen[$largeUrl])) {
                continue;
            }

            $imgTag = $aNode->getElementsByTagName('img')->item(0);
            $thumbUrl = ($imgTag instanceof DOMElement) ? trim($imgTag->getAttribute('src')) : $largeUrl;
            $thumbWidth = ($imgTag instanceof DOMElement && $imgTag->getAttribute('width')) ? (int) $imgTag->getAttribute('width') : 350;
            $thumbHeight = ($imgTag instanceof DOMElement && $imgTag->getAttribute('height')) ? (int) $imgTag->getAttribute('height') : 233;

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
            if (!$linkNode instanceof DOMElement) {
                continue;
            }
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
            if (!$node instanceof DOMElement) {
                continue;
            }
            $href = trim($node->getAttribute('href'));
            if (empty($href)) {
                continue;
            }

            $largeUrl = str_starts_with($href, 'http') ? $href : $baseDir . ltrim($href, '/');
            if (isset($seen[$largeUrl])) {
                continue;
            }

            $imgNode = $node->getElementsByTagName('img')->item(0);
            $thumbRel = ($imgNode instanceof DOMElement) ? ($imgNode->getAttribute('data-src') ?: $imgNode->getAttribute('src')) : '';
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
    protected function isTitleMatch(string $videoTitle, string $articleTitle, ?Video $video = null): bool
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

        // 核心片名词汇至少匹配 50% (如 4 个词匹配 2 个：shared fantasy)
        if (($matched / count($vWords)) >= 0.5) {
            return true;
        }

        // 或者：若文章中包含视频的演员名字，且至少命中了 1 个片名核心词汇
        if ($video && $video->relationLoaded('actors') && $video->actors->isNotEmpty()) {
            $artLower = strtolower($articleTitle);
            $hasActor = false;
            foreach ($video->actors as $actor) {
                $actorParts = array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', (string) $actor->name))), fn($p) => strlen($p) >= 3);
                foreach ($actorParts as $part) {
                    if (str_contains($artLower, $part)) {
                        $hasActor = true;
                        break 2;
                    }
                }
            }
            if ($hasActor && $matched >= 1) {
                return true;
            }
        }

        return false;
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

    /**
     * 判断指定视频是否处于剧照搜索失败冷却期内（近期未搜到，避免频繁重复请求）
     */
    public function isCoolingDown(int $videoId): bool
    {
        try {
            if (class_exists(Redis::class)) {
                $exists = Redis::exists("crawler:dorcel:screen_img:cooldown:{$videoId}");
                if ($exists) {
                    return true;
                }
            }
        } catch (Throwable) {}

        return Cache::has("crawler:dorcel:screen_img:cooldown:{$videoId}");
    }

    /**
     * 将搜索未匹配/无相册的视频加入冷却期（默认 7 天）
     */
    public function markCoolingDown(int $videoId, int $days = 7): void
    {
        if ($days <= 0) {
            return;
        }

        $ttlSeconds = $days * 86400;
        try {
            if (class_exists(Redis::class)) {
                Redis::setex("crawler:dorcel:screen_img:cooldown:{$videoId}", $ttlSeconds, '1');
            }
        } catch (Throwable) {}

        Cache::put("crawler:dorcel:screen_img:cooldown:{$videoId}", '1', $ttlSeconds);
    }

    /**
     * 清除指定视频的剧照冷却期标记（例如匹配成功或手动重新测试时）
     */
    public function clearCoolingDown(int $videoId): void
    {
        try {
            if (class_exists(Redis::class)) {
                Redis::del("crawler:dorcel:screen_img:cooldown:{$videoId}");
            }
        } catch (Throwable) {}

        Cache::forget("crawler:dorcel:screen_img:cooldown:{$videoId}");
    }

    /**
     * 一键清空所有视频的剧照冷却期标记
     */
    public function clearAllCoolingDown(): int
    {
        $count = 0;
        try {
            if (class_exists(Redis::class)) {
                /** @var array<int, string> $keys */
                $keys = (array) Redis::keys('*crawler:dorcel:screen_img:cooldown:*');
                if (!empty($keys)) {
                    foreach ($keys as $k) {
                        if (preg_match('/crawler:dorcel:screen_img:cooldown:(\d+)$/', $k, $m)) {
                            Redis::del("crawler:dorcel:screen_img:cooldown:{$m[1]}");
                        } else {
                            Redis::del($k);
                        }
                        $count++;
                    }
                }
            }
        } catch (Throwable) {}

        return $count;
    }

    /**
     * 获取当前处于剧照冷静期中的视频总数量
     */
    public function getCoolingDownCount(): int
    {
        try {
            if (class_exists(Redis::class)) {
                /** @var array<int, string> $keys */
                $keys = (array) Redis::keys('*crawler:dorcel:screen_img:cooldown:*');
                return count($keys);
            }
        } catch (Throwable) {}

        return 0;
    }

    /**
     * 发送 Telegram 抓取汇总报告（含未匹配相册的视频 ID 与 Slug 列表，自动长消息分批推送）
     */
    public function sendTelegramSummary(array $stats, array $unmatchedVideos = []): bool
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (empty($botToken) || empty($chatId)) {
            Log::info("未配置 TELEGRAM_BOT_TOKEN 或 TELEGRAM_CHAT_ID，跳过 Telegram 通知发送。");
            return false;
        }

        $formattedDuration = $stats['duration'] ?? '-';
        $totalVideos = $stats['total_videos'] ?? 0;
        $processedVideos = $stats['processed_videos'] ?? 0;
        $successVideos = $stats['success_videos'] ?? 0;
        $u4pSolved = $stats['u4p_solved'] ?? 0;
        $psSolved = $stats['ps_solved'] ?? 0;
        $emptyVideos = $stats['empty_videos'] ?? 0;
        $totalImages = $stats['total_images'] ?? 0;

        // 1. 构建主汇总消息
        $lines = [
            "📸 <b>DorcelClub 剧照抓取执行报告</b>",
            "━━━━━━━━━━━━━━━━━━━━",
            "📊 <b>目标视频总数</b>: {$totalVideos} 部",
            "⚡ <b>实际处理视频</b>: {$processedVideos} 部",
            "✅ <b>成功抓取相册</b>: {$successVideos} 部",
            "   ├─ Ultra4kPorn: {$u4pSolved} 部",
            "   └─ Perfect-Stockings: {$psSolved} 部",
            "⚠️ <b>未搜到剧照相册</b>: {$emptyVideos} 部",
            "🖼️ <b>抓取切片图片总量</b>: {$totalImages} 张",
            "⏱️ <b>任务执行总耗时</b>: {$formattedDuration}",
        ];

        if (empty($unmatchedVideos)) {
            $lines[] = "";
            $lines[] = "🎉 <b>太棒了！所有处理的视频均成功匹配到了相册剧照！</b>";
            return $this->sendTelegramMessage($botToken, $chatId, implode("\n", $lines));
        }

        $unmatchedCount = count($unmatchedVideos);
        $lines[] = "";
        $lines[] = "📋 <b>未搜到相册的视频清单 (共 {$unmatchedCount} 部):</b>";

        // Telegram 单条限制 4096 字符，每条列表分批发送（每批 35 部）
        $chunks = array_chunk($unmatchedVideos, 35);

        // 发送首条主消息包含第一批列表
        $firstBatch = $chunks[0];
        foreach ($firstBatch as $item) {
            $slug = htmlspecialchars($item['slug'] ?? 'video', ENT_QUOTES);
            $name = htmlspecialchars($item['name'] ?? '', ENT_QUOTES);
            $lines[] = "• <code>[{$item['id']}]</code> {$slug}" . (!empty($name) ? " (<i>{$name}</i>)" : "");
        }

        if (count($chunks) > 1) {
            $lines[] = "";
            $lines[] = "<i>(未完待续，剩余清单将在接下来的消息中推送...)</i>";
        }

        $firstResult = $this->sendTelegramMessage($botToken, $chatId, implode("\n", $lines));

        // 发送后续批次
        for ($i = 1; $i < count($chunks); $i++) {
            $batchLines = [
                "📋 <b>未搜到相册的视频清单 (续 " . ($i + 1) . "/" . count($chunks) . "):</b>",
            ];
            foreach ($chunks[$i] as $item) {
                $slug = htmlspecialchars($item['slug'] ?? 'video', ENT_QUOTES);
                $name = htmlspecialchars($item['name'] ?? '', ENT_QUOTES);
                $batchLines[] = "• <code>[{$item['id']}]</code> {$slug}" . (!empty($name) ? " (<i>{$name}</i>)" : "");
            }
            $this->sendTelegramMessage($botToken, $chatId, implode("\n", $batchLines));
        }

        return $firstResult;
    }

    /**
     * 发送单条 Telegram HTML 消息
     */
    protected function sendTelegramMessage(string $botToken, string $chatId, string $htmlContent): bool
    {
        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id'                  => $chatId,
                'text'                     => $htmlContent,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            return $response->successful();
        } catch (Throwable $e) {
            Log::warning("发送 Telegram 消息失败: " . $e->getMessage());
            return false;
        }
    }
}

