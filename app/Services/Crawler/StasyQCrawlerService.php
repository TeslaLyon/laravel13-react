<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\Channel;
use App\Models\Tag;
use App\Models\Video;
use App\Models\VideoDetail;
use Carbon\Carbon;
use Closure;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class StasyQCrawlerService
{
    public const CHANNEL_SLUG = 'stasyq';
    public const CHANNEL_NAME = 'StasyQ';
    public const BASE_URL = 'https://www.stasyq.com';
    public const RELEASES_URL = 'https://www.stasyq.com/releases';
    public const CONFIRM_AGE_URL = 'https://www.stasyq.com/confirm-age';
    public const COOKIE_CACHE_KEY = 'stasyq:crawler:cookies';

    /**
     * 控制台日志输出回调
     */
    protected ?Closure $logger = null;

    /**
     * 片商缓存
     */
    protected ?Channel $channel = null;

    /**
     * 内存会话 CookieJar
     */
    protected ?CookieJar $cookieJar = null;

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
     * 记录日志（同时输出控制台与 Laravel Log）
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            ($this->logger)($message, $level);
        }

        match ($level) {
            'error'   => Log::error("[StasyQCrawler] {$message}"),
            'warn'    => Log::warning("[StasyQCrawler] {$message}"),
            'debug'   => Log::debug("[StasyQCrawler] {$message}"),
            default   => Log::info("[StasyQCrawler] {$message}"),
        };
    }

    /**
     * 确保获取或创建 StasyQ 片商记录并注册反应器
     */
    public function ensureChannel(): Channel
    {
        if ($this->channel) {
            return $this->channel;
        }

        $avatarPath = file_exists(public_path('images/channels/stasyq.avif'))
            ? '/images/channels/stasyq.avif'
            : (file_exists(public_path('images/channels/stasyq.webp')) ? '/images/channels/stasyq.webp' : 'https://www.stasyq.com/assets/img/logo.svg');

        $logoPath = file_exists(public_path('images/channels/stasyq.avif'))
            ? '/images/channels/stasyq.avif'
            : (file_exists(public_path('images/channels/stasyq-logo.avif')) ? '/images/channels/stasyq-logo.avif' : 'https://www.stasyq.com/assets/img/logo.svg');

        $this->channel = Channel::firstOrCreate(
            ['slug' => self::CHANNEL_SLUG],
            [
                'name'                 => self::CHANNEL_NAME,
                'slug'                 => self::CHANNEL_SLUG,
                'data_crawl_type'      => 1,
                'avatar'               => $avatarPath,
                'logo'                 => $logoPath,
                'official_website_url' => self::BASE_URL,
                'video_num'            => 0,
                'follow_num'           => 0,
            ]
        );

        $updates = [];
        if ((int) $this->channel->data_crawl_type !== 1) {
            $updates['data_crawl_type'] = 1;
        }
        if (empty($this->channel->official_website_url)) {
            $updates['official_website_url'] = self::BASE_URL;
        }
        if (!empty($updates)) {
            $this->channel->update($updates);
        }

        if ($this->channel->isNotRegisteredAsLoveReactant()) {
            $this->channel->registerAsLoveReactant();
        }

        return $this->channel;
    }

    /**
     * 获取或恢复会话 CookieJar（支持 Cache 持久化复用）
     */
    protected function getCookieJar(): CookieJar
    {
        if ($this->cookieJar instanceof CookieJar) {
            return $this->cookieJar;
        }

        $cachedCookies = Cache::get(self::COOKIE_CACHE_KEY);
        if (is_array($cachedCookies) && !empty($cachedCookies)) {
            $this->cookieJar = new CookieJar(false, $cachedCookies);
        } else {
            $this->cookieJar = new CookieJar();
        }

        return $this->cookieJar;
    }

    /**
     * 将已通过年龄验证的 CookieJar 持久化至 Cache
     */
    protected function saveCookieJar(CookieJar $jar): void
    {
        $this->cookieJar = $jar;
        $cookiesArray = $jar->toArray();
        if (!empty($cookiesArray)) {
            Cache::put(self::COOKIE_CACHE_KEY, $cookiesArray, now()->addHours(2));
        }
    }

    /**
     * 检测页面是否为 18+ 年龄确认拦截页
     */
    protected function isAgeGatePage(string $html): bool
    {
        return str_contains($html, 'https://www.stasyq.com/confirm-age')
            || (str_contains($html, 'confirm-age') && str_contains($html, 'are 18+?'));
    }

    /**
     * 自动提交 StasyQ 18+ 年龄确认表单获取通行 Cookie
     */
    protected function passAgeGate(string $gateHtml, CookieJar $jar, array $headers, ?string $proxy): bool
    {
        if (!preg_match('~<input[^>]*name="_token"[^>]*value="([^"]+)"~i', $gateHtml, $m)) {
            $this->log("⚠️ 年龄确认页面未匹配到 CSRF _token 字段", 'warn');
            return false;
        }

        $csrfToken = $m[1];
        $this->log("🔞 检测到 StasyQ 18+ 年龄确认拦截页，正在自动提交确认表单...");

        $postHeaders = array_merge($headers, [
            'origin'       => self::BASE_URL,
            'referer'      => self::CONFIRM_AGE_URL,
            'content-type' => 'application/x-www-form-urlencoded',
        ]);

        $req = Http::timeout(25)
            ->withHeaders($postHeaders)
            ->withOptions([
                'cookies'         => $jar,
                'verify'          => false,
                'allow_redirects' => ['track_redirects' => true],
            ]);

        if (!empty($proxy)) {
            $req = $req->withOptions(['proxy' => $proxy]);
        }

        $res = $req->asForm()->post(self::CONFIRM_AGE_URL, [
            '_token' => $csrfToken,
            'age'    => 'true',
        ]);

        if ($res->successful() || $res->status() === 302) {
            $this->saveCookieJar($jar);
            $this->log("✅ 已成功通过 18+ 年龄验证并缓存通行 Cookie！");
            return true;
        }

        $this->log("❌ 提交年龄确认表单失败 [HTTP {$res->status()}]", 'error');
        return false;
    }

    /**
     * 通用 HTTP GET 请求封装（自动通过 18+ 年龄验证并复用 Cookie，命中 Cloudflare 阻断时调度 FlareSolverr）
     */
    protected function fetchHtml(string $url): ?string
    {
        $proxy = config('services.crawler.proxy');
        $userAgent = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
        $flaresolverrUrl = config('services.flaresolverr.url', env('FLARESOLVERR_URL', 'http://flaresolverr:8191/v1'));

        $headers = [
            'user-agent'                => $userAgent,
            'accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'accept-language'           => 'en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7',
            'referer'                   => self::BASE_URL . '/',
            'sec-ch-ua'                 => '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
            'sec-ch-ua-mobile'          => '?0',
            'sec-ch-ua-platform'        => '"macOS"',
            'sec-fetch-dest'            => 'document',
            'sec-fetch-mode'            => 'navigate',
            'sec-fetch-site'            => 'same-origin',
            'upgrade-insecure-requests' => '1',
        ];

        $jar = $this->getCookieJar();

        try {
            $buildRequest = function () use ($headers, $jar, $proxy) {
                $req = Http::timeout(25)
                    ->retry(2, 500, throw: false)
                    ->withHeaders($headers)
                    ->withOptions([
                        'cookies' => $jar,
                        'verify'  => false,
                    ]);

                if (!empty($proxy)) {
                    $req = $req->withOptions(['proxy' => $proxy]);
                }

                return $req;
            };

            $response = $buildRequest()->get($url);
            $statusCode = $response->status();
            $body = $response->body();

            // 若命中 18+ 年龄确认页面，自动 POST 确认并重新拉取目标 URL
            if ($response->successful() && $this->isAgeGatePage($body)) {
                $passed = $this->passAgeGate($body, $jar, $headers, $proxy);
                if ($passed) {
                    $response = $buildRequest()->get($url);
                    $statusCode = $response->status();
                    $body = $response->body();
                }
            }

            $isBlocked = $statusCode === 403
                || $statusCode === 503
                || str_contains($body, 'Just a moment...')
                || str_contains($body, 'Attention Required!')
                || str_contains($body, 'cf-browser-verification')
                || str_contains($body, 'not allowed by policy');

            if (!$isBlocked && $response->successful() && strlen($body) > 1000 && !$this->isAgeGatePage($body)) {
                $this->saveCookieJar($jar);
                $this->log("🌐 直连获取成功 [HTTP {$statusCode}, " . number_format(strlen($body)) . " bytes]: {$url}");
                return $body;
            }

            if ($isBlocked && !empty($flaresolverrUrl)) {
                $this->log("⚠️ 目标页面命中防护阻断 [HTTP {$statusCode}, " . strlen($body) . " bytes]，自动调度 FlareSolverr 穿透: {$url}", 'warn');
                return $this->fetchViaFlareSolverr($flaresolverrUrl, $url);
            }

            $this->log("❌ 请求异常 [HTTP {$statusCode}, " . strlen($body) . " bytes]: {$url}", 'error');
            return $response->successful() ? $body : null;
        } catch (Throwable $e) {
            $this->log("⚠️ HTTP 直连异常 [{$url}]: {$e->getMessage()}，尝试调度 FlareSolverr...", 'warn');
            if (!empty($flaresolverrUrl)) {
                return $this->fetchViaFlareSolverr($flaresolverrUrl, $url);
            }
            return null;
        }
    }

    /**
     * 调度 FlareSolverr 求解并获取页面 HTML
     */
    protected function fetchViaFlareSolverr(string $flaresolverrUrl, string $url): ?string
    {
        $cleanUrl = rtrim($flaresolverrUrl, '/');
        $endpoint = str_ends_with($cleanUrl, '/v1') ? $cleanUrl : "{$cleanUrl}/v1";
        $sessionId = 'sess_stasyq';

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
                $html = $data['solution']['response'];
                $this->log("🛡️ FlareSolverr 穿透成功 [" . number_format(strlen($html)) . " bytes]: {$url}");
                return $html;
            }

            $this->log("❌ FlareSolverr 穿透失败: " . ($data['message'] ?? $response->body()), 'error');
            $this->destroyFlareSolverrSession($endpoint, $sessionId);
            return null;
        } catch (Throwable $e) {
            $this->log("❌ FlareSolverr 请求异常: " . $e->getMessage(), 'error');
            $this->destroyFlareSolverrSession($endpoint, $sessionId);
            return null;
        }
    }

    /**
     * 销毁 FlareSolverr 会话
     */
    protected function destroyFlareSolverrSession(string $endpoint, string $sessionId): void
    {
        unset($this->readyFlareSolverrSessions[$sessionId]);
    }

    /**
     * 抓取指定分页的列表页 HTML
     * - 第 1 页请求 https://www.stasyq.com/releases
     * - 第 N 页请求 https://www.stasyq.com/releases?page={N}
     */
    public function fetchListPageHtml(int $page = 1): ?string
    {
        $url = $page <= 1 ? self::RELEASES_URL : self::RELEASES_URL . "?page={$page}";
        $this->log("正在抓取列表页 (第 {$page} 页): {$url}");
        return $this->fetchHtml($url);
    }

    /**
     * 从分页 HTML 中检测是否还有下一页
     */
    public function hasNextPage(string $html, int $currentPage): bool
    {
        if (empty(trim($html))) {
            return false;
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // 1. 检查 rel="next" 分页链接
        $nextLink = $xpath->query('//ul[contains(@class, "pagination")]//a[@rel="next"] | //a[@rel="next"]');
        if ($nextLink->length > 0) {
            return true;
        }

        // 2. 检查是否有 page={currentPage + 1} 的链接
        $nextPage = $currentPage + 1;
        $pageLinks = $xpath->query("//a[contains(@href, 'page={$nextPage}')]");
        if ($pageLinks->length > 0) {
            return true;
        }

        return false;
    }

    /**
     * 安全获取 DOM 节点属性值（避免 DOMNode 缺少 getAttribute 引发静态分析错误与运行时异常）
     */
    protected function attr(?DOMNode $node, string $attribute, string $default = ''): string
    {
        if ($node instanceof DOMElement) {
            return trim($node->getAttribute($attribute));
        }

        return $default;
    }

    /**
     * 核心列表页 HTML 解析器
     * 仅提取正式发布的视频卡片（自动忽略 Upcoming Releases、Interviews、Models Directory）
     *
     * @param string $html
     * @return array<int, array>
     */
    public function parseReleasesHtml(string $html): array
    {
        if (empty(trim($html))) {
            return [];
        }

        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // 精准定位正片卡片：仅匹配同时具备 release-preview-card 与 video-preview 类名的容器
        // 这样会自动跳过 Upcoming releases、Interviews (interview-item) 和 Models (model-preview-card)
        $cards = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " release-preview-card ") and contains(concat(" ", normalize-space(@class), " "), " video-preview ")]');

        $results = [];
        $seenUrls = [];

        foreach ($cards as $card) {
            // 1. 二次过滤：排除包含 watch before release 的未上线预告
            $upcomingBtn = $xpath->query('.//a[contains(text(), "watch before release")]', $card);
            if ($upcomingBtn->length > 0) {
                continue;
            }

            // 2. 提取标题与详情页 URL
            $titleNode = $xpath->query('.//div[contains(@class, "release-preview-card__title")]//a', $card)->item(0);
            if (!$titleNode) {
                continue;
            }

            $url = $this->attr($titleNode, 'href');
            $title = trim($titleNode->textContent);

            if (!empty($url) && !str_starts_with($url, 'http')) {
                $url = self::BASE_URL . '/' . ltrim($url, '/');
            }

            // 仅保留 /releases/ 下的正式视频页面
            if (!str_contains($url, '/releases/')) {
                continue;
            }

            if (isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;

            $slug = basename(parse_url($url, PHP_URL_PATH) ?: '');

            // 3. 提取封面图
            $imgNode = $xpath->query('.//a[contains(@class, "release-preview-card__img")]//img', $card)->item(0);
            if (!$imgNode) {
                $imgNode = $xpath->query('.//img', $card)->item(0);
            }
            $coverUrl = null;
            if ($imgNode) {
                $coverUrl = $this->attr($imgNode, 'src')
                    ?: $this->attr($imgNode, 'data-src')
                    ?: $this->attr($imgNode, 'data-flickity-lazyload');
                if (!empty($coverUrl) && !str_starts_with($coverUrl, 'http')) {
                    $coverUrl = self::BASE_URL . '/' . ltrim($coverUrl, '/');
                }
            }

            // 4. 提取预览视频 MP4 直链
            $previewVideo = $this->attr($card, 'data-video-src');

            // 5. 提取 4K / 清晰度标签
            $markNodes = $xpath->query('.//div[contains(@class, "card-marks")]//span', $card);
            $is4k = false;
            foreach ($markNodes as $m) {
                if (stripos($m->textContent, '4k') !== false) {
                    $is4k = true;
                    break;
                }
            }

            // 6. 提取发布日期
            $dateNode = $xpath->query('.//div[contains(@class, "text-grey-card")]/p', $card)->item(0);
            $releaseDate = $dateNode ? trim($dateNode->textContent) : null;

            // 7. 提取播放量 / 浏览量 views
            $viewsNode = $xpath->query('.//div[contains(@class, "views")]/p', $card)->item(0);
            $views = $viewsNode ? (int) trim($viewsNode->textContent) : 0;

            // 8. 提取源站内部 ID（收藏按钮上的 data-id）
            $favBtn = $xpath->query('.//button[contains(@class, "card-control-tool--fav")]', $card)->item(0);
            $sourceId = $favBtn ? ($this->attr($favBtn, 'data-id') ?: null) : null;

            // 9. 提取出演模特 / 演员
            $modelNodes = $xpath->query('.//div[contains(@class, "model-name")]//a', $card);
            $models = [];
            foreach ($modelNodes as $mNode) {
                $mUrl = $this->attr($mNode, 'href');
                if (!empty($mUrl) && !str_starts_with($mUrl, 'http')) {
                    $mUrl = self::BASE_URL . '/' . ltrim($mUrl, '/');
                }
                $models[] = [
                    'name' => trim($mNode->textContent),
                    'url'  => $mUrl,
                    'slug' => basename(parse_url($mUrl, PHP_URL_PATH) ?: ''),
                ];
            }

            $results[] = [
                'source_id'     => $sourceId,
                'title'         => $title,
                'url'           => $url,
                'slug'          => $slug,
                'cover_img'     => $coverUrl,
                'video_preview' => $previewVideo,
                'is_4k'         => $is4k,
                'release_date'  => $releaseDate,
                'views'         => $views,
                'models'        => $models,
            ];
        }

        return $results;
    }

    /**
     * 请求并解析单个视频详情页
     *
     * @param string $detailUrl
     * @param string|null $localHtml 若传入本地 HTML 则直接离线解析
     * @return array{
     *     large_img: ?string,
     *     list_img_large_meta: array,
     *     screen_img: array,
     *     description: string,
     *     tags: array<string>
     * }
     */
    public function crawlDetail(string $detailUrl, ?string $localHtml = null): array
    {
        $html = $localHtml !== null ? $localHtml : $this->fetchHtml($detailUrl);
        if (empty($html)) {
            return [
                'large_img'           => null,
                'list_img_large_meta' => [],
                'screen_img'          => [],
                'description'         => '',
                'tags'                => [],
            ];
        }

        return $this->parseDetailHtml($html);
    }

    /**
     * 核心详情页 HTML 解析器：提取大图、Photos 预览图集、简介、标签
     *
     * @param string $html
     * @return array
     */
    public function parseDetailHtml(string $html): array
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);

        // 1. 提取详情页高画质大图 (list_img_large_meta)
        // 优先提取播放器 safariPlaylist / otherBrowsersPlaylist 首条记录中的大图 (partners/...webp)，兜底使用 .video-box img 及 og:image (for-sale/...jpeg)
        $largeImgUrl = null;
        if (preg_match('~(?:safariPlaylist|otherBrowsersPlaylist)\s*=\s*\[\s*\{\s*image:\s*[\'"]([^\'"]+)[\'"]~is', $html, $m)) {
            $largeImgUrl = trim($m[1]);
        }
        if (empty($largeImgUrl)) {
            $videoImg = $xpath->query('//div[contains(@class, "video-box")]//img')->item(0);
            if ($videoImg) {
                $largeImgUrl = $this->attr($videoImg, 'src') ?: $this->attr($videoImg, 'data-src');
            }
        }
        if (empty($largeImgUrl)) {
            $ogImgNode = $xpath->query('//meta[@property="og:image"]')->item(0);
            if ($ogImgNode) {
                $largeImgUrl = $this->attr($ogImgNode, 'content');
            }
        }
        if (!empty($largeImgUrl) && !str_starts_with($largeImgUrl, 'http')) {
            $largeImgUrl = self::BASE_URL . '/' . ltrim($largeImgUrl, '/');
        }

        $listImgLargeMeta = !empty($largeImgUrl) ? $this->buildLargeImgMetaFromUrl($largeImgUrl) : [];

        // 2. 提取 Photos 预览图集 (screen_img) - 单一尺寸直接存入 source_url 与 full_source_url
        $screenImg = [];
        $seenPhotos = [];
        $photoLinks = $xpath->query('//a[starts-with(@data-fancybox, "galleryGirl")]');
        if ($photoLinks->length === 0) {
            $photoLinks = $xpath->query('//div[contains(@class, "js-release-gallery")]//a[@data-fancybox]');
        }

        foreach ($photoLinks as $linkNode) {
            $photoUrl = $this->attr($linkNode, 'href');
            if (empty($photoUrl)) {
                $imgChild = $xpath->query('.//img', $linkNode)->item(0);
                $photoUrl = $imgChild ? $this->attr($imgChild, 'src') : '';
            }

            if (!empty($photoUrl)) {
                if (!str_starts_with($photoUrl, 'http')) {
                    $photoUrl = self::BASE_URL . '/' . ltrim($photoUrl, '/');
                }
                if (!isset($seenPhotos[$photoUrl])) {
                    $seenPhotos[$photoUrl] = true;
                    $screenImg[] = [
                        'source_url'      => $photoUrl,
                        'width'           => 0,
                        'height'          => 0,
                        'full_source_url' => $photoUrl,
                        'full_width'      => 0,
                        'full_height'     => 0,
                    ];
                }
            }
        }

        // 3. 提取视频简介 (About Release)
        $description = '';
        $descNode = $xpath->query('//div[contains(@class, "about-section__text")]')->item(0);
        if ($descNode) {
            $description = trim(preg_replace('/\s+/', ' ', $descNode->textContent));
        }

        // 4. 提取标签 (Tags，包含默认可见标签与 #hiddenTags 隐藏标签)
        $tags = [];
        $tagNodes = $xpath->query('//div[contains(@class, "about-section__title-text")]//a[contains(@class, "tag")]');
        foreach ($tagNodes as $tagNode) {
            $tagName = trim($tagNode->textContent);
            if (!empty($tagName) && !in_array($tagName, $tags, true)) {
                $tags[] = $tagName;
            }
        }

        return [
            'large_img'           => $largeImgUrl,
            'list_img_large_meta' => $listImgLargeMeta,
            'screen_img'          => $screenImg,
            'description'         => $description,
            'tags'                => $tags,
        ];
    }

    /**
     * 执行视频入库/更新（含列表元数据 + 详情页大图、Photos 图集、简介、标签）
     *
     * @param array $item 列表页解析的视频信息
     * @param bool $fetchDetail 是否请求详情页补充大图与 Photos
     * @param bool $skipExisting 若数据库已存在且详情完整则跳过详情页网络请求
     * @param string|null $localDetailHtml 本地离线详情 HTML（仅调试/单条测试使用）
     * @return Video|null
     */
    public function saveOrUpdateVideo(array $item, bool $fetchDetail = true, bool $skipExisting = false, ?string $localDetailHtml = null): ?Video
    {
        $channel = $this->ensureChannel();
        $sourceId = $item['source_id'] ?: $item['slug'];
        $sourceUuid = "stasyq-{$sourceId}";
        $videoCode = $this->generateVideoCode($item);

        // 先查是否已存在
        $existing = Video::where('source_uuid', $sourceUuid)
            ->orWhere(function ($q) use ($channel, $videoCode) {
                $q->where('channel_id', $channel->id)->where('video_code', $videoCode);
            })
            ->first();

        $existingDetail = $existing ? VideoDetail::where('video_id', $existing->id)->first() : null;

        // 判断是否可以跳过详情页抓取
        $needDetail = $fetchDetail;
        if ($skipExisting && $existing && $existingDetail && !empty($existingDetail->screen_img) && !empty($existingDetail->list_img_large_meta)) {
            $needDetail = false;
        }

        $detailData = [
            'list_img_large_meta' => [],
            'screen_img'          => [],
            'description'         => '',
            'tags'                => [],
        ];

        if ($needDetail) {
            if ($localDetailHtml !== null) {
                $detailData = $this->parseDetailHtml($localDetailHtml);
            } elseif (!empty($item['url'])) {
                $detailData = $this->crawlDetail($item['url']);
            }
        }

        $releaseAt = $this->parseReleaseDate($item['release_date']);
        $listImg = !empty($item['cover_img']) ? $this->buildListImgFromSingleUrl($item['cover_img']) : [];
        $preview = !empty($item['video_preview']) ? ('1<' . $item['video_preview']) : null;

        return DB::transaction(function () use ($channel, $item, $existing, $existingDetail, $detailData, $sourceUuid, $sourceId, $videoCode, $releaseAt, $listImg, $preview) {
            $videoData = [
                'name'               => $item['title'],
                'video_code'         => $videoCode,
                'channel_id'         => $channel->id,
                'source_uuid'        => $sourceUuid,
                'is_4k'              => (bool) ($item['is_4k'] ?? false),
                'max_quality'        => !empty($item['is_4k']) ? '2160p' : '1080p',
                'sexual_orientation' => 1,
                'status'             => 1,
            ];

            if ($existing) {
                $updates = [];
                if (empty($existing->name) && !empty($videoData['name'])) {
                    $updates['name'] = $videoData['name'];
                }
                if (empty($existing->video_code) && !empty($videoCode)) {
                    $updates['video_code'] = $videoCode;
                }
                if (empty($existing->list_img) && !empty($listImg)) {
                    $updates['list_img'] = $listImg;
                }
                if (empty($existing->preview) && !empty($preview)) {
                    $updates['preview'] = $preview;
                }
                if (empty($existing->release_at) && !empty($releaseAt)) {
                    $updates['release_at'] = $releaseAt;
                }
                if (!$existing->is_4k && !empty($videoData['is_4k'])) {
                    $updates['is_4k'] = true;
                    $updates['max_quality'] = '2160p';
                }
                if (empty($existing->source_uuid)) {
                    $updates['source_uuid'] = $sourceUuid;
                }
                if (!empty($updates)) {
                    $existing->update($updates);
                }
                $video = $existing;
            } else {
                $videoData['slug'] = "stasyq-{$sourceId}-" . Str::slug($item['title']);
                $videoData['list_img'] = $listImg;
                $videoData['preview'] = $preview;
                $videoData['release_at'] = $releaseAt;
                $videoData['views_count'] = (int) ($item['views'] ?? 0);

                $video = Video::create($videoData);
            }

            // 维护 VideoDetail 附表（防御性补充大图、Photos 预览图、简介）
            if ($existingDetail) {
                $detailUpdates = [];
                if (empty(trim($existingDetail->description ?? '')) && !empty($detailData['description'])) {
                    $detailUpdates['description'] = $detailData['description'];
                }
                if (empty($existingDetail->screen_img) && !empty($detailData['screen_img'])) {
                    $detailUpdates['screen_img'] = $detailData['screen_img'];
                }
                if (empty($existingDetail->list_img_large_meta) && !empty($detailData['list_img_large_meta'])) {
                    $detailUpdates['list_img_large_meta'] = $detailData['list_img_large_meta'];
                }
                if (!empty($detailUpdates)) {
                    $existingDetail->update($detailUpdates);
                }
            } else {
                VideoDetail::create([
                    'video_id'            => $video->id,
                    'description'         => $detailData['description'] ?? '',
                    'screen_img'          => $detailData['screen_img'] ?? [],
                    'list_img_large_meta' => $detailData['list_img_large_meta'] ?? [],
                ]);
            }

            // 关联出演模特/演员
            if (!empty($item['models']) && is_array($item['models'])) {
                $actorIds = [];
                foreach ($item['models'] as $m) {
                    $actorName = trim($m['name'] ?? '');
                    if (empty($actorName)) {
                        continue;
                    }
                    $actorSlug = !empty($m['slug']) ? $m['slug'] : Str::slug($actorName);
                    $actor = Actor::firstOrCreate(
                        ['name' => $actorName],
                        [
                            'slug'   => $actorSlug,
                            'gender' => 2,
                        ]
                    );
                    $actorIds[] = $actor->id;
                }
                if (!empty($actorIds)) {
                    $video->actors()->syncWithoutDetaching($actorIds);
                }
            }

            // 关联标签 (Tags)
            if (!empty($detailData['tags'])) {
                $tagIds = [];
                foreach ($detailData['tags'] as $tagName) {
                    $normalizedName = Str::title(strtolower(trim($tagName)));
                    if (empty($normalizedName)) {
                        continue;
                    }
                    $tag = Tag::firstOrCreate(
                        ['slug' => Str::slug($normalizedName)],
                        ['name' => $normalizedName]
                    );
                    $tagIds[] = $tag->id;
                }
                if (!empty($tagIds)) {
                    $video->tags()->syncWithoutDetaching($tagIds);
                }
            }

            return $video;
        });
    }

    /**
     * 同步并刷新片商的总视频数
     */
    public function syncChannelVideoNum(): void
    {
        if ($this->channel) {
            $count = Video::where('channel_id', $this->channel->id)->count();
            $this->channel->update(['video_num' => $count]);
        }
    }

    /**
     * 生成规范的视频编号
     */
    protected function generateVideoCode(array $item): string
    {
        $title = trim($item['title'] ?? '');
        if (preg_match('~^StasyQ\s+(\d+)$~i', $title, $m)) {
            return "StasyQ.{$m[1]}";
        }

        $datePart = '';
        if (!empty($item['release_date'])) {
            $ts = strtotime($item['release_date']);
            if ($ts) {
                $datePart = date('y.m.d.', $ts);
            }
        }

        $modelsPart = '';
        if (!empty($item['models'])) {
            $names = [];
            foreach ($item['models'] as $m) {
                $cleaned = preg_replace('/[^\p{L}\p{N}]+/u', '', $m['name'] ?? '');
                if ($cleaned) {
                    $names[] = $cleaned;
                }
            }
            if (!empty($names)) {
                $modelsPart = implode('.And.', $names) . '.';
            }
        }

        $slugWords = explode('-', Str::slug($title));
        $cleanTitle = implode('.', array_map('ucfirst', array_filter($slugWords)));

        return "StasyQ.{$datePart}{$modelsPart}{$cleanTitle}";
    }

    /**
     * 解析发布日期并转为标准 DateTime 字符串
     */
    protected function parseReleaseDate(?string $dateStr): string
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
     * 将单个封面图片 URL 包装为响应式 list_img 结构
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

    /**
     * 将详情页大图 URL 包装为标准响应式 list_img_large_meta 结构
     */
    public function buildLargeImgMetaFromUrl(string $url): array
    {
        return [
            [
                'src_source'         => $url,
                'placeholder_source' => '',
                'width'              => 1920,
                'height'             => 1080,
                'media'              => '(max-width: 768px)',
                'highdpi'            => [
                    'double_source' => $url,
                ],
            ],
            [
                'src_source'         => $url,
                'placeholder_source' => '',
                'width'              => 1920,
                'height'             => 1080,
                'media'              => '(min-width: 769px)',
                'highdpi'            => [
                    'double_source' => $url,
                ],
            ],
        ];
    }
}
