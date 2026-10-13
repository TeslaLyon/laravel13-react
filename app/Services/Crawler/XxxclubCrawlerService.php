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
        bool $onlyReleased = true,
        string $order = 'desc'
    ): Builder {
        $query = Video::query()
            ->with(['actors:id,name,slug', 'channel:id,name,slug', 'downloads']);

        if ($videoId) {
            return $query->where('id', $videoId);
        }

        if ($onlyReleased) {
            // 排除明确排期在未来的视频 (release_at > now())
            $query->where(function (Builder $rq) {
                $rq->whereNull('release_at')
                   ->orWhere('release_at', '<=', now());
            });
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
     * 判断视频是否属于未来尚未上映/发售的片源（双重校验，从 release_at 及 video_code 中识别未来排期）
     */
    public function isFutureVideo(Video $video): bool
    {
        // 1. 检查 release_at 字段
        if (!empty($video->release_at)) {
            try {
                if (Carbon::parse($video->release_at)->isFuture()) {
                    return true;
                }
            } catch (Throwable) {}
        }

        // 2. 从 video_code 或 name 中提取 YY.MM.DD 格式（例如 Milfy.26.10.15...）
        $raw = $video->video_code ?: ($video->name ?: '');
        if (preg_match('/(?:\.|\b)(\d{2})\.(\d{2})\.(\d{2})(?:\.|\b)/', $raw, $matches)) {
            try {
                $year = (int) $matches[1] + 2000;
                $month = (int) $matches[2];
                $day = (int) $matches[3];
                if (checkdate($month, $day, $year)) {
                    $dt = Carbon::create($year, $month, $day)->startOfDay();
                    if ($dt->isFuture()) {
                        return true;
                    }
                }
            } catch (Throwable) {}
        }

        return false;
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
     * 一键清空所有视频的冷却期标记（支持 Redis 与 Cache）
     */
    public function clearAllCoolingDown(): int
    {
        $count = 0;
        try {
            if (class_exists(Redis::class)) {
                $keys = Redis::keys('*crawler:xxxclub:cooldown:*');
                if (!empty($keys)) {
                    foreach ($keys as $k) {
                        Redis::del($k);
                        $count++;
                    }
                }
            }
        } catch (Throwable) {}

        return $count;
    }

    /**
     * 获取所有当前正处于冷静期中的视频列表（含剩余冷静时间 TTL）
     * @return array<int, array{id: int, ttl_seconds: int, ttl_formatted: string, video: ?Video}>
     */
    public function getCoolingDownVideos(): array
    {
        $items = [];
        try {
            if (class_exists(Redis::class)) {
                $keys = Redis::keys('*crawler:xxxclub:cooldown:*');
                if (!empty($keys)) {
                    foreach ($keys as $k) {
                        if (preg_match('/crawler:xxxclub:cooldown:(\d+)$/', $k, $m)) {
                            $vid = (int) $m[1];
                            $ttl = (int) Redis::ttl($k);
                            $items[$vid] = [
                                'id'            => $vid,
                                'ttl_seconds'   => $ttl,
                                'ttl_formatted' => $this->formatTtl($ttl),
                            ];
                        }
                    }
                }
            }
        } catch (Throwable) {}

        if (empty($items)) {
            return [];
        }

        // 关联查询对应的视频元数据
        $videoIds = array_keys($items);
        $videos = Video::with('channel')->whereIn('id', $videoIds)->get()->keyBy('id');

        foreach ($items as $vid => &$data) {
            $data['video'] = $videos->get($vid);
        }

        // 按剩余 TTL 从大到小排序
        uasort($items, fn($a, $b) => $b['ttl_seconds'] <=> $a['ttl_seconds']);

        return array_values($items);
    }

    /**
     * 将 TTL 秒数格式化为人类易读的“X天X小时X分”
     */
    public function formatTtl(int $seconds): string
    {
        if ($seconds <= 0) {
            return '已过期';
        }
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) $parts[] = "{$days}天";
        if ($hours > 0) $parts[] = "{$hours}小时";
        if ($minutes > 0 || empty($parts)) $parts[] = "{$minutes}分钟";

        return implode('', $parts);
    }

    /**
     * 根据视频元数据智能构建用于 xxxclub 的多策略候选搜索关键词列表（按置信度排序）
     * 策略 1: 原始/清洗后的完整 video_code（例如 Deeper.26.10.09.Violet.Voss.And.Alex.Jones.Absolution）
     * 策略 2: 容差时差候选词：厂牌 + 前一天日期 + 标题（解决欧美发行时区偏差1天的情况，例如 Deeper.26.10.08.Violet.Voss.And.Alex.Jones.Absolution）
     * 策略 3: 容差时差候选词：厂牌 + 后一天日期 + 标题（例如 Deeper.26.10.10.Violet.Voss.And.Alex.Jones.Absolution）
     * 策略 4: 厂牌 + 日期（例如 Deeper.26.10.09）
     * 策略 5: 厂牌 + 前一天日期（例如 Deeper.26.10.08）
     * 策略 6: 厂牌 + 主要演员（例如 Deeper.Violet.Voss）
     * 策略 7: 片商 + 发布日期 + 演员 + 片名 组合（当无规范 video_code 时的兜底）
     *
     * @return array<int, string>
     */
    public function buildSearchKeywords(Video $video): array
    {
        $keywords = [];

        // 1. 如果已有 video_code
        $rawCode = trim($video->video_code ?? '');
        if (!empty($rawCode)) {
            $cleanCode = preg_replace('/[_\s]+/', '.', $rawCode);
            $cleanCode = preg_replace('/[^a-zA-Z0-9.]+/', '', $cleanCode);
            $cleanCode = trim(preg_replace('/\.+/', '.', $cleanCode), '.');

            if (!empty($cleanCode)) {
                $keywords[] = $this->limitKeywordWords($cleanCode, 10);
            }

            // 识别标准结构：[厂牌].[YY].[MM].[DD].[后续内容]
            if (preg_match('/^([a-zA-Z0-9]+)[._-](\d{2})[._-](\d{2})[._-](\d{2})[._-](.+)$/i', $rawCode, $m)) {
                $channel = trim($m[1]);
                $yy = (int) $m[2];
                $mm = (int) $m[3];
                $dd = (int) $m[4];
                $afterDate = trim(preg_replace('/\.+/', '.', preg_replace('/[^a-zA-Z0-9.]+/', '', preg_replace('/[_\s]+/', '.', $m[5]))), '.');

                $fullYear = $yy > 70 ? 1900 + $yy : 2000 + $yy;
                if (checkdate($mm, $dd, $fullYear)) {
                    $dt = Carbon::create($fullYear, $mm, $dd);
                    $prevDate = $dt->copy()->subDay()->format('y.m.d');
                    $nextDate = $dt->copy()->addDay()->format('y.m.d');

                    // 容差 +-1 天完整词 (欧美发片时区偏差)
                    if (!empty($afterDate)) {
                        $keywords[] = $this->limitKeywordWords("{$channel}.{$prevDate}.{$afterDate}", 10);
                        $keywords[] = $this->limitKeywordWords("{$channel}.{$nextDate}.{$afterDate}", 10);
                    }

                    // 厂牌 + 日期 (如 Deeper.26.10.09 / Deeper.26.10.08)
                    $origDate = sprintf('%02d.%02d.%02d', $yy, $mm, $dd);
                    $keywords[] = "{$channel}.{$origDate}";
                    $keywords[] = "{$channel}.{$prevDate}";

                    // 厂牌 + 女演员 (如 Deeper.Violet.Voss)
                    $afterTokens = array_values(array_filter(explode('.', $afterDate), fn($t) => strlen($t) >= 2));
                    if (count($afterTokens) >= 2) {
                        $keywords[] = "{$channel}.{$afterTokens[0]}.{$afterTokens[1]}";
                    } elseif (count($afterTokens) === 1) {
                        $keywords[] = "{$channel}.{$afterTokens[0]}";
                    }
                }
            }
        }

        // 2. 备选方案：通过 [片商] + [发布日期(YY.MM.DD)] + [主要女演员] + [片名] 组合构建
        $parts = [];
        if ($video->relationLoaded('channel') && $video->channel) {
            $parts[] = Str::studly($video->channel->slug ?: $video->channel->name);
        }
        if ($video->release_at) {
            try {
                $parts[] = Carbon::parse($video->release_at)->format('y.m.d');
            } catch (Throwable) {}
        }
        if ($video->relationLoaded('actors') && $video->actors->isNotEmpty()) {
            foreach ($video->actors->take(2) as $actor) {
                $cleanActor = preg_replace('/[^a-zA-Z0-9]+/', '.', trim($actor->name));
                if (!empty($cleanActor)) {
                    $parts[] = $cleanActor;
                }
            }
        }
        $rawName = $video->name ?: $video->slug;
        if (!empty($rawName)) {
            $cleanName = preg_replace('/[^a-zA-Z0-9]+/', '.', trim($rawName));
            if (!empty($cleanName)) {
                $parts[] = trim($cleanName, '.');
            }
        }

        if (!empty($parts)) {
            $combined = implode('.', array_filter($parts));
            $combined = trim(preg_replace('/\.+/', '.', $combined), '.');
            if (strlen($combined) >= 3) {
                $keywords[] = $this->limitKeywordWords($combined, 10);
            }
        }

        return array_values(array_unique(array_filter($keywords)));
    }

    /**
     * 根据视频元数据智能构建用于 xxxclub 的精确搜索关键词（首选关键词）
     * xxxclub 最多取前 10 个词，以点号（.）分隔
     */
    public function buildSearchKeyword(Video $video): string
    {
        $keywords = $this->buildSearchKeywords($video);
        return $keywords[0] ?? '';
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
        // 0. 如果条目来自已完成严格日期/标题比对的外部搜索引擎（如 Ciligou）
        if (!empty($item['source']) && $item['source'] === 'ciligou') {
            return true;
        }

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

        // 特征 A: 发布日期吻合（YY.MM.DD 或 YYYY.MM.DD，支持时差偏差 +-1 天）
        $videoDate = null;
        if (!empty($video->video_code) && preg_match('/(?:\.|\b)(?:20)?(\d{2})[._-](\d{2})[._-](\d{2})(?:\.|\b)/', (string) $video->video_code, $dm)) {
            $y = (int) $dm[1] > 70 ? 1900 + (int) $dm[1] : 2000 + (int) $dm[1];
            if (checkdate((int) $dm[2], (int) $dm[3], $y)) {
                $videoDate = Carbon::create($y, (int) $dm[2], (int) $dm[3]);
            }
        } elseif ($video->release_at) {
            try {
                $videoDate = Carbon::parse($video->release_at);
            } catch (Throwable) {}
        }

        if ($videoDate !== null) {
            $shortDate = $videoDate->format('ymd');     // 如 261009
            $fullDate = $videoDate->format('Ymd');      // 如 20261009
            $prevDate = $videoDate->copy()->subDay()->format('ymd'); // 如 261008
            $nextDate = $videoDate->copy()->addDay()->format('ymd'); // 如 261010
            $prevFull = $videoDate->copy()->subDay()->format('Ymd');
            $nextFull = $videoDate->copy()->addDay()->format('Ymd');

            if (str_contains($titleNorm, $shortDate) || str_contains($titleNorm, $fullDate)) {
                $score += 50;
            } elseif (str_contains($titleNorm, $prevDate) || str_contains($titleNorm, $nextDate) || str_contains($titleNorm, $prevFull) || str_contains($titleNorm, $nextFull)) {
                // 欧美发行时差偏差 1 天：赋予 40 分
                $score += 40;
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

        // 特征 D: 从 video_code 提取的词元重合率验证（补充当 actors 未预加载或片名简短时的置信度）
        if (!empty($video->video_code)) {
            $codeWords = array_values(array_filter(explode('.', preg_replace('/[_\s-]+/', '.', (string) $video->video_code)), fn($w) => strlen($w) >= 3 && !preg_match('/^\d+$/', $w)));
            if (!empty($codeWords)) {
                $matchedCodeWords = 0;
                foreach ($codeWords as $cw) {
                    if (str_contains($titleNorm, strtolower($cw))) {
                        $matchedCodeWords++;
                    }
                }
                $ratio = $matchedCodeWords / count($codeWords);
                if ($ratio >= 0.5 && $matchedCodeWords >= 2) {
                    $score += 45;
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

        // 自动同步视频的 has_downloads, is_4k, max_quality 及 is_vr 状态
        $qualityUpdates = $this->syncVideoQualities($video);
        if (!empty($qualityUpdates)) {
            $stats['quality_updates'] = $qualityUpdates;
        }

        return $stats;
    }

    /**
     * 根据关联的下载项，精准同步更新视频的 is_4k, max_quality, is_vr 及 has_downloads 字段
     * @return array<string, mixed> 返回本次发生变更的字段键值
     */
    public function syncVideoQualities(Video $video): array
    {
        // 1. 读取关联的下载项（优先使用有效项，无有效项则使用全部）
        $downloads = $video->downloads()->where('status', 1)->get();
        if ($downloads->isEmpty()) {
            $downloads = $video->downloads()->get();
        }

        if ($downloads->isEmpty()) {
            if ($video->has_downloads) {
                $video->update(['has_downloads' => false]);
                $video->has_downloads = false;
                return ['has_downloads' => false];
            }
            return [];
        }

        $has4k = false;
        $has1080p = false;
        $has720p = false;
        $hasVr = false;

        foreach ($downloads as $dl) {
            $res = strtoupper(trim((string) $dl->resolution));
            $title = (string) $dl->title;

            // 4K / 2160p 识别
            if (in_array($res, ['4K', '2160P', 'UHD']) || preg_match('/\b(2160p|4k|uhd)\b/i', $title)) {
                $has4k = true;
            } elseif (in_array($res, ['1080P', 'FHD', 'FULLHD']) || preg_match('/\b(1080p|fhd)\b/i', $title)) {
                $has1080p = true;
            } elseif (in_array($res, ['720P', 'HD']) || preg_match('/\b(720p|hd)\b/i', $title)) {
                $has720p = true;
            }

            // VR 识别
            if (preg_match('/(?:\b|[._\-\[\(])(?:vr|virtual[\s._-]?reality)(?:\b|[._\-\]\)])|(?i:\b[a-z0-9]+vr\b)/i', $title)) {
                $hasVr = true;
            }
        }

        // 辅助检测：若下载项标题中未显式包含 VR，但视频编码或片商明确属于 VR 厂牌
        if (!$hasVr) {
            $rawCode = (string) ($video->video_code ?? '');
            if (preg_match('/(?:\b|[._\-\[\(])(?:vr|virtual[\s._-]?reality)(?:\b|[._\-\]\)])|(?i:\b[a-z0-9]+vr\b)/i', $rawCode)) {
                $hasVr = true;
            } elseif ($video->relationLoaded('channel') && $video->channel && preg_match('/(?:\b|[._\-\[\(])vr(?:\b|[._\-\]\)])|(?i:\b[a-z0-9]+vr\b)/i', (string) $video->channel->name)) {
                $hasVr = true;
            }
        }

        $updates = [];

        // 标记拥有下载项
        if (!$video->has_downloads) {
            $updates['has_downloads'] = true;
        }

        // 4K 与最高画质判定：只要拥有 4k 下载项，即更新 is_4k 为 true，并确保 max_quality 为 2160p
        if ($has4k) {
            if (!$video->is_4k) {
                $updates['is_4k'] = true;
            }
            if ($video->max_quality !== '2160p') {
                $updates['max_quality'] = '2160p';
            }
        } elseif ($has1080p) {
            // 缺失 4K 但拥有 1080P 时，且原视频未标为 4K 时设置 max_quality 为 1080p
            if (!$video->is_4k && $video->max_quality !== '2160p' && $video->max_quality !== '1080p') {
                $updates['max_quality'] = '1080p';
            }
        } elseif ($has720p) {
            if (!$video->is_4k && empty($video->max_quality)) {
                $updates['max_quality'] = '720p';
            }
        }

        // VR 判定：若匹配到 VR 资源，且 is_vr 为 false，则更新 is_vr 为 true
        if ($hasVr && !$video->is_vr) {
            $updates['is_vr'] = true;
        }

        if (!empty($updates)) {
            $video->update($updates);
            foreach ($updates as $k => $v) {
                $video->{$k} = $v;
            }
        }

        return $updates;
    }

    /**
     * 批量处理历史已执行过下载项抓取的视频，重新扫描下载项并同步更新 is_4k, max_quality 与 is_vr
     */
    public function syncAllVideosQualities(bool $dryRun = false, ?callable $progressCallback = null, ?int $limit = null, bool $forceAll = false): array
    {
        $stats = [
            'scanned'             => 0,
            'updated_videos'      => 0,
            'updated_4k'          => 0,
            'updated_max_quality' => 0,
            'updated_vr'          => 0,
            'unchanged'           => 0,
        ];

        $query = $forceAll
            ? Video::query()->orderBy('id')
            : Video::where('has_downloads', true)->orWhereHas('downloads')->orderBy('id');

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        foreach ($query->with(['downloads', 'channel'])->cursor() as $video) {
            $stats['scanned']++;

            if ($dryRun) {
                $cloned = clone $video;
                $updates = $this->syncVideoQualities($cloned);
            } else {
                $updates = $this->syncVideoQualities($video);
            }

            if (!empty($updates)) {
                $stats['updated_videos']++;
                if (isset($updates['is_4k'])) $stats['updated_4k']++;
                if (isset($updates['max_quality'])) $stats['updated_max_quality']++;
                if (isset($updates['is_vr'])) $stats['updated_vr']++;
            } else {
                $stats['unchanged']++;
            }

            if ($progressCallback !== null) {
                $progressCallback($video, $updates, $stats);
            }
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

