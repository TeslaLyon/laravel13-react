<?php

namespace App\Services\Crawler;

use App\Models\Video;
use App\Models\VideoDownload;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class XxxclubCrawlerService
{
    /**
     * 基础搜索 URL
     */
    protected string $baseUrl = 'https://xxxclub.to/torrents/search/all';

    /**
     * 公共 Trackers 列表（附加在磁力链接后以便 BT 客户端更迅速寻轨做种）
     */
    protected array $publicTrackers = [
        'udp://tracker.opentrackr.org:1337/announce',
        'udp://open.stealth.si:80/announce',
        'udp://tracker.openbittorrent.com:6969/announce',
        'udp://exodus.desync.com:6969/announce',
        'udp://tracker.torrent.eu.org:451/announce',
    ];

    /**
     * 构建目标视频查询构造器：
     * 1. 没有有效下载信息（has_downloads 为 false，或没有有效 video_downloads 记录）的视频；
     * 2. 标记为 4K（is_4k=true 或 max_quality=2160p），但现有有效 video_downloads 中缺失 4K/2160p 版本的视频。
     */
    public function getTargetVideosQuery(
        ?string $channel = null,
        ?int $videoId = null,
        bool $onlyReleased = false,
        string $order = 'desc'
    ): Builder {
        $query = Video::query()
            ->with(['actors:id,name,slug', 'channel:id,name,slug', 'downloads']);

        if ($videoId) {
            return $query->where('id', $videoId);
        }

        if ($onlyReleased) {
            $query->where('release_at', '<=', now());
        }

        if ($channel) {
            $query->whereHas('channel', function ($q) use ($channel) {
                $q->where('slug', $channel)
                    ->orWhere('name', 'ILIKE', "%{$channel}%");
            });
        }

        $query->where(function (Builder $mainQuery) {
            // 条件 1: 完全没有有效下载记录
            $mainQuery->where(function (Builder $noDl) {
                $noDl->where('has_downloads', false)
                    ->orWhereDoesntHave('downloads', fn($dq) => $dq->where('status', 1));
            })
            // 条件 2: 标记为 4K，但下载项中缺失 4K/2160p 资源
            ->orWhere(function (Builder $missing4k) {
                $missing4k->where(function (Builder $kQ) {
                    $kQ->where('is_4k', true)
                        ->orWhere('max_quality', '2160p');
                })
                ->whereDoesntHave('downloads', function (Builder $dq) {
                    $dq->where('status', 1)
                        ->where(function (Builder $rq) {
                            $rq->whereIn('resolution', ['4K', '2160P'])
                                ->orWhere('title', 'ILIKE', '%2160p%')
                                ->orWhere('title', 'ILIKE', '%4k%');
                        });
                });
            });
        });

        return strtolower($order) === 'asc' ? $query->orderBy('id') : $query->orderByDesc('id');
    }

    /**
     * 判断指定视频是否处于 xxxclub 失败冷却期内（近期未搜到资源，避免重复请求陷入死循环）
     */
    public function isCoolingDown(int $videoId): bool
    {
        try {
            if (class_exists(Redis::class)) {
                $exists = Redis::exists("crawler:xxxclub:cooldown:{$videoId}");
                if ($exists) {
                    return true;
                }
            }
        } catch (Throwable) {}

        return Cache::has("crawler:xxxclub:cooldown:{$videoId}");
    }

    /**
     * 将搜索未匹配/无资源的视频加入冷却期（默认 7 天）
     */
    public function markCoolingDown(int $videoId, int $days = 7): void
    {
        if ($days <= 0) {
            return;
        }

        $ttlSeconds = $days * 86400;
        try {
            if (class_exists(Redis::class)) {
                Redis::setex("crawler:xxxclub:cooldown:{$videoId}", $ttlSeconds, '1');
            }
        } catch (Throwable) {}

        Cache::put("crawler:xxxclub:cooldown:{$videoId}", '1', $ttlSeconds);
    }

    /**
     * 清除指定视频的冷却期标记（例如匹配成功或手动重新测试时）
     */
    public function clearCoolingDown(int $videoId): void
    {
        try {
            if (class_exists(Redis::class)) {
                Redis::del("crawler:xxxclub:cooldown:{$videoId}");
            }
        } catch (Throwable) {}

        Cache::forget("crawler:xxxclub:cooldown:{$videoId}");
    }

    /**
     * 根据视频元数据智能构建用于 xxxclub 的精确搜索关键词
     * xxxclub 最多取前 10 个词，以点号（.）分隔
     */
    public function buildSearchKeyword(Video $video): string
    {
        // 1. 如果已有 video_code，且包含点号或者标准结构（如 BrazzersExxtra.26.10.08...）
        if (!empty($video->video_code)) {
            $code = trim($video->video_code);
            // 清理多余非打印或特殊符号，将空格与下划线统一为点号
            $code = preg_replace('/[_\s]+/', '.', $code);
            $code = preg_replace('/[^a-zA-Z0-9.]+/', '', $code);
            $code = trim(preg_replace('/\.+/', '.', $code), '.');

            // 如果 code 的点号段落 >= 3，说明是完整编码，精确度极高
            if (substr_count($code, '.') >= 2 && strlen($code) >= 10) {
                return $this->limitKeywordWords($code, 10);
            }
        }

        // 2. 备选方案：通过 [片商] + [发布日期(YY.MM.DD)] + [主要女演员] + [片名] 组合构建
        $parts = [];

        // 片商标识
        if ($video->channel) {
            $parts[] = Str::studly($video->channel->slug ?: $video->channel->name);
        }

        // 发布日期（如 26.10.08）
        if ($video->release_at) {
            $parts[] = Carbon::parse($video->release_at)->format('y.m.d');
        }

        // 演员（取前 2 位）
        if ($video->relationLoaded('actors') && $video->actors->isNotEmpty()) {
            foreach ($video->actors->take(2) as $actor) {
                $cleanActor = preg_replace('/[^a-zA-Z0-9]+/', '.', trim($actor->name));
                if (!empty($cleanActor)) {
                    $parts[] = $cleanActor;
                }
            }
        }

        // 原片片名（去除标点并点号连接）
        $rawName = $video->name ?: $video->slug;
        if (!empty($rawName)) {
            $cleanName = preg_replace('/[^a-zA-Z0-9]+/', '.', trim($rawName));
            $parts[] = trim($cleanName, '.');
        }

        $combined = implode('.', array_filter($parts));
        $combined = trim(preg_replace('/\.+/', '.', $combined), '.');

        return $this->limitKeywordWords($combined, 10);
    }

    /**
     * 限制关键词最大词数（xxxclub 最多支持 10 个有效词）
     */
    protected function limitKeywordWords(string $keyword, int $maxWords = 10): string
    {
        $words = array_values(array_filter(explode('.', $keyword), fn($w) => strlen(trim($w)) > 0));
        if (count($words) <= $maxWords) {
            return implode('.', $words);
        }

        return implode('.', array_slice($words, 0, $maxWords));
    }

    /**
     * 已就绪的 FlareSolverr 会话列表
     */
    protected array $readyFlareSolverrSessions = [];

    /**
     * 各会话累计请求计数（防内存泄漏）
     */
    protected array $sessionRequestCounts = [];

    /**
     * 发送网络请求获取 xxxclub 搜索结果页面 HTML
     * 支持 FlareSolverr 自动绕过 Cloudflare 5秒盾与 Cookie 会话秒级复用
     */
    public function fetchSearchHtml(
        string $keyword,
        ?string $proxy = null,
        array $extraHeaders = [],
        ?string $customFlareSolverrUrl = null,
        bool $useFlareSolverr = true
    ): array {
        $searchUrl = "{$this->baseUrl}/" . rawurlencode($keyword);
        $hostKey = 'xxxclub.to';

        $flaresolverrUrl = $customFlareSolverrUrl
            ?: config('services.flaresolverr.url', env('FLARESOLVERR_URL', 'http://flaresolverr:8191/v1'));

        $resolvedProxy = $proxy ?: config('services.crawler.proxy') ?: env('CRAWLER_PROXY') ?: env('HTTP_PROXY');

        // =========================================================================
        // 模式 1: 极速快速通道 —— 若已有通行 Cookie，优先走毫秒级直连 HTTP
        // =========================================================================
        $cachedCookie = $this->getCachedCookie($hostKey);

        if (!empty($cachedCookie)) {
            $fastResult = $this->fetchDirectHttp($searchUrl, $cachedCookie, $resolvedProxy, $extraHeaders);
            if ($fastResult['success']) {
                return $fastResult;
            }

            // 若返回被 CF 拦截 (403 或 5秒盾)，说明通行 Cookie 已过期失效，主动清除
            if ($fastResult['is_cf']) {
                Log::info("xxxclub.to 缓存通行 Cookie 已失效，将切换至 FlareSolverr 重新求解...");
                $this->clearCachedCookies($hostKey);
            }
        }

        // =========================================================================
        // 模式 2: FlareSolverr 过盾通道 —— 自动求解并获取最新 cf_clearance 与 HTML
        // =========================================================================
        if ($useFlareSolverr && !empty($flaresolverrUrl)) {
            Log::info("🛡️ 正在通过 FlareSolverr ({$flaresolverrUrl}) 请求 xxxclub 搜索: {$keyword}");
            $fsResult = $this->fetchViaFlareSolverr($flaresolverrUrl, $searchUrl, $resolvedProxy);

            if ($fsResult['success']) {
                return $fsResult;
            }

            // 若 FlareSolverr 失败且未曾尝试直连，记录错误
            Log::warning("FlareSolverr 请求失败，尝试降级常规直连: " . $fsResult['message']);
        }

        // =========================================================================
        // 模式 3: 常规直连兜底（若无 FlareSolverr 或未配置）
        // =========================================================================
        return $this->fetchDirectHttp($searchUrl, null, $resolvedProxy, $extraHeaders);
    }

    /**
     * 直接通过 HTTP 请求网页
     */
    protected function fetchDirectHttp(string $url, ?string $cookie = null, ?string $proxy = null, array $extraHeaders = []): array
    {
        $customUserAgent = $this->getCachedUserAgent();

        $defaultHeaders = [
            'User-Agent'                => $customUserAgent,
            'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'Accept-Language'           => 'en-US,en;q=0.9,zh-CN;q=0.8,zh;q=0.7',
            'Cache-Control'             => 'max-age=0',
            'Referer'                   => 'https://xxxclub.to/',
            'Sec-Ch-Ua'                 => '"Google Chrome";v="131", "Chromium";v="131", "Not_A Brand";v="24"',
            'Sec-Ch-Ua-Mobile'          => '?0',
            'Sec-Ch-Ua-Platform'        => '"macOS"',
            'Sec-Fetch-Dest'            => 'document',
            'Sec-Fetch-Mode'            => 'navigate',
            'Sec-Fetch-Site'            => 'same-origin',
            'Sec-Fetch-User'            => '?1',
            'Upgrade-Insecure-Requests' => '1',
        ];

        if (!empty($cookie)) {
            $defaultHeaders['Cookie'] = $cookie;
        }

        $headers = array_merge($defaultHeaders, $extraHeaders);

        try {
            $client = Http::withHeaders($headers)->timeout(25);

            if (!empty($proxy)) {
                $client = $client->withOptions(['proxy' => $proxy]);
            }

            $response = $client->get($url);
            $statusCode = $response->status();
            $body = $response->body();

            // 检查 Cloudflare 质询拦截
            if ($statusCode === 403 || str_contains($body, 'Just a moment...') || str_contains($body, 'cf-browser-verification')) {
                return [
                    'success'          => false,
                    'status_code'      => $statusCode,
                    'url'              => $url,
                    'is_cf'            => true,
                    'via_flaresolverr' => false,
                    'message'          => '触发了 Cloudflare 防爬验证质询 (403/Challenge)',
                    'html'             => $body,
                ];
            }

            if (!$response->successful()) {
                return [
                    'success'          => false,
                    'status_code'      => $statusCode,
                    'url'              => $url,
                    'is_cf'            => false,
                    'via_flaresolverr' => false,
                    'message'          => "HTTP 请求失败，状态码: {$statusCode}",
                    'html'             => $body,
                ];
            }

            return [
                'success'          => true,
                'status_code'      => $statusCode,
                'url'              => $url,
                'is_cf'            => false,
                'via_flaresolverr' => false,
                'message'          => 'OK',
                'html'             => $body,
            ];
        } catch (Throwable $e) {
            Log::error("xxxclub 直连请求异常 [{$url}]: " . $e->getMessage());
            return [
                'success'          => false,
                'status_code'      => 0,
                'url'              => $url,
                'is_cf'            => false,
                'via_flaresolverr' => false,
                'message'          => '网络请求异常: ' . $e->getMessage(),
                'html'             => '',
            ];
        }
    }

    /**
     * 通过 FlareSolverr 穿透 Cloudflare 获取网页并缓存 Cookies
     */
    protected function fetchViaFlareSolverr(string $flaresolverrUrl, string $url, ?string $proxy = null): array
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'xxxclub.to';
        $hostKey = strtolower(preg_replace('/^www\./i', '', $host));
        $sessionId = 'sess_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $hostKey);

        $cleanUrl = rtrim($flaresolverrUrl, '/');
        $endpoint = str_ends_with($cleanUrl, '/v1') ? $cleanUrl : "{$cleanUrl}/v1";

        try {
            // 确保会话存在
            $this->ensureFlareSolverrSession($endpoint, $sessionId, $proxy);

            // 防内存泄漏：超过 15 次请求主动重置会话
            $this->sessionRequestCounts[$sessionId] = ($this->sessionRequestCounts[$sessionId] ?? 0) + 1;
            if ($this->sessionRequestCounts[$sessionId] > 15) {
                Log::info("FlareSolverr 会话 [{$sessionId}] 连续请求已达 15 次，重置会话释放 Chromium 内存...");
                $this->destroyFlareSolverrSession($endpoint, $sessionId);
                $this->ensureFlareSolverrSession($endpoint, $sessionId, $proxy);
                $this->sessionRequestCounts[$sessionId] = 1;
            }

            $postData = [
                'cmd'        => 'request.get',
                'url'        => $url,
                'session'    => $sessionId,
                'maxTimeout' => 60000,
            ];

            if (!empty($proxy)) {
                $postData['proxy'] = ['url' => $proxy];
            }

            $startTime = microtime(true);
            $response = Http::timeout(75)->post($endpoint, $postData);
            $data = $response->json() ?? [];

            // 容错处理：若 session 失效、浏览器标签崩溃(tab crashed)等，清理后尝试重建并重试
            $status = $data['status'] ?? '';
            $errMsg = strtolower($data['message'] ?? '');
            $needsRecreate = ($status === 'error' || $response->serverError()) && (
                str_contains($errMsg, 'session') ||
                str_contains($errMsg, 'tab crashed') ||
                str_contains($errMsg, 'chromedriver')
            );

            if ($needsRecreate) {
                Log::warning("FlareSolverr 会话 [{$sessionId}] 异常 (" . ($data['message'] ?? $response->body()) . ")，正在重建重试...");
                $this->destroyFlareSolverrSession($endpoint, $sessionId);
                $this->ensureFlareSolverrSession($endpoint, $sessionId, $proxy);

                $response = Http::timeout(75)->post($endpoint, $postData);
                $data = $response->json() ?? [];
            }

            if (($data['status'] ?? '') === 'ok' && !empty($data['solution']['response'])) {
                $elapsed = round(microtime(true) - $startTime, 2);
                Log::info("🎉 FlareSolverr 成功穿透 Cloudflare [{$hostKey} | 耗时 {$elapsed}s]！");

                // 缓存配套的 User-Agent
                if (!empty($data['solution']['userAgent'])) {
                    $this->cacheUserAgent($data['solution']['userAgent']);
                }

                // 缓存提取出的 Cookies（含 cf_clearance）
                if (!empty($data['solution']['cookies'])) {
                    $this->cacheCookies($hostKey, $data['solution']['cookies']);
                }

                return [
                    'success'          => true,
                    'status_code'      => $data['solution']['status'] ?? 200,
                    'url'              => $url,
                    'is_cf'            => false,
                    'via_flaresolverr' => true,
                    'message'          => 'OK (via FlareSolverr)',
                    'html'             => $data['solution']['response'],
                ];
            }

            $failureMessage = $data['message'] ?? $response->body();
            Log::error("FlareSolverr 求解失败 [{$url}]: " . $failureMessage);

            return [
                'success'          => false,
                'status_code'      => $data['solution']['status'] ?? 500,
                'url'              => $url,
                'is_cf'            => true,
                'via_flaresolverr' => true,
                'message'          => 'FlareSolverr 求解失败: ' . $failureMessage,
                'html'             => '',
            ];
        } catch (Throwable $e) {
            Log::error("调用 FlareSolverr 发生异常: " . $e->getMessage());
            return [
                'success'          => false,
                'status_code'      => 0,
                'url'              => $url,
                'is_cf'            => true,
                'via_flaresolverr' => true,
                'message'          => '调用 FlareSolverr 发生异常: ' . $e->getMessage(),
                'html'             => '',
            ];
        }
    }

    /**
     * 确保 FlareSolverr 会话处于活动状态
     */
    protected function ensureFlareSolverrSession(string $endpoint, string $sessionId, ?string $proxy = null): void
    {
        if (isset($this->readyFlareSolverrSessions[$sessionId])) {
            return;
        }

        try {
            $postData = [
                'cmd'     => 'sessions.create',
                'session' => $sessionId,
            ];

            if (!empty($proxy)) {
                $postData['proxy'] = ['url' => $proxy];
            }

            $response = Http::timeout(30)->post($endpoint, $postData);
            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'ok' || str_contains($data['message'] ?? '', 'already exists')) {
                    $this->readyFlareSolverrSessions[$sessionId] = true;
                }
            }
        } catch (Throwable $e) {
            Log::warning("FlareSolverr 会话 [{$sessionId}] 初始化异常: " . $e->getMessage());
        }
    }

    /**
     * 销毁指定 FlareSolverr 会话
     */
    protected function destroyFlareSolverrSession(string $endpoint, string $sessionId): void
    {
        unset($this->readyFlareSolverrSessions[$sessionId]);
        $this->sessionRequestCounts[$sessionId] = 0;

        try {
            Http::timeout(10)->post($endpoint, [
                'cmd'     => 'sessions.destroy',
                'session' => $sessionId,
            ]);
        } catch (Throwable) {}
    }

    /**
     * 获取缓存的域名专属 Cookie 字符串
     */
    protected function getCachedCookie(string $hostKey): ?string
    {
        try {
            if (class_exists(Redis::class)) {
                $cached = Redis::get("crawler:cookie_str:{$hostKey}");
                if (!empty($cached)) {
                    return $cached;
                }
                $clearance = Redis::get("crawler:cf_clearance:{$hostKey}");
                if (!empty($clearance)) {
                    return "cf_clearance={$clearance}";
                }
            }
        } catch (Throwable) {}

        return Cache::get("crawler:cookie_str:{$hostKey}");
    }

    /**
     * 缓存提取出的 Cookies（含 cf_clearance）
     */
    protected function cacheCookies(string $hostKey, array $cookies): void
    {
        $cookiePairs = [];
        $cfClearance = null;
        foreach ($cookies as $c) {
            if (!empty($c['name']) && isset($c['value'])) {
                $cookiePairs[] = "{$c['name']}={$c['value']}";
                if ($c['name'] === 'cf_clearance') {
                    $cfClearance = $c['value'];
                }
            }
        }

        if (!empty($cookiePairs)) {
            $cookieStr = implode('; ', $cookiePairs);
            try {
                if (class_exists(Redis::class)) {
                    Redis::setex("crawler:cookie_str:{$hostKey}", 7200, $cookieStr);
                    if ($cfClearance) {
                        Redis::setex("crawler:cf_clearance:{$hostKey}", 7200, $cfClearance);
                    }
                }
            } catch (Throwable) {}
            Cache::put("crawler:cookie_str:{$hostKey}", $cookieStr, 7200);
        }
    }

    /**
     * 清除域名的缓存 Cookie
     */
    protected function clearCachedCookies(string $hostKey): void
    {
        try {
            if (class_exists(Redis::class)) {
                Redis::del("crawler:cookie_str:{$hostKey}");
                Redis::del("crawler:cf_clearance:{$hostKey}");
            }
        } catch (Throwable) {}
        Cache::forget("crawler:cookie_str:{$hostKey}");
    }

    /**
     * 获取缓存的 User-Agent
     */
    protected function getCachedUserAgent(): string
    {
        try {
            if (class_exists(Redis::class)) {
                $cached = Redis::get('crawler:user_agent');
                if (!empty($cached)) {
                    return $cached;
                }
            }
        } catch (Throwable) {}

        $cached = Cache::get('crawler:user_agent');
        if (!empty($cached)) {
            return $cached;
        }

        return config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');
    }

    /**
     * 缓存 User-Agent
     */
    protected function cacheUserAgent(string $ua): void
    {
        try {
            if (class_exists(Redis::class)) {
                Redis::setex('crawler:user_agent', 86400, $ua);
            }
        } catch (Throwable) {}
        Cache::put('crawler:user_agent', $ua, 86400);
    }

    /**
     * 解析 HTML 中 class 为 tsearch 的种子列表并提取结构化数据
     * 针对 PHP 8.4+ 自动启用全新原生的 Lexbor HTML5 解析器 (\Dom\HTMLDocument)
     */
    public function parseSearchHtml(string $html): array
    {
        if (empty(trim($html))) {
            return [];
        }

        // 🌟 优先使用 PHP 8.4 全新原生 HTML5 解析引擎（Lexbor，速度快 2.4 倍且天然支持 CSS 选择器）
        if (class_exists(\Dom\HTMLDocument::class)) {
            return $this->parseSearchHtmlWithHtml5($html);
        }

        // 兼容降级模式：老版本 PHP 使用传统 DOMDocument + XPath
        return $this->parseSearchHtmlWithLegacyDom($html);
    }

    /**
     * 【PHP 8.4+ 现代模式】使用原生 \Dom\HTMLDocument 解析
     */
    protected function parseSearchHtmlWithHtml5(string $html): array
    {
        // 原生 Lexbor HTML5 规范解析，禁止无谓的 libxml warning
        $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $liNodes = $doc->querySelectorAll('ul.tsearch > li');

        if (!$liNodes || $liNodes->length === 0) {
            return [];
        }

        $results = [];

        foreach ($liNodes as $index => $node) {
            // 跳过表头行
            if ($index === 0) {
                $headerSpan = $node->querySelector('span');
                if ($headerSpan && strtolower(trim($headerSpan->textContent)) === 'category') {
                    continue;
                }
            }

            // 1. 清晰度标签（UHD, FullHD, HD, SD）
            $catNode = $node->querySelector('span.catlabe lable');
            $catLabel = $catNode ? trim($catNode->textContent) : '';

            // 2. 真实下载项与哈希提取
            // 跳过蜜罐链接 (style 包含 left:-99999px)
            $realLink = null;
            $aNodes = $node->querySelectorAll('span.toral a');
            foreach ($aNodes as $a) {
                $style = $a->getAttribute('style') ?? '';
                $href = $a->getAttribute('href') ?? '';
                if (str_contains($style, 'left:-99999px') || !str_contains($href, '/torrents/details/')) {
                    continue;
                }
                $realLink = $a;
                break;
            }

            if (!$realLink) {
                continue;
            }

            // 从 id 属性中提取 40 位 hex hash (例如 #i5c1517257edc8b0728ccd054199bfdbf91d4dbe0)
            $idAttr = $realLink->getAttribute('id') ?? '';
            if (!preg_match('/[a-f0-9]{40}/i', $idAttr, $hashMatch)) {
                continue;
            }
            $hash = strtolower($hashMatch[0]);

            // 3. 标题与净化
            $rawTitle = trim($realLink->textContent);
            $cleanTitle = trim(preg_replace('/\s*\[XC\]\s*$/i', '', $rawTitle));

            // 4. 清晰度判定与权重排序
            $resInfo = $this->detectResolution($catLabel, $cleanTitle);

            // 5. 文件大小解析为字节
            $sizeNode = $node->querySelector('span.siz');
            $sizeStr = $sizeNode ? trim($sizeNode->textContent) : '';
            $fileSizeBytes = $this->parseSizeToBytes($sizeStr);

            // 6. 发布时间
            $dateNode = $node->querySelector('span.adde');
            $dateStr = $dateNode ? trim($dateNode->textContent) : '';

            // 7. 做种数与下载数
            $seeNode = $node->querySelector('span.see');
            $seeders = $seeNode ? (int) trim($seeNode->textContent) : 0;

            $leeNode = $node->querySelector('span.lee');
            $leechers = $leeNode ? (int) trim($leeNode->textContent) : 0;

            // 8. 上传者
            $uploaderNode = $node->querySelector('span.uploadertable');
            $uploader = $uploaderNode ? trim($uploaderNode->textContent) : '';

            // 9. 构造标准 Magnet 磁力链接
            $magnetLink = $this->buildMagnetLink($hash, $cleanTitle);

            $results[] = [
                'hash'        => $hash,
                'title'       => $cleanTitle,
                'cat'         => $catLabel,
                'resolution'  => $resInfo['resolution'],
                'sort_order'  => $resInfo['sort_order'],
                'size_str'    => $sizeStr,
                'file_size'   => $fileSizeBytes,
                'date_str'    => $dateStr,
                'seeders'     => $seeders,
                'leechers'    => $leechers,
                'uploader'    => $uploader,
                'magnet_link' => $magnetLink,
            ];
        }

        return $results;
    }

    /**
     * 【传统模式】使用 DOMDocument + XPath 解析（老版本 PHP 兼容降级）
     */
    protected function parseSearchHtmlWithLegacyDom(string $html): array
    {
        libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new DOMXPath($doc);
        $liNodes = $xpath->query('//ul[contains(@class, "tsearch")]/li');

        if (!$liNodes || $liNodes->length === 0) {
            return [];
        }

        $results = [];

        foreach ($liNodes as $index => $node) {
            // 跳过表头行
            if ($index === 0) {
                $headerSpan = $xpath->query('.//span', $node)->item(0);
                if ($headerSpan && strtolower(trim($headerSpan->textContent)) === 'category') {
                    continue;
                }
            }

            // 1. 清晰度标签（UHD, FullHD, HD, SD）
            $catNode = $xpath->query('.//span[contains(@class, "catlabe")]//lable', $node)->item(0);
            $catLabel = $catNode ? trim($catNode->textContent) : '';

            // 2. 真实下载项与哈希提取
            /** @var DOMElement|null $realLink */
            $realLink = null;
            $aNodes = $xpath->query('.//span[contains(@class, "toral")]/a', $node);
            if ($aNodes) {
                foreach ($aNodes as $a) {
                    if (!($a instanceof DOMElement)) {
                        continue;
                    }
                    $style = (string) $a->getAttribute('style');
                    $href = (string) $a->getAttribute('href');
                    if (str_contains($style, 'left:-99999px') || !str_contains($href, '/torrents/details/')) {
                        continue;
                    }
                    $realLink = $a;
                    break;
                }
            }

            if (!($realLink instanceof DOMElement)) {
                continue;
            }

            // 从 id 属性中提取 40 位 hex hash (例如 #i5c1517257edc8b0728ccd054199bfdbf91d4dbe0)
            $idAttr = (string) $realLink->getAttribute('id');
            if (!preg_match('/[a-f0-9]{40}/i', $idAttr, $hashMatch)) {
                continue;
            }
            $hash = strtolower($hashMatch[0]);

            // 3. 标题与净化
            $rawTitle = trim($realLink->textContent);
            $cleanTitle = trim(preg_replace('/\s*\[XC\]\s*$/i', '', $rawTitle));

            // 4. 清晰度判定与权重排序
            $resInfo = $this->detectResolution($catLabel, $cleanTitle);

            // 5. 文件大小解析为字节
            $sizeNode = $xpath->query('.//span[contains(@class, "siz")]', $node)->item(0);
            $sizeStr = $sizeNode ? trim($sizeNode->textContent) : '';
            $fileSizeBytes = $this->parseSizeToBytes($sizeStr);

            // 6. 发布时间
            $dateNode = $xpath->query('.//span[contains(@class, "adde")]', $node)->item(0);
            $dateStr = $dateNode ? trim($dateNode->textContent) : '';

            // 7. 做种数与下载数
            $seeNode = $xpath->query('.//span[contains(@class, "see")]', $node)->item(0);
            $seeders = $seeNode ? (int) trim($seeNode->textContent) : 0;

            $leeNode = $xpath->query('.//span[contains(@class, "lee")]', $node)->item(0);
            $leechers = $leeNode ? (int) trim($leeNode->textContent) : 0;

            // 8. 上传者
            $uploaderNode = $xpath->query('.//span[contains(@class, "uploadertable")]', $node)->item(0);
            $uploader = $uploaderNode ? trim($uploaderNode->textContent) : '';

            // 9. 构造标准 Magnet 磁力链接
            $magnetLink = $this->buildMagnetLink($hash, $cleanTitle);

            $results[] = [
                'hash'        => $hash,
                'title'       => $cleanTitle,
                'cat'         => $catLabel,
                'resolution'  => $resInfo['resolution'],
                'sort_order'  => $resInfo['sort_order'],
                'size_str'    => $sizeStr,
                'file_size'   => $fileSizeBytes,
                'date_str'    => $dateStr,
                'seeders'     => $seeders,
                'leechers'    => $leechers,
                'uploader'    => $uploader,
                'magnet_link' => $magnetLink,
            ];
        }

        return $results;
    }

    /**
     * 判断解析出的种子项是否精确匹配目标视频
     */
    public function isMatch(Video $video, array $item): bool
    {
        $torrentTitle = $item['title'] ?? '';
        if (empty($torrentTitle)) {
            return false;
        }

        $normalize = fn($s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $s));

        $titleNorm = $normalize($torrentTitle);

        // 1. 如果已有 video_code，且包含在 torrent 标题中（如 BrazzersExxtra.26.10.08...）
        if (!empty($video->video_code)) {
            $codeNorm = $normalize($video->video_code);
            if (!empty($codeNorm) && strlen($codeNorm) >= 8) {
                if (str_contains($titleNorm, $codeNorm) || str_contains($codeNorm, $titleNorm)) {
                    return true;
                }
            }
        }

        // 2. 复合特征打分验证
        $score = 0;

        // 特征 A: 发布日期吻合（YY.MM.DD 或 YYYY.MM.DD）
        if ($video->release_at) {
            $dt = Carbon::parse($video->release_at);
            $shortDate = $dt->format('ymd');     // 如 261008
            $fullDate = $dt->format('Ymd');      // 如 20261008
            if (str_contains($titleNorm, $shortDate) || str_contains($titleNorm, $fullDate)) {
                $score += 50;
            }
        }

        // 特征 B: 片名核心词匹配
        $rawVideoName = $video->name ?: $video->slug;
        if (!empty($rawVideoName)) {
            $nameNorm = $normalize($rawVideoName);
            if (!empty($nameNorm) && strlen($nameNorm) >= 4) {
                if (str_contains($titleNorm, $nameNorm)) {
                    $score += 60;
                } else {
                    // 分词匹配
                    $nameWords = array_filter(explode(' ', strtolower(preg_replace('/[^a-zA-Z0-9]+/', ' ', $rawVideoName))));
                    $matchedWords = 0;
                    foreach ($nameWords as $w) {
                        if (strlen($w) >= 3 && str_contains($titleNorm, $w)) {
                            $matchedWords++;
                        }
                    }
                    if (count($nameWords) > 0 && ($matchedWords / count($nameWords)) >= 0.7) {
                        $score += 50;
                    }
                }
            }
        }

        // 特征 C: 演员名字吻合
        if ($video->relationLoaded('actors') && $video->actors->isNotEmpty()) {
            foreach ($video->actors as $actor) {
                $actorNorm = $normalize($actor->name);
                if (!empty($actorNorm) && strlen($actorNorm) >= 4 && str_contains($titleNorm, $actorNorm)) {
                    $score += 40;
                    break;
                }
            }
        }

        // 综合门槛：得分达到 80 分以上即确认为同部影片
        return $score >= 80;
    }

    /**
     * 将匹配出的下载项持久化到 video_downloads 表，并自动同步更新 videos.has_downloads
     */
    public function importDownloads(Video $video, array $items, bool $dryRun = false, bool $force = false): array
    {
        $stats = [
            'total'         => count($items),
            'matched'       => 0,
            'inserted'      => 0,
            'updated'       => 0,
            'skipped'       => 0,
            'matched_items' => [],
        ];

        $matchedItems = [];
        foreach ($items as $item) {
            if ($this->isMatch($video, $item)) {
                $matchedItems[] = $item;
                $stats['matched_items'][] = [
                    'title'       => $item['title'],
                    'resolution'  => $item['resolution'],
                    'size_str'    => $item['size_str'],
                    'hash'        => $item['hash'],
                    'magnet_link' => $item['magnet_link'] ?? '',
                ];
            }
        }

        $stats['matched'] = count($matchedItems);

        if (empty($matchedItems) || $dryRun) {
            return $stats;
        }

        $hasInsertedOrUpdated = false;

        foreach ($matchedItems as $item) {
            $hash = $item['hash'];
            $existing = VideoDownload::where('hash', $hash)->first();

            $payload = [
                'video_id'         => $video->id,
                'user_id'          => null,
                'title'            => $item['title'],
                'type'             => 'magnet',
                'cost_type'        => 'free',
                'resolution'       => $item['resolution'],
                'price'            => null,
                'link'             => $item['magnet_link'],
                'hash'             => $hash,
                'file_size'        => $item['file_size'],
                'extraction_code'  => null,
                'archive_password' => null,
                'description'      => null,
                'status'           => 1,
                'sort_order'       => $item['sort_order'],
            ];

            if ($existing) {
                if ($force) {
                    $existing->update($payload);
                    $stats['updated']++;
                    $hasInsertedOrUpdated = true;
                } else {
                    $stats['skipped']++;
                }
            } else {
                VideoDownload::create($payload);
                $stats['inserted']++;
                $hasInsertedOrUpdated = true;
            }
        }

        // 确保关联视频标记为拥有下载资源
        if (!$video->has_downloads) {
            $video->update(['has_downloads' => true]);
            $video->has_downloads = true;
        }

        return $stats;
    }

    /**
     * 清晰度与排序优先级自动映射
     */
    public function detectResolution(string $category, string $title): array
    {
        $cat = strtoupper(trim($category));
        $t = strtolower($title);

        if ($cat === 'UHD' || str_contains($t, '2160p') || str_contains($t, '4k') || str_contains($t, 'uhd')) {
            return ['resolution' => '4K', 'sort_order' => 10];
        }
        if ($cat === 'FULLHD' || str_contains($t, '1080p')) {
            return ['resolution' => '1080P', 'sort_order' => 5];
        }
        if ($cat === 'HD' || str_contains($t, '720p')) {
            return ['resolution' => '720P', 'sort_order' => 3];
        }
        if ($cat === 'SD' || str_contains($t, '480p') || str_contains($t, '540p') || str_contains($t, 'sd')) {
            return ['resolution' => 'SD', 'sort_order' => 1];
        }

        return ['resolution' => null, 'sort_order' => 0];
    }

    /**
     * 将可读文件大小字符串（如 3.80 GB）换算为字节数
     */
    public function parseSizeToBytes(string $sizeStr): ?int
    {
        $sizeStr = trim($sizeStr);
        if (preg_match('/^([\d.]+)\s*([KMGTP]?B)$/i', $sizeStr, $matches)) {
            $num = (float) $matches[1];
            $unit = strtoupper($matches[2]);
            return match ($unit) {
                'TB', 'TIB' => (int) round($num * 1024 * 1024 * 1024 * 1024),
                'GB', 'GIB' => (int) round($num * 1024 * 1024 * 1024),
                'MB', 'MIB' => (int) round($num * 1024 * 1024),
                'KB', 'KIB' => (int) round($num * 1024),
                'B'         => (int) round($num),
                default     => null,
            };
        }
        return null;
    }

    /**
     * 构造带有标准公共 Trackers 的磁力链接
     */
    public function buildMagnetLink(string $hash, string $title): string
    {
        $link = "magnet:?xt=urn:btih:{$hash}&dn=" . rawurlencode($title);
        foreach ($this->publicTrackers as $tr) {
            $link .= '&tr=' . rawurlencode($tr);
        }
        return $link;
    }
}

