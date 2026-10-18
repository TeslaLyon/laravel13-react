<?php

namespace App\Services\Crawler;

use App\Models\Actor;
use App\Models\ActorDetail;
use App\Services\Image\ImageStorageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class TpdbApiService
{
    protected string $baseUrl = 'https://api.theporndb.net';
    protected ?string $token;
    protected ImageStorageService $imageStorage;

    /**
     * 性别常量定义（与 actors 表结构一致：0-未知；1-男；2-女；3-变性人）
     */
    public const GENDER_UNKNOWN = 0;
    public const GENDER_MALE    = 1;
    public const GENDER_FEMALE  = 2;
    public const GENDER_TRANS   = 3;

    public function __construct(?ImageStorageService $imageStorage = null)
    {
        $this->imageStorage = $imageStorage ?? app(ImageStorageService::class);
        try {
            $this->token = config('services.tpdb.api_token') ?: env('TPDB_API_TOKEN');
        } catch (Throwable) {
            $this->token = env('TPDB_API_TOKEN');
        }
    }

    /**
     * 测试 API Token 是否有效与网络连通性
     */
    public function testConnection(): array
    {
        if (empty($this->token)) {
            return [
                'success' => false,
                'message' => '未配置 TPDB_API_TOKEN，请在 .env 文件中添加',
            ];
        }

        try {
            $response = Http::withToken($this->token)
                ->withHeaders([
                    'Accept'     => 'application/json',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
                ])
                ->withOptions(['verify' => false])
                ->timeout(10)
                ->get("{$this->baseUrl}/performers", ['q' => 'Angela White']);

            if ($response->successful()) {
                $data = $response->json('data', []);
                return [
                    'success' => true,
                    'message' => 'ThePornDB API 连接成功且 Token 有效！',
                    'count'   => count($data),
                    'sample'  => $data[0]['name'] ?? null,
                ];
            }

            return [
                'success' => false,
                'message' => 'API 请求返回状态码: ' . $response->status() . '，详情: ' . $response->body(),
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'message' => 'API 请求异常: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 从 ThePornDB API 检索演员信息（带 Redis 缓存 30 天与请求限频保护）
     */
    public function searchPerformer(string $name): ?array
    {
        $trimmedName = trim($name);
        if (empty($trimmedName)) {
            return null;
        }

        $cacheKey = 'tpdb:api:performer:' . md5(mb_strtolower($trimmedName, 'UTF-8'));

        // 1. 尝试从 Redis 缓存读取
        try {
            $cached = Redis::get($cacheKey);
            if ($cached !== null) {
                return json_decode($cached, true);
            }
        } catch (Throwable $e) {
            Log::warning("[TPDB API] Redis 缓存读取异常: " . $e->getMessage());
        }

        if (empty($this->token)) {
            Log::warning("[TPDB API] 未配置 TPDB_API_TOKEN，无法发起外部查询: {$trimmedName}");
            return null;
        }

        // 2. 限频保护：保证两次请求间有短暂间隔（避免触发 TPDB 429 限制）
        $this->throttleRequest();

        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        ];

        // 3. 发起 API 请求
        try {
            $req = Http::withToken($this->token)
                ->withHeaders($headers)
                ->withOptions(['verify' => false])
                ->timeout(10)
                ->retry(2, 1000);

            $proxy = config('services.crawler.proxy');
            if (!empty($proxy)) {
                $req = $req->withOptions(['proxy' => $proxy]);
            }

            $response = $req->get("{$this->baseUrl}/performers", [
                'q' => $trimmedName,
            ]);

            if ($response->status() === 429) {
                Log::warning("[TPDB API] 触发速率限制 (429 Too Many Requests)，暂停 3 秒并放弃当前查询: {$trimmedName}");
                sleep(3);
                return null;
            }

            if ($response->successful()) {
                $list = $response->json('data', []);
                $matchedPerformer = $this->findBestMatchingPerformer($trimmedName, $list);

                // 写入 Redis 缓存（缓存 30 天，即使未查到也写入空数组缓存 1 天，防止对同一冷门名字疯狂重试）
                try {
                    $ttl = $matchedPerformer ? 2592000 : 86400;
                    Redis::setex($cacheKey, $ttl, json_encode($matchedPerformer));
                } catch (Throwable $e) {
                    Log::warning("[TPDB API] Redis 写入异常: " . $e->getMessage());
                }

                return $matchedPerformer;
            } else {
                Log::warning("[TPDB API] 请求失败 [{$response->status()}]: {$response->body()}");
            }
        } catch (Throwable $e) {
            Log::error("[TPDB API] 查询演员异常 [{$trimmedName}]: " . $e->getMessage());
        }

        return null;
    }

    /**
     * 解析演员性别，返回标准状态值：
     * 0: 未知
     * 1: 男
     * 2: 女
     * 3: 变性人
     */
    public function resolveGender(string $name): int
    {
        $trimmedName = trim($name);
        if (empty($trimmedName)) {
            return self::GENDER_UNKNOWN;
        }

        // 步骤 1：本地数据库反查 (Local Actors DB)
        $actorSlug = Str::slug($trimmedName);
        $localActor = Actor::where(function ($q) use ($actorSlug, $trimmedName) {
            $q->where('slug', $actorSlug)
              ->orWhere('name', $trimmedName);
        })->where('gender', '>', 0)->first();

        if ($localActor && $localActor->gender > 0) {
            return (int) $localActor->gender;
        }

        // 步骤 2：第三方权威成人库 API 自动查询 (ThePornDB)
        $performer = $this->searchPerformer($trimmedName);
        if ($performer) {
            return $this->extractGenderFromPerformer($performer);
        }

        return self::GENDER_UNKNOWN;
    }

    /**
     * 从 API 返回的单个 performer 数据中安全提取并解析性别
     */
    public function extractGenderFromPerformer(?array $performer): int
    {
        if (empty($performer)) {
            return self::GENDER_UNKNOWN;
        }

        // 兼容 extras.gender, extra.gender 以及顶层 gender
        $rawGender = $performer['extras']['gender']
            ?? ($performer['extra']['gender']
            ?? ($performer['gender'] ?? null));

        if ($rawGender && is_string($rawGender)) {
            return $this->parseGenderString($rawGender);
        }

        return self::GENDER_UNKNOWN;
    }

    /**
     * 将演员头像转码为 AVIF 格式并上传至 Cloudflare R2 / 本地公共存储
     * 遵循最新项目标准路径规范: images/actors/{actorSlug}/{hash}.avif
     * 严格限制尺寸（最大宽度 280px）与目标文件体积在 20KB 以内
     *
     * @param string $actorSlug 演员 Slug
     * @param string|null $remoteUrl 远程图片地址
     * @param bool $force 是否强制重新压缩覆盖已存在文件
     * @return string|null 存储成功后的相对路径或兜底原地址
     */
    public function storeAvatarAsAvif(string $actorSlug, ?string $remoteUrl, bool $force = false): ?string
    {
        if (empty($remoteUrl)) {
            return null;
        }

        // 如果已经是相对路径且是以 .avif 结尾的本地/CDN路径，直接返回
        if (!str_starts_with($remoteUrl, 'http://') && !str_starts_with($remoteUrl, 'https://')) {
            return $remoteUrl;
        }

        try {
            $cleanUrl = explode('?', $remoteUrl)[0];
            $fileHash = sha1($cleanUrl);
            $targetSubPath = "images/actors/{$actorSlug}/{$fileHash}.avif";

            // 🎯 最大宽度 280px，目标上限 20KB (20480 字节)
            $stored = $this->imageStorage->downloadOptimizeAndStore(
                $remoteUrl,
                $targetSubPath,
                'avif',
                280,
                20 * 1024,
                $force
            );

            if ($stored && !empty($stored['path'])) {
                return $stored['path'];
            }
        } catch (Throwable $e) {
            Log::warning("[TPDB API] 演员 [{$actorSlug}] 头像转码 AVIF 并上传 R2 异常: " . $e->getMessage());
        }

        return $remoteUrl;
    }

    /**
     * 构建符合项目规范的 booty_img 结构 (9:16 封面大图)
     * 仅保存外部图片直链，不上传至 R2
     */
    public function buildBootyImg(?string $imageUrl): ?array
    {
        if (empty($imageUrl)) {
            return null;
        }

        return [
            [
                'src'         => $imageUrl,
                'placeholder' => '',
                'width'       => 500,
                'height'      => 750,
                'highdpi'     => [
                    'double' => $imageUrl,
                ],
            ],
        ];
    }

    /**
     * 根据姓名与头像查找或创建本地 Actor，自动解析性别并补充基本信息与 extras 详情
     */
    public function resolveOrCreateActor(string $name, ?string $avatar = null): Actor
    {
        $actorSlug = Str::slug($name);
        $actor = Actor::where('slug', $actorSlug)->first();

        if ($actor) {
            $performer = $this->searchPerformer($name);
            if ($performer) {
                $updates = [];
                $resolvedGender = $this->extractGenderFromPerformer($performer);
                if ($resolvedGender > 0 && (int) $actor->gender === self::GENDER_UNKNOWN) {
                    $updates['gender'] = $resolvedGender;
                    $updates['is_trans_model'] = ($resolvedGender === self::GENDER_TRANS);
                }

                // 🎯 核心规范：avatar 必须选用 TPDB 的 "face" 字段，且仅 avatar 转码 AVIF 上传到 R2
                $faceUrl = $performer['face'] ?? ($performer['image'] ?? $avatar);
                if (!empty($faceUrl)) {
                    $expectedSubPath = "images/actors/{$actorSlug}/" . sha1(explode('?', $faceUrl)[0]) . ".avif";
                    if (empty($actor->avatar) || $actor->avatar !== $expectedSubPath) {
                        $avifPath = $this->storeAvatarAsAvif($actorSlug, $faceUrl);
                        if ($avifPath && $avifPath !== $actor->avatar) {
                            $updates['avatar'] = $avifPath;
                        }
                    }
                }

                // 🎯 核心规范：booty_img 选用 "image" 字段，保留原外部链接，不上传 R2
                if (empty($actor->booty_img) && !empty($performer['image'])) {
                    $updates['booty_img'] = $this->buildBootyImg($performer['image']);
                }

                if (!empty($updates)) {
                    $actor->update($updates);
                }
                $this->syncActorDetailsFromTpdb($actor, $performer);
            }
            return $actor;
        }

        // 本地不存在，通过 API 查询解析性别与全部元数据
        $performer = $this->searchPerformer($name);
        $gender = $performer ? $this->extractGenderFromPerformer($performer) : self::GENDER_UNKNOWN;

        // 🎯 核心规范：avatar 选用 "face" 字段，转码为 AVIF 上传至 R2
        $faceUrl = $performer['face'] ?? ($performer['image'] ?? $avatar);
        $storedAvatar = '';
        if (!empty($faceUrl)) {
            $storedAvatar = $this->storeAvatarAsAvif($actorSlug, $faceUrl) ?: $faceUrl;
        }

        // 🎯 核心规范：booty_img 选用 "image" 字段，保留原外部链接，不上传 R2
        $bootyImg = !empty($performer['image']) ? $this->buildBootyImg($performer['image']) : null;

        $actor = Actor::create([
            'name'           => $name,
            'slug'           => $actorSlug,
            'avatar'         => $storedAvatar ?: '',
            'booty_img'      => $bootyImg,
            'gender'         => $gender,
            'is_trans_model' => ($gender === self::GENDER_TRANS),
        ]);

        if ($performer) {
            $this->syncActorDetailsFromTpdb($actor, $performer);
        }

        return $actor;
    }

    /**
     * 将 TPDB API 返回的 performer extras 数据结构化提取，并同步至 actor_details 表
     * 仅提取不为空且不为 null 的有效键值对
     *
     * @param Actor $actor
     * @param array $performer API 返回的单个 performer 数组
     * @return ActorDetail|null
     */
    public function syncActorDetailsFromTpdb(Actor $actor, array $performer): ?ActorDetail
    {
        $extras = $performer['extras'] ?? [];
        if (!is_array($extras) || empty($extras)) {
            return null;
        }

        // 1. 分离 socials (社交媒体 / 权威库链接)
        $socials = [];
        if (isset($extras['links']) && is_array($extras['links'])) {
            foreach ($extras['links'] as $platform => $link) {
                if ($link !== null && $link !== '') {
                    $socials[$platform] = trim((string) $link);
                }
            }
        }

        // 2. 字段分类映射
        // 基础信息 (Basic Info) 字段清单
        $basicKeys = [
            'birthday',
            'birthplace',
            'birthplace_code',
            'astrology',
            'nationality',
            'career_start_year',
            'career_end_year',
            'deathday',
            'same_sex_only',
        ];

        // 外形数据 (Physical Info) 字段清单
        $physicalKeys = [
            'ethnicity',
            'hair_colour',
            'eye_colour',
            'weight',
            'height',
            'measurements',
            'cupsize',
            'waist',
            'hips',
            'fake_boobs',
            'tattoos',
            'piercings',
        ];

        $extractedBasic = [];
        $extractedPhysical = [];

        foreach ($extras as $k => $v) {
            // 忽略嵌套 links、null、空字符串以及内部纯时间戳字段
            if ($k === 'links' || $v === null || $v === '') {
                continue;
            }
            if (str_ends_with($k, '_timestamp') || str_ends_with($k, '_type')) {
                continue;
            }
            if ($k === 'gender') {
                continue; // gender 已在 actors 主表存储
            }

            if (in_array($k, $basicKeys, true)) {
                $extractedBasic[$k] = $v;
            } elseif (in_array($k, $physicalKeys, true)) {
                $extractedPhysical[$k] = $v;
            } else {
                // 未知扩展属性默认归入 basic_info
                $extractedBasic[$k] = $v;
            }
        }

        if (empty($extractedBasic) && empty($extractedPhysical) && empty($socials)) {
            return null;
        }

        // 3. 写入或增量合并到 actor_details 表
        try {
            $detail = ActorDetail::firstOrNew(['actor_id' => $actor->id]);

            $existingBasic = is_array($detail->basic_info) ? $detail->basic_info : [];
            $existingPhysical = is_array($detail->physical_info) ? $detail->physical_info : [];
            $existingSocials = is_array($detail->socials) ? $detail->socials : [];

            // 增量合并：优先保留现有非空数据，用 API 数据补充缺失字段
            $detail->basic_info = array_merge($extractedBasic, $existingBasic);
            $detail->physical_info = array_merge($extractedPhysical, $existingPhysical);
            $detail->socials = array_merge($socials, $existingSocials);

            $detail->save();

            return $detail;
        } catch (Throwable $e) {
            Log::warning("[TPDB API] 同步演员 [{$actor->name}] 扩展详情至 actor_details 异常: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 将 API 返回的性别字符串转换为本地系统标准的整数枚举值
     */
    protected function parseGenderString(string $genderStr): int
    {
        $lower = strtolower(trim($genderStr));

        if (str_contains($lower, 'trans') || str_contains($lower, 'non_binary') || str_contains($lower, 'intersex')) {
            return self::GENDER_TRANS;
        }

        if (str_contains($lower, 'female') || str_contains($lower, 'woman')) {
            return self::GENDER_FEMALE;
        }

        if (str_contains($lower, 'male') || str_contains($lower, 'man')) {
            return self::GENDER_MALE;
        }

        return self::GENDER_UNKNOWN;
    }

    /**
     * 从 API 搜索结果列表中精确定位最匹配的一项
     */
    protected function findBestMatchingPerformer(string $targetName, array $performers): ?array
    {
        if (empty($performers)) {
            return null;
        }

        $targetLower = mb_strtolower($targetName, 'UTF-8');

        // 1. 完全一致
        foreach ($performers as $p) {
            if (mb_strtolower($p['name'] ?? '', 'UTF-8') === $targetLower) {
                return $p;
            }
        }

        // 2. 检查 aliases 别名是否一致
        foreach ($performers as $p) {
            $aliases = $p['aliases'] ?? [];
            if (is_array($aliases)) {
                foreach ($aliases as $alias) {
                    if (mb_strtolower(trim($alias), 'UTF-8') === $targetLower) {
                        return $p;
                    }
                }
            }
        }

        // 3. 兜底返回第一个结果
        return $performers[0];
    }

    /**
     * 简单的请求间隔保护（通过 usleep 保证单进程下调用间隔 >= 800ms）
     */
    protected function throttleRequest(): void
    {
        try {
            $lastCallTime = Redis::get('tpdb:api:last_call_time');
            $now = microtime(true);
            if ($lastCallTime) {
                $elapsed = $now - (float) $lastCallTime;
                $minInterval = 0.8; // 800 毫秒间隔，约 75 次/分钟以内
                if ($elapsed < $minInterval) {
                    usleep((int) (($minInterval - $elapsed) * 1000000));
                }
            }
            Redis::setex('tpdb:api:last_call_time', 10, (string) microtime(true));
        } catch (Throwable) {
            // Redis 故障时降级固定休眠 500ms
            usleep(500000);
        }
    }
}

