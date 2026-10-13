<?php

namespace App\Services\Crawler;

use App\Models\Tag;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class ThePornDBCrawlerService
{
    protected array $readyFlareSolverrSessions = [];
    protected array $sessionRequestCounts = [];

    /**
     * 常见欧美成人标签中英对照字典（覆盖体位、动作、特征、场景等核心词汇）
     */
    protected array $tagDictionary = [
        'blowjob'                  => '口交',
        'cum in mouth'             => '射在口中',
        'cum on tits'              => '射在胸上',
        'cum shot'                 => '射精镜头',
        'cumshot'                  => '射精镜头',
        'creampie'                 => '内射',
        'facial'                   => '颜射',
        'doggystyle'               => '后入式',
        'doggystyle - standing'    => '站立后入式',
        'missionary'               => '传教士体位',
        'prone bone'               => '趴卧后入式',
        'pussy licking'            => '舔阴',
        'cunnilingus'              => '舔阴',
        'reverse cowgirl'          => '反向女上位',
        'cowgirl'                  => '女上位',
        'riding'                   => '骑乘位',
        'spooning'                 => '侧卧勺式',
        'anal'                     => '肛交',
        'anal fingering'           => '肛门指交',
        'anal toys'                => '肛门玩具',
        'deep throat'              => '深喉',
        'deepthroat'               => '深喉',
        'face fuck'                => '面交',
        'face sitting'             => '面坐',
        'fisting'                  => '拳交',
        'gagging'                  => '作呕',
        'pussy fingering'          => '指交',
        'spanking'                 => '打屁股',
        'squirt'                   => '潮喷',
        'squirting'                => '潮喷',
        'titty fuck'               => '乳交',
        'titfuck'                  => '乳交',
        'footjob'                  => '足交',
        'handjob'                  => '手交',
        'masturbation'             => '自慰',
        'kissing'                  => '接吻',
        'rimming'                  => '毒龙',
        'hardcore'                 => '硬核',
        'rough sex'                => '激烈性爱',
        'outdoor'                  => '户外',
        'indoors'                  => '室内',
        'bedroom'                  => '卧室',
        'kitchen'                  => '厨房',
        'bathroom'                 => '浴室',
        'shower'                   => '淋浴',
        'pool'                     => '泳池',
        'office'                   => '办公室',
        'hotel'                    => '酒店',
        'beach'                    => '海滩',
        'car'                      => '车震',
        'lingerie'                 => '性感内衣',
        'stockings'                => '丝袜',
        'pantyhose'                => '连裤袜',
        'high heels'               => '高跟鞋',
        'dress'                    => '连衣裙',
        'uniform'                  => '制服',
        'bikini'                   => '比基尼',
        'fishnet'                  => '渔网袜',
        'blonde'                   => '金发',
        'blond hair'               => '金发',
        'brunette'                 => '褐发',
        'brown hair'               => '棕发',
        'redhead'                  => '红发',
        'red head'                 => '红发',
        'black hair'               => '黑发',
        'short hair'               => '短发',
        'big tits'                 => '大胸',
        'huge tits'                => '超大胸',
        'natural tits'             => '自然美胸',
        'small tits'               => '小胸贫乳',
        'big ass'                  => '大屁股',
        'bubble butt'              => '翘臀',
        'shaved'                   => '剃毛',
        'bald pussy'               => '白虎剃毛',
        'trimmed pussy'            => '修剪阴毛',
        'innie pussy'              => '一线天/紧致阴部',
        'piercing'                 => '穿孔',
        'tattoo'                   => '纹身',
        'athletic'                 => '健美',
        'petite'                   => '娇小',
        'skinny'                   => '骨感纤细',
        'thick'                    => '丰满肉感',
        'voluptuous'               => '丰腴多姿',
        'curvy'                    => '曲线曼妙',
        'slim'                     => '苗条',
        'threesome'                => '三人行/3P',
        'foursome'                 => '四人行',
        'gangbang'                 => '多人轮交',
        'orgy'                     => '群交派对',
        'bukkake'                  => '颜射群射',
        'double penetration'       => '双管齐下/双洞齐开',
        'dp'                       => '双重插入',
        'lesbian'                  => '女同',
        'girl on girl'             => '女女百合',
        'pov'                      => '第一人称主视角',
        'blowjob - pov'            => '主观第一视角口交',
        'blowjob - double'         => '双人口交',
        'blowjob - threeway'       => '三人乱交口交',
        'cheating'                 => '出轨背德',
        'couples fantasies'        => '情侣幻想',
        'work fantasies'           => '职场幻想',
        'massage'                  => '按摩情欲',
        'fetish'                   => '恋物癖',
        'bdsm'                     => '绳艺虐恋',
        'bondage'                  => '捆绑',
        'submissive'               => '顺从女奴',
        'dominant'                 => '女王支配',
        'milf'                     => '风骚熟女/人妻',
        'mature'                   => '成熟熟妇',
        'teen'                     => '年轻尤物/少女',
        'college'                  => '大学女生',
        'babes'                    => '极品尤物',
        'european'                 => '欧洲尤物',
        'american'                 => '美国女郎',
        'latina'                   => '拉丁美人',
        'asian'                    => '亚洲佳丽',
        'ebony'                    => '黑美人',
        'interracial'              => '跨种族激情',
        'bbc'                      => '巨根黑人大屌',
        'big dick'                 => '巨屌大阴茎',
        'sex toys'                 => '性玩具',
        'vibrator'                 => '跳蛋震动器',
        'dildo'                    => '假阳具',
        'oil'                      => '精油涂抹',
        'sensual'                  => '感性前戏',
        'erotic'                   => '情色唯美',
        'sneaky'                   => '偷情/偷偷摸摸',
        'amateur'                  => '素人真实',
        'public'                   => '公共场合露出',
        'striptease'               => '脱衣挑逗',
        'first time'               => '初次体验',
        'debut'                    => '出道首秀',
        'side fuck'                => '侧入体位',
        'standing'                 => '站立位',
        'sex'                      => '性爱',
    ];

    /**
     * 爬取指定厂牌的视频列表页
     *
     * @param string $siteSlug 厂牌 slug（如 vixen, blacked, deeper, tushy, slayed 等）
     * @param int $page 页码
     * @return array
     */
    public function crawlSitePage(string $siteSlug, int $page = 1): array
    {
        $page = max(1, $page);
        $url = "https://theporndb.net/sites/{$siteSlug}";
        $params = $page > 1 ? ['page' => $page] : [];

        $html = $this->fetchHtml($url, $params);
        if (empty($html)) {
            return [
                'success' => false,
                'message' => "抓取厂牌 [{$siteSlug}] 第 {$page} 页失败，页面为空",
                'site'    => $siteSlug,
                'page'    => $page,
                'scenes'  => [],
            ];
        }

        $inertiaData = $this->extractInertiaData($html);
        if (!$inertiaData || empty($inertiaData['props']['scenes'])) {
            return [
                'success' => false,
                'message' => "解析厂牌 [{$siteSlug}] 第 {$page} 页 Inertia JSON 数据为空或结构不符",
                'site'    => $siteSlug,
                'page'    => $page,
                'scenes'  => [],
            ];
        }

        $scenesData = $inertiaData['props']['scenes']['data'] ?? [];
        $meta = $inertiaData['props']['scenes']['meta'] ?? [];

        return [
            'success'      => true,
            'site'         => $siteSlug,
            'current_page' => (int) ($meta['current_page'] ?? $page),
            'last_page'    => (int) ($meta['last_page'] ?? 1),
            'total'        => (int) ($meta['total'] ?? count($scenesData)),
            'per_page'     => (int) ($meta['per_page'] ?? count($scenesData)),
            'scenes'       => $scenesData,
        ];
    }

    /**
     * 抓取单部视频详情页
     *
     * @param string $sceneUrlOrSlug 详情页完整链接（https://theporndb.net/scenes/...）或 slug
     * @return array|null 包含 scene 数据的数组，失败返回 null
     */
    public function crawlSceneDetail(string $sceneUrlOrSlug): ?array
    {
        $url = str_starts_with($sceneUrlOrSlug, 'http')
            ? $sceneUrlOrSlug
            : "https://theporndb.net/scenes/" . ltrim($sceneUrlOrSlug, '/');

        $html = $this->fetchHtml($url);
        if (empty($html)) {
            Log::warning("[ThePornDB] 详情页抓取失败，内容为空: {$url}");
            return null;
        }

        $inertiaData = $this->extractInertiaData($html);
        if (!$inertiaData || empty($inertiaData['props']['scene'])) {
            Log::warning("[ThePornDB] 详情页未解析出 scene 数据: {$url}");
            return null;
        }

        return $inertiaData['props']['scene'];
    }

    /**
     * 根据 TPDB 详情数据，在本地数据库中匹配 Video 模型
     *
     * @param array $scene TPDB scene 节点
     * @param string|null $siteSlug 厂牌 slug
     * @return Video|null
     */
    public function findVideoForScene(array $scene, ?string $siteSlug = null): ?Video
    {
        $sceneTitle = trim($scene['title'] ?? '');
        $sceneUrl = trim($scene['url'] ?? '');
        $sceneSlug = trim($scene['slug'] ?? '');
        $externalId = trim($scene['external_id'] ?? '');

        // 策略 1：根据原站 url 提取官方 slug (例如 https://www.vixen.com/videos/beautiful-ashby-fucks-him-good)
        if (!empty($sceneUrl)) {
            $path = parse_url($sceneUrl, PHP_URL_PATH);
            if (!empty($path)) {
                $targetSlug = basename($path);
                if (!empty($targetSlug)) {
                    $video = Video::where('slug', $targetSlug)->first();
                    if ($video) return $video;

                    // 匹配 source_uuid 尾部（例如 vixen:1234:beautiful-ashby-fucks-him-good）
                    $video = Video::where('source_uuid', 'LIKE', "%:{$targetSlug}")->first();
                    if ($video) return $video;
                }
            }
        }

        // 策略 2：根据 external_id 匹配（例如 vixen:beautiful-ashby-fucks-him-good）
        if (!empty($externalId) && str_contains($externalId, ':')) {
            $parts = explode(':', $externalId);
            $extSlug = end($parts);
            if (!empty($extSlug)) {
                $video = Video::where('slug', $extSlug)->first();
                if ($video) return $video;

                $video = Video::where('source_uuid', 'LIKE', "%:{$extSlug}")->first();
                if ($video) return $video;
            }
        }

        // 策略 3：根据 TPDB slug 去除片商前缀匹配（例如 vixen-beautiful-ashby-fucks-him-good -> beautiful-ashby-fucks-him-good）
        if (!empty($sceneSlug)) {
            if ($siteSlug && str_starts_with($sceneSlug, "{$siteSlug}-")) {
                $strippedSlug = substr($sceneSlug, strlen("{$siteSlug}-"));
                $video = Video::where('slug', $strippedSlug)->first();
                if ($video) return $video;
            }

            $video = Video::where('slug', $sceneSlug)->first();
            if ($video) return $video;
        }

        // 策略 4：根据视频名称严格或不区分大小写匹配
        if (!empty($sceneTitle)) {
            $video = Video::where('name', $sceneTitle)->first();
            if ($video) return $video;

            $video = Video::whereRaw('LOWER(name) = ?', [strtolower($sceneTitle)])->first();
            if ($video) return $video;

            // 标题转换为 slug 后再次匹配
            $titleSlug = Str::slug($sceneTitle);
            $video = Video::where('slug', $titleSlug)->first();
            if ($video) return $video;
        }

        return null;
    }

    /**
     * 将从 TPDB 抓取到的标签同步给指定的本地视频
     *
     * @param Video $video 本地视频模型
     * @param array $tagsData TPDB 的 tags 数组，每项通常为 ['id' => 1, 'name' => 'Blowjob', ...]
     * @param bool $dryRun 是否仅演练，不实际入库
     * @return array 同步结果与标签明细
     */
    public function syncTagsToVideo(Video $video, array $tagsData, bool $dryRun = false): array
    {
        if (empty($tagsData)) {
            return [
                'success'      => true,
                'synced_count' => 0,
                'tags'         => [],
            ];
        }

        $tagIds = [];
        $appliedTags = [];

        foreach ($tagsData as $tagItem) {
            $rawName = is_array($tagItem) ? ($tagItem['name'] ?? '') : (string) $tagItem;
            $rawName = trim($rawName);
            if (empty($rawName)) {
                continue;
            }

            if ($dryRun) {
                $nameZh = $this->resolveChineseName($rawName);
                $appliedTags[] = [
                    'name'    => $rawName,
                    'name_zh' => $nameZh,
                ];
                continue;
            }

            $tagModel = $this->getOrCreateTag($rawName);
            if ($tagModel) {
                $tagIds[] = $tagModel->id;
                $appliedTags[] = [
                    'id'      => $tagModel->id,
                    'name'    => $tagModel->name,
                    'name_zh' => $tagModel->name_zh,
                ];
            }
        }

        if (!$dryRun && !empty($tagIds)) {
            $video->tags()->syncWithoutDetaching($tagIds);

            // 更新标签关联视频数量统计
            Tag::whereIn('id', $tagIds)->increment('video_num');
        }

        return [
            'success'      => true,
            'synced_count' => count($appliedTags),
            'tags'         => $appliedTags,
        ];
    }

    /**
     * 获取或创建标签（自动匹配中文翻译与友好 slug）
     *
     * @param string $tagName 原始英文标签名
     * @return Tag
     */
    public function getOrCreateTag(string $tagName): Tag
    {
        $tagName = trim($tagName);
        $slug = Str::slug($tagName);
        if (empty($slug)) {
            $slug = strtolower(str_replace([' ', '/', '(', ')', '+', '&'], '-', $tagName));
        }

        // 1. 尝试以 name 或 slug 精准查询现有标签
        $existing = Tag::where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [strtolower($tagName)])
            ->first();

        if ($existing) {
            // 若现有标签没有中文翻译，尝试用词典补全
            if (empty($existing->name_zh) || $existing->name_zh === $existing->name) {
                $zh = $this->resolveChineseName($tagName);
                if (!empty($zh) && $zh !== $tagName) {
                    $existing->update(['name_zh' => $zh]);
                }
            }
            return $existing;
        }

        // 2. 匹配中文名称
        $nameZh = $this->resolveChineseName($tagName);

        return Tag::create([
            'name'              => $tagName,
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
    }

    /**
     * 解析英文标签对应的中文名（优先查本地对照词典）
     */
    public function resolveChineseName(string $tagName): string
    {
        $clean = strtolower(trim($tagName));

        // 1. 字典完全匹配
        if (isset($this->tagDictionary[$clean])) {
            return $this->tagDictionary[$clean];
        }

        // 2. 移除连字符与多余空格再查
        $norm = str_replace('-', ' ', $clean);
        if (isset($this->tagDictionary[$norm])) {
            return $this->tagDictionary[$norm];
        }

        // 3. 词典不存在时，回退到原名（等待后续翻译或人工校正）
        return $tagName;
    }

    /**
     * 从 HTML 提取 Inertia.js 注入的结构化数据
     */
    public function extractInertiaData(string $html): ?array
    {
        if (preg_match('/<script data-page="app" type="application\/json">(.*?)<\/script>/s', $html, $matches)) {
            $json = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                return $json;
            }
        }
        return null;
    }

    /**
     * 抓取网页 HTML（优先走带 Cookie 的 HTTP 快速通道，拦截后自动激活 FlareSolverr 求解）
     */
    protected function fetchHtml(string $url, array $params = []): string
    {
        $flaresolverrUrl = config('services.flaresolverr.url', env('FLARESOLVERR_URL', 'http://flaresolverr:8191/v1'));
        $hostKey = 'theporndb.net';

        try {
            $proxy = config('services.crawler.proxy');
            $customUserAgent = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');

            // 1. 读取配套的 User-Agent
            try {
                $cachedUa = Redis::get('crawler:user_agent');
                if (!empty($cachedUa)) {
                    $customUserAgent = $cachedUa;
                }
            } catch (Throwable) {
            }

            // 2. 读取已缓存的通行 Cookie
            $cookie = null;
            try {
                $cachedCookieStr = Redis::get("crawler:cookie_str:{$hostKey}");
                if (!empty($cachedCookieStr)) {
                    $cookie = $cachedCookieStr;
                } else {
                    $cachedCf = Redis::get("crawler:cf_clearance:{$hostKey}");
                    if (!empty($cachedCf)) {
                        $cookie = "cf_clearance={$cachedCf}";
                    }
                }
            } catch (Throwable) {
            }

            // 若完全无 Cookie，且有配置 FlareSolverr，直接唤醒 FlareSolverr 首次获取
            if (!empty($flaresolverrUrl) && empty($cookie)) {
                return $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
            }

            // 3. 构建现代浏览器指纹头
            $headers = [
                'user-agent'                => $customUserAgent,
                'accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'accept-language'           => 'en-US,en;q=0.9',
                'sec-ch-ua'                 => '"Chromium";v="128", "Not;A=Brand";v="24", "Google Chrome";v="128"',
                'sec-ch-ua-mobile'          => '?0',
                'sec-ch-ua-platform'        => '"macOS"',
                'sec-fetch-dest'            => 'document',
                'sec-fetch-mode'            => 'navigate',
                'sec-fetch-site'            => 'none',
                'sec-fetch-user'            => '?1',
                'upgrade-insecure-requests' => '1',
            ];

            if (!empty($cookie)) {
                $headers['cookie'] = $cookie;
            }

            $request = Http::timeout(25)
                ->retry(2, 1000, throw: false)
                ->withHeaders($headers);

            if (!empty($proxy)) {
                $request = $request->withOptions(['proxy' => $proxy]);
            }

            $response = $request->get($url, $params);

            // 4. 判定是否遭遇 5 秒盾或未授权阻断
            if ($response->status() === 403 || str_contains($response->body(), 'Just a moment...')) {
                Log::warning("[ThePornDB] 命中 Cloudflare 5秒盾，切换至 FlareSolverr 穿透求解: {$url}");
                try {
                    Redis::del("crawler:cf_clearance:{$hostKey}");
                    Redis::del("crawler:cookie_str:{$hostKey}");
                } catch (Throwable) {}

                if (!empty($flaresolverrUrl)) {
                    return $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
                }

                return '';
            }

            return $response->successful() ? $response->body() : '';
        } catch (Throwable $e) {
            Log::warning("[ThePornDB] HTTP 直连异常，尝试切换至 FlareSolverr: " . $e->getMessage());
            if (!empty($flaresolverrUrl)) {
                return $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
            }
            return '';
        }
    }

    /**
     * 通过 FlareSolverr 求解 Cloudflare 5秒盾并缓存 Cookie
     */
    protected function fetchViaFlareSolverr(string $flaresolverrUrl, string $url, array $params = []): string
    {
        try {
            if (!empty($params)) {
                $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($params);
            }

            $hostKey = 'theporndb.net';
            $sessionId = 'sess_theporndb';

            $cleanUrl = rtrim($flaresolverrUrl, '/');
            $endpoint = str_ends_with($cleanUrl, '/v1') ? $cleanUrl : "{$cleanUrl}/v1";

            $this->ensureFlareSolverrSession($endpoint, $sessionId);

            // 防 Chromium 标签页内存泄露：超过 15 次重置会话
            $this->sessionRequestCounts[$sessionId] = ($this->sessionRequestCounts[$sessionId] ?? 0) + 1;
            if ($this->sessionRequestCounts[$sessionId] > 15) {
                Log::info("[ThePornDB] FlareSolverr 会话已请求 15 次，重置会话释放内存...");
                $this->destroyFlareSolverrSession($endpoint, $sessionId);
                $this->ensureFlareSolverrSession($endpoint, $sessionId);
                $this->sessionRequestCounts[$sessionId] = 1;
            }

            $startTime = microtime(true);
            Log::info("[ThePornDB] FlareSolverr 穿透请求: {$url}");

            $postData = [
                'cmd'        => 'request.get',
                'url'        => $url,
                'session'    => $sessionId,
                'maxTimeout' => 60000,
            ];

            $proxy = config('services.crawler.proxy');
            if (!empty($proxy)) {
                $postData['proxy'] = ['url' => $proxy];
            }

            $response = Http::timeout(75)->post($endpoint, $postData);
            $data = $response->json() ?? [];

            // 异常重试
            $status = $data['status'] ?? '';
            $errMsg = strtolower($data['message'] ?? '');
            $needsRecreate = ($status === 'error' || $response->serverError()) && (
                str_contains($errMsg, 'session') ||
                str_contains($errMsg, 'tab crashed') ||
                str_contains($errMsg, 'chromedriver')
            );

            if ($needsRecreate) {
                Log::warning("[ThePornDB] 会话异常，重新建立并重试: {$errMsg}");
                $this->destroyFlareSolverrSession($endpoint, $sessionId);
                $this->ensureFlareSolverrSession($endpoint, $sessionId);

                $response = Http::timeout(75)->post($endpoint, $postData);
                $data = $response->json() ?? [];
            }

            if (($data['status'] ?? '') === 'ok' && !empty($data['solution']['response'])) {
                $elapsed = round(microtime(true) - $startTime, 2);
                Log::info("[ThePornDB] FlareSolverr 穿透成功 (耗时 {$elapsed}s)！");

                // 缓存真实 UA
                if (!empty($data['solution']['userAgent'])) {
                    try {
                        Redis::setex('crawler:user_agent', 86400, $data['solution']['userAgent']);
                    } catch (Throwable) {}
                }

                // 提取并缓存 Cookie
                if (!empty($data['solution']['cookies'])) {
                    $cookiePairs = [];
                    foreach ($data['solution']['cookies'] as $c) {
                        if (!empty($c['name']) && isset($c['value'])) {
                            $cookiePairs[] = "{$c['name']}={$c['value']}";
                            if ($c['name'] === 'cf_clearance') {
                                try {
                                    Redis::setex("crawler:cf_clearance:{$hostKey}", 7200, $c['value']);
                                } catch (Throwable) {}
                            }
                        }
                    }
                    if (!empty($cookiePairs)) {
                        try {
                            Redis::setex("crawler:cookie_str:{$hostKey}", 7200, implode('; ', $cookiePairs));
                        } catch (Throwable) {}
                    }
                }

                return $data['solution']['response'];
            }

            Log::error("[ThePornDB] FlareSolverr 求解失败: " . $response->body());
            return '';
        } catch (Throwable $e) {
            Log::error("[ThePornDB] FlareSolverr 请求异常: " . $e->getMessage());
            return '';
        }
    }

    /**
     * 确保 FlareSolverr 会话存活
     */
    protected function ensureFlareSolverrSession(string $endpoint, string $sessionId): void
    {
        if (isset($this->readyFlareSolverrSessions[$sessionId])) {
            return;
        }

        try {
            $postData = [
                'cmd'     => 'sessions.create',
                'session' => $sessionId,
            ];
            $proxy = config('services.crawler.proxy');
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
            Log::warning("[ThePornDB] 会话 [{$sessionId}] 初始化异常: " . $e->getMessage());
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
}

