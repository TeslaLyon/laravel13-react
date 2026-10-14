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
    public ?string $lastFetchDiagnosis = null;

    /** @var callable|null */
    protected $logger = null;

    public function setLogger(?callable $logger): self
    {
        $this->logger = $logger;
        return $this;
    }

    public function log(string $message, string $level = 'info'): void
    {
        if ($this->logger) {
            ($this->logger)($message, $level);
        }

        match ($level) {
            'error'   => Log::error("[ThePornDB] {$message}"),
            'warn'    => Log::warning("[ThePornDB] {$message}"),
            default   => Log::info("[ThePornDB] {$message}"),
        };
    }

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
     * @param string|null $localHtml 可选：直接传入本地 HTML 内容进行离线测试
     * @return array
     */
    public function crawlSitePage(string $siteSlug, int $page = 1, ?string $localHtml = null): array
    {
        $page = max(1, $page);
        $url = "https://theporndb.net/sites/{$siteSlug}";
        $params = $page > 1 ? ['page' => $page] : [];

        $html = $localHtml !== null ? $localHtml : $this->fetchHtml($url, $params);
        if (empty($html)) {
            return [
                'success'    => false,
                'message'    => "抓取厂牌 [{$siteSlug}] 第 {$page} 页失败：响应内容为空（" . ($this->lastFetchDiagnosis ?? '网络超时或 FlareSolverr 未启动') . "）",
                'site'       => $siteSlug,
                'page'       => $page,
                'scenes'     => [],
                'debug_info' => $this->lastFetchDiagnosis ?? '无响应内容',
            ];
        }

        $inertiaData = $this->extractInertiaData($html);
        if (!$inertiaData || empty($inertiaData['props']['scenes'])) {
            $component = $inertiaData['component'] ?? '';
            $pageTitle = '';
            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) {
                $pageTitle = trim($tm[1]);
            }

            // 1. 如果命中登录页
            if ($component === 'Auth/Login' || str_contains($pageTitle, 'Log in')) {
                return [
                    'success'    => false,
                    'message'    => "解析厂牌 [{$siteSlug}] 第 {$page} 页失败: 页面处于登录页 (Auth/Login)，请配置 TPDB_EMAIL/TPDB_PASSWORD 并加上 --force-login 重新验证",
                    'debug_info' => "HTML 长度: " . strlen($html) . " 字节, 标题: [{$pageTitle}], 组件: [{$component}]",
                    'site'       => $siteSlug,
                    'page'       => $page,
                    'scenes'     => [],
                ];
            }

            // 2. 深入诊断未命中原因
            $isCf = $this->isCloudflareBlocked(200, $html);

            $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($html))), 0, 160);

            $reason = $isCf
                ? "页面被 Cloudflare 5秒盾/人机质询拦截，尚未成功穿透 (标题: [{$pageTitle}])"
                : "页面未找到 Inertia JSON 结构 (标题: [{$pageTitle}], 摘要: {$snippet})";

            return [
                'success'    => false,
                'message'    => "解析厂牌 [{$siteSlug}] 第 {$page} 页失败: {$reason}",
                'debug_info' => "HTML 长度: " . strlen($html) . " 字节, 标题: [{$pageTitle}]",
                'site'       => $siteSlug,
                'page'       => $page,
                'scenes'     => [],
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
     * @param string|null $localDetailHtml 可选：直接传入本地详情页 HTML 内容进行离线测试
     * @return array|null 包含 scene 数据的数组，失败返回 null
     */
    public function crawlSceneDetail(string $sceneUrlOrSlug, ?string $localDetailHtml = null): ?array
    {
        if ($localDetailHtml !== null) {
            $html = $localDetailHtml;
        } else {
            $url = str_starts_with($sceneUrlOrSlug, 'http')
                ? $sceneUrlOrSlug
                : "https://theporndb.net/scenes/" . ltrim($sceneUrlOrSlug, '/');

            $html = $this->fetchHtml($url);
        }

        if (empty($html)) {
            Log::warning("[ThePornDB] 详情页抓取失败，内容为空: {$sceneUrlOrSlug}");
            return null;
        }

        $inertiaData = $this->extractInertiaData($html);
        if (!$inertiaData || empty($inertiaData['props']['scene'])) {
            Log::warning("[ThePornDB] 详情页未解析出 scene 数据: {$sceneUrlOrSlug}");
            return null;
        }

        $scene = $inertiaData['props']['scene'];
        $scene['combined_tags'] = $this->extractCombinedTags($scene);

        return $scene;
    }

    /**
     * 将详情页数据中的 tags 与 markers 融合为统一的标签列表（自动按名称去重）
     *
     * @param array $sceneDetail TPDB scene 节点
     * @return array 融合后的标签对象/名称数组
     */
    public function extractCombinedTags(array $sceneDetail): array
    {
        $tags = [];
        $seen = [];

        // 1. 融合 tags 字段 (通常每项包含 name)
        foreach ($sceneDetail['tags'] ?? [] as $tag) {
            $name = is_array($tag) ? ($tag['name'] ?? $tag['title'] ?? '') : (string) $tag;
            $name = trim($name);
            $key = strtolower($name);
            if (!empty($name) && !isset($seen[$key])) {
                $seen[$key] = true;
                $tags[] = [
                    'name'        => $name,
                    'source'      => 'tag',
                    'description' => is_array($tag) ? ($tag['description'] ?? null) : null,
                ];
            }
        }

        // 2. 融合 markers 字段 (通常每项包含 title，代表视频各时段具体动作/体位)
        foreach ($sceneDetail['markers'] ?? [] as $marker) {
            $name = is_array($marker) ? ($marker['title'] ?? $marker['name'] ?? '') : (string) $marker;
            $name = trim($name);
            $key = strtolower($name);
            if (!empty($name) && !isset($seen[$key])) {
                $seen[$key] = true;
                $tags[] = [
                    'name'   => $name,
                    'source' => 'marker',
                ];
            }
        }

        return $tags;
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
                    // 优先匹配新版规范 slug (例如 vixen-beautiful-ashby-fucks-him-good)
                    if (!empty($siteSlug)) {
                        $prefixedSlug = strtolower($siteSlug) . '-' . $targetSlug;
                        $video = Video::where('slug', $prefixedSlug)->first();
                        if ($video) return $video;
                    }

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
                if (!empty($siteSlug)) {
                    $prefixedSlug = strtolower($siteSlug) . '-' . $extSlug;
                    $video = Video::where('slug', $prefixedSlug)->first();
                    if ($video) return $video;
                }

                $video = Video::where('slug', $extSlug)->first();
                if ($video) return $video;

                $video = Video::where('source_uuid', 'LIKE', "%:{$extSlug}")->first();
                if ($video) return $video;
            }
        }

        // 策略 3：根据 TPDB slug 匹配（例如 vixen-beautiful-ashby-fucks-him-good）
        if (!empty($sceneSlug)) {
            // 直接以完整 TPDB slug 匹配视频的 slug（完全对齐新版 {channel_slug}-{source_slug}）
            $video = Video::where('slug', $sceneSlug)->first();
            if ($video) return $video;

            // 兼容旧版：若带 siteSlug 前缀，尝试去除前缀匹配原始 slug
            if ($siteSlug && str_starts_with($sceneSlug, "{$siteSlug}-")) {
                $strippedSlug = substr($sceneSlug, strlen("{$siteSlug}-"));
                $video = Video::where('slug', $strippedSlug)->first();
                if ($video) return $video;
            }
        }

        // 策略 4：根据视频名称严格或不区分大小写匹配
        if (!empty($sceneTitle)) {
            $video = Video::where('name', $sceneTitle)->first();
            if ($video) return $video;

            $video = Video::whereRaw('LOWER(name) = ?', [strtolower($sceneTitle)])->first();
            if ($video) return $video;

            // 标题转换为 slug 后再次匹配
            $titleSlug = Str::slug($sceneTitle);
            if (!empty($siteSlug)) {
                $prefixedTitleSlug = strtolower($siteSlug) . '-' . $titleSlug;
                $video = Video::where('slug', $prefixedTitleSlug)->first();
                if ($video) return $video;
            }

            $video = Video::where('slug', $titleSlug)->first();
            if ($video) return $video;
        }

        return null;
    }

    /**
     * 将从 TPDB 抓取到的标签同步给指定的本地视频 (支持 tags 与 markers 融合数据)
     *
     * @param Video $video 本地视频模型
     * @param array $tagsData TPDB 的 tags/markers 数组，每项通常为 ['id' => 1, 'name' => 'Blowjob', ...] 或 ['title' => 'Pussy Licking', ...]
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
        $seen = [];

        foreach ($tagsData as $tagItem) {
            $rawName = is_array($tagItem) ? ($tagItem['name'] ?? $tagItem['title'] ?? '') : (string) $tagItem;
            $rawName = trim($rawName);
            if (empty($rawName)) {
                continue;
            }

            $key = strtolower($rawName);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            if ($dryRun) {
                $nameZh = $this->resolveChineseName($rawName);
                $appliedTags[] = [
                    'name'    => $rawName,
                    'name_zh' => $nameZh,
                ];
                continue;
            }

            try {
                $tagModel = $this->getOrCreateTag($rawName);
                if ($tagModel) {
                    $tagIds[] = $tagModel->id;
                    $appliedTags[] = [
                        'id'      => $tagModel->id,
                        'name'    => $tagModel->name,
                        'name_zh' => $tagModel->name_zh,
                    ];
                }
            } catch (\Throwable $e) {
                $this->log("⚠️  [标签同步] 处理标签 [{$rawName}] 异常: " . $e->getMessage(), 'warn');
            }
        }

        if (!$dryRun && !empty($tagIds)) {
            try {
                $video->tags()->syncWithoutDetaching($tagIds);

                // 更新标签关联视频数量统计
                Tag::whereIn('id', $tagIds)->increment('video_num');
            } catch (\Throwable $e) {
                $this->log("❌ [标签关联] 关联标签至视频 [ID: {$video->id}] 异常: " . $e->getMessage(), 'error');
            }
        }

        return [
            'success'      => true,
            'synced_count' => count($appliedTags),
            'tags'         => $appliedTags,
        ];
    }

    /**
     * 获取或创建标签（自动匹配中文翻译与友好 slug，支持 name/name_zh/slug 智能去重防冲突）
     *
     * @param string $tagName 原始英文标签名
     * @return Tag|null
     */
    public function getOrCreateTag(string $tagName): ?Tag
    {
        $tagName = trim($tagName);
        if (empty($tagName)) {
            return null;
        }

        $slug = Str::slug($tagName);
        if (empty($slug)) {
            $slug = strtolower(str_replace([' ', '/', '(', ')', '+', '&', '_', '.'], '-', $tagName));
        }

        // 1. 匹配中文名称（若词典无对应翻译，则安全回退为原英文名）
        $nameZh = $this->resolveChineseName($tagName);

        // 2. 尝试从数据库查询现有标签：
        // 匹配原则：无论 slug、英文名（忽略大小写）还是中文译名相同，均归属于同一标签概念
        $existing = Tag::where('slug', $slug)
            ->orWhereRaw('LOWER(name) = ?', [strtolower($tagName)])
            ->orWhere('name_zh', $nameZh)
            ->first();

        if ($existing) {
            // 若现有标签没有中文翻译（或之前存了英文名），且当前词典已具备中文翻译，则自动补全中文名
            if ((empty($existing->name_zh) || $existing->name_zh === $existing->name) && $nameZh !== $tagName) {
                try {
                    $existing->update(['name_zh' => $nameZh]);
                } catch (\Throwable) {}
            }
            return $existing;
        }

        // 3. 不存在时创建新标签（带唯一性冲突安全保护）
        try {
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
        } catch (\Throwable $e) {
            // 若因并发写入或 name_zh 唯一性冲突报错，再次检索现有记录并返回
            $existing = Tag::where('slug', $slug)
                ->orWhereRaw('LOWER(name) = ?', [strtolower($tagName)])
                ->orWhere('name_zh', $nameZh)
                ->first();

            if ($existing) {
                return $existing;
            }

            // 若 slug 发生冲突，附加哈希后缀重试创建
            $fallbackSlug = $slug . '-' . substr(md5($tagName), 0, 4);
            try {
                return Tag::create([
                    'name'              => $tagName,
                    'name_zh'           => $nameZh,
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
            } catch (\Throwable $ex) {
                $this->log("⚠️  [标签入库] 创建标签 [{$tagName}] 异常: " . $ex->getMessage(), 'warn');
                return null;
            }
        }
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
        $this->log("🔍 [解析] 正在从 HTML (长度 " . strlen($html) . " 字节) 匹配 Inertia 数据...", 'line');

        // 兼容不同的属性顺序、引号与换行
        $patterns = [
            '/<script[^>]*data-page=["\']app["\'][^>]*>(.*?)<\/script>/s',
            '/<script[^>]*id=["\']app["\'][^>]*data-page[^>]*>(.*?)<\/script>/s',
            '/<script[^>]*type=["\']application\/json["\'][^>]*data-page[^>]*>(.*?)<\/script>/s',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $json = json_decode($matches[1], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                    $component = $json['component'] ?? '未知组件';
                    $hasScenes = !empty($json['props']['scenes']);
                    $hasScene = !empty($json['props']['scene']);
                    $this->log("✅ [解析] 成功解析 Inertia JSON! 组件: [{$component}], scenes: " . ($hasScenes ? '有' : '无') . ", scene: " . ($hasScene ? '有' : '无'), 'info');
                    return $json;
                } else {
                    $this->log("⚠️  [解析] 命中 script 标签但 JSON 解码失败: " . json_last_error_msg(), 'warn');
                }
            }
        }

        // 提取标题与前置摘要便于排查
        $pageTitle = '无标题';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $tm)) {
            $pageTitle = trim($tm[1]);
        }
        $snippet = mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($html))), 0, 150);
        $this->log("❌ [解析] 未能找到任何 <script data-page=\"app\"> 标签！", 'warn');
        $this->log("   📄 网页标题: [{$pageTitle}]", 'comment');
        $this->log("   📝 网页摘要: [{$snippet}...]", 'comment');

        return null;
    }

    /**
     * 精准判断响应是否真正遭遇 Cloudflare 5秒盾或人机质询阻断
     *
     * 避免因现代网站在正常页面中嵌入 /cdn-cgi/challenge-platform/ 脚本或 Turnstile 样式而造成的假阳性误判。
     *
     * @param int $statusCode HTTP 状态码
     * @param string $body 响应 HTML 内容
     * @return bool
     */
    public function isCloudflareBlocked(int $statusCode, string $body): bool
    {
        // 1. 如果状态码为 200，且已成功包含 Inertia 结构或正常单页应用标志，绝非阻断
        if ($statusCode === 200) {
            $hasInertia = str_contains($body, 'data-page=') || str_contains($body, 'id="app"');
            if ($hasInertia) {
                // 仅当标题明确是 Cloudflare 质询拦截时才算拦截
                $isCfTitle = str_contains($body, '<title>Just a moment...</title>')
                    || str_contains($body, '<title>Attention Required! | Cloudflare</title>');
                if (!$isCfTitle && !str_contains($body, 'id="challenge-running"')) {
                    return false;
                }
            }
        }

        // 2. 典型 Cloudflare 拦截特征：403 或 503 状态码
        if ($statusCode === 403 || $statusCode === 503) {
            return true;
        }

        // 3. 页面标题或特定质询 DOM 容器（注意：绝不能仅凭 /challenge-platform/ 或 Turnstile 脚本路径误判）
        if (str_contains($body, '<title>Just a moment...</title>')
            || str_contains($body, '<title>Attention Required! | Cloudflare</title>')
            || str_contains($body, 'id="challenge-running"')
            || str_contains($body, 'id="challenge-stage"')
            || str_contains($body, 'id="cf-please-wait"')
            || (str_contains($body, 'cf-browser-verification') && !str_contains($body, 'data-page='))
            || (str_contains($body, 'Verify you are human') && !str_contains($body, 'data-page='))
        ) {
            return true;
        }

        return false;
    }

    /**
     * 自动登录 ThePornDB 并将登录凭证持久化到 Redis (默认 30 天)
     *
     * @param string|null $email 账号邮箱（留空读取配置/环境变量）
     * @param string|null $password 账号密码（留空读取配置/环境变量）
     * @param bool $force 是否强制重新登录并刷新凭证
     * @return bool 是否登录成功
     */
    public function login(?string $email = null, ?string $password = null, bool $force = false): bool
    {
        $email = $email ?: (config('services.tpdb.email') ?: env('TPDB_EMAIL', 'yimaneilicj@gmail.com'));
        $password = $password ?: (config('services.tpdb.password') ?: env('TPDB_PASSWORD', '!UdgGXcL4!ZuyBD'));

        if (empty($email) || empty($password)) {
            $this->log("❌ [登录] 未配置 ThePornDB 账号或密码 (TPDB_EMAIL / TPDB_PASSWORD)", 'error');
            return false;
        }

        $hostKey = 'theporndb.net';
        $sessionId = 'sess_theporndb';
        $flaresolverrUrl = config('services.flaresolverr.url', env('FLARESOLVERR_URL', 'http://flaresolverr:8191/v1'));
        $cleanUrl = rtrim($flaresolverrUrl, '/');
        $endpoint = str_ends_with($cleanUrl, '/v1') ? $cleanUrl : "{$cleanUrl}/v1";

        // 若非强制且 Redis 中已有有效登录标识，直接复用
        if (!$force) {
            try {
                $isAuth = Redis::get('crawler:tpdb_authenticated');
                $cachedCookieStr = Redis::get("crawler:cookie_str:{$hostKey}");
                if ($isAuth && !empty($cachedCookieStr)) {
                    $this->log("🔑 [登录] 命中已持久化的 ThePornDB 登录会话，无需重复登录", 'info');
                    return true;
                }
            } catch (Throwable) {}
        }

        $this->log("🔐 [登录] 准备执行 ThePornDB 自动登录 (账号: {$email})...", 'info');

        // 清理旧的不完整凭证，保证纯净登录
        try {
            Redis::del("crawler:tpdb_authenticated");
            Redis::del("crawler:cookie_str:{$hostKey}");
            Redis::del("crawler:cookies_array:{$hostKey}");
        } catch (Throwable) {}

        $this->ensureFlareSolverrSession($endpoint, $sessionId);

        $proxy = config('services.crawler.proxy');

        // 步骤 1：访问 /login 获取初始 Cookie 与 XSRF-TOKEN
        $this->log("📡 [登录] 步骤 1/3: 请求 https://theporndb.net/login 获取 CSRF 令牌与初始 Cookie...", 'line');
        $getPayload = [
            'cmd'        => 'request.get',
            'url'        => 'https://theporndb.net/login',
            'session'    => $sessionId,
            'maxTimeout' => 60000,
        ];
        if (!empty($proxy)) {
            $getPayload['proxy'] = ['url' => $proxy];
        }

        $loginHtml = '';
        $loginCookies = [];
        $capturedUa = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');
        try {
            $response = Http::timeout(75)->post($endpoint, $getPayload);
            $getData = $response->json() ?? [];
            if (($getData['status'] ?? '') === 'ok') {
                $loginHtml = $getData['solution']['response'] ?? '';
                $loginCookies = $getData['solution']['cookies'] ?? [];
                if (!empty($getData['solution']['userAgent'])) {
                    $capturedUa = $getData['solution']['userAgent'];
                }
            }
        } catch (Throwable $e) {
            $this->log("❌ [登录] 访问登录页失败: " . $e->getMessage(), 'error');
            return false;
        }

        // 提取 XSRF-TOKEN 与 Cookie 字典
        $xsrfTokenHeader = '';
        $cookieMap = [];
        foreach ($loginCookies as $c) {
            if (!empty($c['name']) && isset($c['value'])) {
                $cookieMap[$c['name']] = $c['value'];
                if ($c['name'] === 'XSRF-TOKEN') {
                    $xsrfTokenHeader = urldecode($c['value']);
                }
            }
        }

        $this->log("🔑 [登录] XSRF 令牌: " . ($xsrfTokenHeader ? '已提取✅' : '未在 Cookie 中找到⚠️'), 'line');

        // 步骤 2：提交认证请求
        $this->log("🚀 [登录] 步骤 2/3: 提交登录认证 (账号: {$email})...", 'line');
        $loginAttemptSuccessful = false;
        $updatedCookies = $loginCookies;

        // 策略 A：直接使用 HTTP 模拟 Inertia.js 客户端 POST 请求（绕过 Form 的 CSRF 限制，最符合现代 Laravel Inertia 标准）
        $cookieHeaderPairs = [];
        foreach ($cookieMap as $k => $v) {
            $cookieHeaderPairs[] = "{$k}={$v}";
        }
        $cookieHeaderStr = implode('; ', $cookieHeaderPairs);

        $inertiaHeaders = [
            'user-agent'                => $capturedUa,
            'cookie'                    => $cookieHeaderStr,
            'x-xsrf-token'              => $xsrfTokenHeader,
            'x-inertia'                 => 'true',
            'x-requested-with'          => 'XMLHttpRequest',
            'referer'                   => 'https://theporndb.net/login',
            'origin'                    => 'https://theporndb.net',
            'accept'                    => 'text/html, application/xhtml+xml',
            'content-type'              => 'application/json',
            'sec-ch-ua-mobile'          => '?0',
            'sec-fetch-dest'            => 'empty',
            'sec-fetch-mode'            => 'cors',
            'sec-fetch-site'            => 'same-origin',
        ];

        try {
            $req = Http::timeout(25)
                ->withOptions(['allow_redirects' => false]) // 捕获 302 重定向与其 Set-Cookie
                ->withHeaders($inertiaHeaders);

            if (!empty($proxy)) {
                $req = $req->withOptions(['proxy' => $proxy]);
            }

            $httpRes = $req->post('https://theporndb.net/login', [
                'email'    => $email,
                'password' => $password,
                'remember' => true,
            ]);

            $status = $httpRes->status();
            $this->log("📥 [登录] Inertia 直连认证响应码: [{$status}]", 'line');

            // 提取响应中的所有 Set-Cookie 头
            $setCookies = $httpRes->toPsrResponse()->getHeader('Set-Cookie');
            $newCookieMap = [];
            foreach ($setCookies as $sc) {
                $parts = explode(';', $sc);
                $main = trim($parts[0] ?? '');
                if (!empty($main) && str_contains($main, '=')) {
                    [$cName, $cVal] = explode('=', $main, 2);
                    $newCookieMap[$cName] = $cVal;
                    $cookieMap[$cName] = $cVal;
                }
            }

            // 若响应是 302（登录重定向），或者 Set-Cookie 包含了 remember_web 或 session
            if ($status === 302 || $status === 200 || !empty($newCookieMap)) {
                $hasRemember = false;
                foreach ($newCookieMap as $k => $v) {
                    if (str_starts_with($k, 'remember_web_')) {
                        $hasRemember = true;
                        break;
                    }
                }
                if ($hasRemember || isset($newCookieMap['theporndb_session']) || $status === 302) {
                    $loginAttemptSuccessful = true;
                    $this->log("🎉 [登录] Inertia 直连认证通过 (Set-Cookie 捕获成功)！", 'info');
                    // 更新 $updatedCookies 数组供 FlareSolverr 注入
                    $updatedCookies = [];
                    foreach ($cookieMap as $k => $v) {
                        $updatedCookies[] = [
                            'name'   => $k,
                            'value'  => $v,
                            'domain' => '.theporndb.net',
                            'path'   => '/',
                        ];
                    }
                }
            } elseif ($status === 422) {
                $errors = $httpRes->json('props.errors') ?? $httpRes->json('errors') ?? [];
                $this->log("❌ [登录] 账号验证失败 (422): " . json_encode($errors, JSON_UNESCAPED_UNICODE), 'error');
                return false;
            }
        } catch (Throwable $e) {
            $this->log("⚠️  [登录] Inertia 直连尝试异常: " . $e->getMessage() . "，切换至 FlareSolverr 模式...", 'warn');
        }

        // 策略 B：若策略 A 未成功，通过 FlareSolverr 提交 POST（切忌带 _token，携带 X-XSRF-TOKEN 请求头）
        if (!$loginAttemptSuccessful) {
            $this->log("🔄 [登录] 尝试通过 FlareSolverr 提交 POST 任务...", 'line');
            $postPayload = [
                'cmd'        => 'request.post',
                'url'        => 'https://theporndb.net/login',
                'session'    => $sessionId,
                'postData'   => http_build_query([
                    'email'    => $email,
                    'password' => $password,
                    'remember' => 'true',
                ]),
                'headers'    => [
                    'X-XSRF-TOKEN'     => $xsrfTokenHeader,
                    'X-Inertia'        => 'true',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Referer'          => 'https://theporndb.net/login',
                ],
                'maxTimeout' => 60000,
            ];
            if (!empty($proxy)) {
                $postPayload['proxy'] = ['url' => $proxy];
            }

            try {
                $postResponse = Http::timeout(75)->post($endpoint, $postPayload);
                $postData = $postResponse->json() ?? [];
                if (($postData['status'] ?? '') === 'ok') {
                    $cookies = $postData['solution']['cookies'] ?? [];
                    if (!empty($cookies)) {
                        $updatedCookies = $cookies;
                    }
                }
            } catch (Throwable $e) {
                $this->log("⚠️  [登录] FlareSolverr POST 异常: " . $e->getMessage(), 'warn');
            }
        }

        // 步骤 3：验证登录会话有效性（访问 /sites/vixen 检查 auth 数据与片单数据）
        $this->log("🔍 [登录] 步骤 3/3: 探测受保护页面验证登录状态...", 'line');
        $verifyPayload = [
            'cmd'        => 'request.get',
            'url'        => 'https://theporndb.net/sites/vixen',
            'session'    => $sessionId,
            'cookies'    => $updatedCookies,
            'maxTimeout' => 45000,
        ];
        if (!empty($proxy)) {
            $verifyPayload['proxy'] = ['url' => $proxy];
        }

        try {
            $probeRes = Http::timeout(50)->post($endpoint, $verifyPayload);
            $probeData = $probeRes->json() ?? [];
            $probeHtml = $probeData['solution']['response'] ?? '';
            $finalCookies = $probeData['solution']['cookies'] ?? $updatedCookies;
            $probeInertia = $this->extractInertiaData($probeHtml);

            $isLoggedIn = false;
            $authName = '';
            $authEmail = '';

            if ($probeInertia) {
                $authEmail = $probeInertia['props']['auth']['email'] ?? null;
                $authName = $probeInertia['props']['auth']['name'] ?? '';
                $component = $probeInertia['component'] ?? '';

                if (!empty($authEmail)) {
                    $isLoggedIn = true;
                } elseif (!empty($probeInertia['props']['scenes'])) {
                    $isLoggedIn = true;
                } elseif ($component !== 'Auth/Login') {
                    $isLoggedIn = true;
                }
            }

            // 如果还是没命中，再检查返回的 Cookies 中是否有 remember_web_ 开头的 cookie
            if (!$isLoggedIn) {
                foreach ($finalCookies as $c) {
                    if (str_starts_with($c['name'] ?? '', 'remember_web_')) {
                        $isLoggedIn = true;
                        break;
                    }
                }
            }

            if ($isLoggedIn) {
                $cookiePairs = [];
                foreach ($finalCookies as $c) {
                    if (!empty($c['name']) && isset($c['value'])) {
                        $cookiePairs[] = "{$c['name']}={$c['value']}";
                        if ($c['name'] === 'cf_clearance') {
                            try {
                                Redis::setex("crawler:cf_clearance:{$hostKey}", 7200, $c['value']);
                            } catch (Throwable) {}
                        }
                    }
                }

                $cookieStr = implode('; ', $cookiePairs);
                $ttl = 2592000; // 30 天持久化保存

                try {
                    Redis::setex("crawler:cookie_str:{$hostKey}", $ttl, $cookieStr);
                    Redis::setex("crawler:cookies_array:{$hostKey}", $ttl, json_encode($finalCookies));
                    Redis::setex("crawler:tpdb_authenticated", $ttl, '1');
                    Redis::setex("crawler:tpdb_auth_user", $ttl, $authEmail ?: $email);
                    if (!empty($probeData['solution']['userAgent'])) {
                        Redis::setex('crawler:user_agent', $ttl, $probeData['solution']['userAgent']);
                    }
                } catch (Throwable) {}

                $userNameText = $authName ? " [用户: {$authName}]" : "";
                $this->log("🎉 [登录] ThePornDB 登录成功！账号: {$email}{$userNameText}，会话凭证已持久化缓存至 Redis (有效期 30 天)", 'info');
                return true;
            }

            $this->log("❌ [登录] 验证失败：受保护页面仍重定向至登录页或未找到用户认证标识", 'error');
            return false;
        } catch (Throwable $e) {
            $this->log("❌ [登录] 登录验证过程异常: " . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * 抓取网页 HTML（优先走带 Cookie 的 HTTP 快速通道，拦截后自动激活 FlareSolverr 求解，遭遇登录要求自动执行登录）
     */
    protected function fetchHtml(string $url, array $params = [], bool $canRetryLogin = true): string
    {
        $flaresolverrUrl = config('services.flaresolverr.url', env('FLARESOLVERR_URL', 'http://flaresolverr:8191/v1'));
        $hostKey = 'theporndb.net';

        $this->log("--------------------------------------------------", 'line');
        $this->log("🌐 [请求] 开始请求目标: {$url}", 'line');
        $this->log("⚙️  [配置] FlareSolverr URL: [{$flaresolverrUrl}]", 'line');

        $html = '';

        try {
            $proxy = config('services.crawler.proxy');
            $customUserAgent = config('services.crawler.user_agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36');

            if (!empty($proxy)) {
                $this->log("🔌 [代理] 使用代理: [{$proxy}]", 'line');
            }

            // 1. 读取配套的 User-Agent
            try {
                $cachedUa = Redis::get('crawler:user_agent');
                if (!empty($cachedUa)) {
                    $customUserAgent = $cachedUa;
                }
            } catch (Throwable) {}

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
            } catch (Throwable) {}

            if (!empty($cookie)) {
                $this->log("🍪 [Cookie] 命中 Redis 缓存 Cookie (" . substr($cookie, 0, 30) . "...)，尝试常规 HTTP 直连", 'line');
            } else {
                $this->log("🍪 [Cookie] Redis 中无缓存 Cookie", 'line');
            }

            // 若完全无 Cookie，且有配置 FlareSolverr，直接唤醒 FlareSolverr 首次获取
            if (!empty($flaresolverrUrl) && empty($cookie)) {
                $this->log("🛡️  [路由] 无可用 Cookie，直接调度 FlareSolverr 穿透 5 秒盾...", 'info');
                $html = $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
            } else {
                // 3. 构建现代浏览器指纹头进行直连
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

                $this->log("🚀 [直连] 发起 HTTP GET 请求...", 'line');
                $request = Http::timeout(25)
                    ->retry(2, 1000, throw: false)
                    ->withHeaders($headers);

                if (!empty($proxy)) {
                    $request = $request->withOptions(['proxy' => $proxy]);
                }

                $response = $request->get($url, $params);
                $statusCode = $response->status();
                $body = $response->body();
                $this->log("📥 [直连] 收到 HTTP 响应，状态码: [{$statusCode}], 内容长度: " . strlen($body) . " 字节", 'line');

                // 4. 判定是否遭遇 5 秒盾或未授权阻断
                $isCfBlocked = $this->isCloudflareBlocked($statusCode, $body);

                if ($isCfBlocked) {
                    $this->log("⚠️  [直连] 判定命中 Cloudflare 盾或 403 阻断 (HTTP {$statusCode})！清除过期凭证，切换至 FlareSolverr 求解...", 'warn');
                    try {
                        Redis::del("crawler:cf_clearance:{$hostKey}");
                        Redis::del("crawler:cookie_str:{$hostKey}");
                        Redis::del("crawler:cookies_array:{$hostKey}");
                        Redis::del("crawler:tpdb_authenticated");
                    } catch (Throwable) {}

                    if (!empty($flaresolverrUrl)) {
                        $html = $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
                    } else {
                        $this->lastFetchDiagnosis = "命中 Cloudflare 盾 (HTTP {$statusCode})，但未配置或无法连接 FlareSolverr";
                        $this->log("❌ [直连] 未配置 FlareSolverr，无法穿透 5 秒盾！", 'error');
                        return '';
                    }
                } else {
                    $html = $response->successful() ? $body : '';
                }
            }
        } catch (Throwable $e) {
            $this->log("❌ [直连] HTTP 请求异常: " . $e->getMessage() . "，尝试切换至 FlareSolverr...", 'warn');
            if (!empty($flaresolverrUrl)) {
                $html = $this->fetchViaFlareSolverr($flaresolverrUrl, $url, $params);
            } else {
                $this->lastFetchDiagnosis = "请求异常: " . $e->getMessage();
                return '';
            }
        }

        // 5. 检查是否重定向或停留在 Auth/Login 登录页
        if (!empty($html)) {
            $isLoginPage = str_contains($html, '"component":"Auth/Login"')
                || (str_contains($html, 'data-page="app"') && str_contains($html, 'Auth/Login'))
                || (bool) preg_match('/<title[^>]*>.*Log in.*<\/title>/i', $html);

            if ($isLoginPage) {
                if ($canRetryLogin) {
                    $this->log("🔒 [鉴权] 检测到当前处于未登录状态 (Auth/Login)，正在唤醒自动登录 ThePornDB...", 'warn');
                    $loginOk = $this->login();
                    if ($loginOk) {
                        $this->log("🔄 [重试] 登录成功，重新拉取目标页面: {$url}", 'info');
                        return $this->fetchHtml($url, $params, canRetryLogin: false);
                    } else {
                        $this->log("❌ [鉴权] 自动登录失败，终止抓取受保护页面: {$url}", 'error');
                        $this->lastFetchDiagnosis = "自动登录失败，无法访问受保护页面";
                        return '';
                    }
                } else {
                    $this->log("❌ [鉴权] 重试登录后页面仍要求登录: {$url}", 'error');
                    $this->lastFetchDiagnosis = "登录凭证无效或未通过验证";
                    return '';
                }
            }
        }

        return $html;
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

            $this->log("🤖 [FlareSolverr] 准备调用接口: {$endpoint}", 'line');
            $this->ensureFlareSolverrSession($endpoint, $sessionId);

            // 防 Chromium 标签页内存泄露：超过 15 次重置会话
            $this->sessionRequestCounts[$sessionId] = ($this->sessionRequestCounts[$sessionId] ?? 0) + 1;
            if ($this->sessionRequestCounts[$sessionId] > 15) {
                $this->log("🔄 [FlareSolverr] 会话已连续请求 15 次，重置会话以释放 Chromium 内存...", 'line');
                $this->destroyFlareSolverrSession($endpoint, $sessionId);
                $this->ensureFlareSolverrSession($endpoint, $sessionId);
                $this->sessionRequestCounts[$sessionId] = 1;
            }

            $startTime = microtime(true);
            $this->log("⏳ [FlareSolverr] 提交 request.get 任务 [会话: {$sessionId}]，等待浏览器穿透 Cloudflare (最多60s)...", 'comment');

            $postData = [
                'cmd'        => 'request.get',
                'url'        => $url,
                'session'    => $sessionId,
                'maxTimeout' => 60000,
            ];

            // 注入已缓存的 cookies 数组，防止会话重建后丢登录态
            try {
                $cachedCookiesRaw = Redis::get("crawler:cookies_array:{$hostKey}");
                if (!empty($cachedCookiesRaw)) {
                    $cachedCookies = json_decode($cachedCookiesRaw, true);
                    if (!empty($cachedCookies) && is_array($cachedCookies)) {
                        $postData['cookies'] = $cachedCookies;
                    }
                }
            } catch (Throwable) {}

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
                str_contains($errMsg, 'chromedriver') ||
                str_contains($errMsg, 'timeout')
            );

            if ($needsRecreate) {
                $this->log("⚠️  [FlareSolverr] 会话异常 ({$errMsg})，重新建立会话并重试...", 'warn');
                $this->destroyFlareSolverrSession($endpoint, $sessionId);
                $this->ensureFlareSolverrSession($endpoint, $sessionId);

                $response = Http::timeout(75)->post($endpoint, $postData);
                $data = $response->json() ?? [];
            }

            $elapsed = round(microtime(true) - $startTime, 2);

            if (($data['status'] ?? '') === 'ok' && !empty($data['solution']['response'])) {
                $respHtml = $data['solution']['response'];
                $this->log("🎉 [FlareSolverr] 穿透成功！耗时: {$elapsed}s，响应 HTML: " . strlen($respHtml) . " 字节", 'info');

                // 检查返回内容是否依然停留在 5 秒盾页面
                if ($this->isCloudflareBlocked(200, $respHtml)) {
                    $this->log("⚠️  [FlareSolverr] 警告：FlareSolverr 返回的 HTML 仍包含 Cloudflare 质询内容（未完全通过），页面可能需要人工验证！", 'warn');
                }

                // 缓存真实 UA
                if (!empty($data['solution']['userAgent'])) {
                    $this->log("👤 [FlareSolverr] 捕获浏览器 User-Agent: " . substr($data['solution']['userAgent'], 0, 45) . "...", 'line');
                    try {
                        Redis::setex('crawler:user_agent', 86400, $data['solution']['userAgent']);
                    } catch (Throwable) {}
                }

                // 提取并缓存 Cookie
                if (!empty($data['solution']['cookies'])) {
                    $cookiePairs = [];
                    $gotCfClearance = false;
                    foreach ($data['solution']['cookies'] as $c) {
                        if (!empty($c['name']) && isset($c['value'])) {
                            $cookiePairs[] = "{$c['name']}={$c['value']}";
                            if ($c['name'] === 'cf_clearance') {
                                $gotCfClearance = true;
                                try {
                                    Redis::setex("crawler:cf_clearance:{$hostKey}", 7200, $c['value']);
                                } catch (Throwable) {}
                            }
                        }
                    }
                    $this->log("🍪 [FlareSolverr] 获取到 Cookies: " . count($cookiePairs) . " 个, cf_clearance: " . ($gotCfClearance ? '已获取✅' : '未找到❌'), 'line');

                    if (!empty($cookiePairs)) {
                        try {
                            $ttl = Redis::get('crawler:tpdb_authenticated') ? 2592000 : 7200;
                            Redis::setex("crawler:cookie_str:{$hostKey}", $ttl, implode('; ', $cookiePairs));
                            Redis::setex("crawler:cookies_array:{$hostKey}", $ttl, json_encode($data['solution']['cookies']));
                        } catch (Throwable) {}
                    }
                }

                return $respHtml;
            }

            $errOutput = $data['message'] ?? $response->body();
            $this->log("❌ [FlareSolverr] 求解失败 (耗时 {$elapsed}s): {$errOutput}", 'error');
            $this->lastFetchDiagnosis = "FlareSolverr 求解失败: {$errOutput}";
            $this->destroyFlareSolverrSession($endpoint, $sessionId);
            return '';
        } catch (Throwable $e) {
            $this->log("❌ [FlareSolverr] 请求过程抛出异常: " . $e->getMessage(), 'error');
            $this->lastFetchDiagnosis = "FlareSolverr 异常: " . $e->getMessage();
            $this->destroyFlareSolverrSession($endpoint, $sessionId);
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
            $this->log("📡 [FlareSolverr] 检查/创建会话 [{$sessionId}]...", 'line');
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
                    $this->log("✅ [FlareSolverr] 会话 [{$sessionId}] 准备完毕", 'line');
                    return;
                }
            }
            $this->log("⚠️  [FlareSolverr] 会话创建返回非成功状态: " . $response->body(), 'warn');
        } catch (Throwable $e) {
            $this->log("❌ [FlareSolverr] 连接 FlareSolverr 失败: " . $e->getMessage() . " (请检查 FLARESOLVERR_URL 是否可通)", 'error');
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

