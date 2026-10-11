<?php

namespace App\Services\Crawler;

use App\Models\Video;
use App\Models\VideoDownload;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function getTargetVideosQuery(?string $channel = null, ?int $videoId = null): Builder
    {
        $query = Video::query()
            ->with(['actors:id,name,slug', 'channel:id,name,slug', 'downloads']);

        if ($videoId) {
            return $query->where('id', $videoId);
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

        return $query->orderByDesc('id');
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
     * 发送网络请求获取 xxxclub 搜索结果页面 HTML
     */
    public function fetchSearchHtml(string $keyword, ?string $proxy = null, array $extraHeaders = []): array
    {
        $searchUrl = "{$this->baseUrl}/" . rawurlencode($keyword);

        $defaultHeaders = [
            'User-Agent'                => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
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

        $headers = array_merge($defaultHeaders, $extraHeaders);

        try {
            $client = Http::withHeaders($headers)->timeout(20);

            $resolvedProxy = $proxy ?: env('CRAWLER_PROXY') ?: env('HTTP_PROXY');
            if (!empty($resolvedProxy)) {
                $client = $client->withOptions(['proxy' => $resolvedProxy]);
            }

            $response = $client->get($searchUrl);
            $statusCode = $response->status();
            $body = $response->body();

            // 检查 Cloudflare 质询拦截
            if ($statusCode === 403 || str_contains($body, 'Just a moment...') || str_contains($body, 'cf-browser-verification')) {
                return [
                    'success'     => false,
                    'status_code' => $statusCode,
                    'url'         => $searchUrl,
                    'is_cf'       => true,
                    'message'     => '触发了 Cloudflare 防爬验证质询 (403/Challenge)，建议配置代理 --proxy 或通过 --file 加载已保存的 HTML。',
                    'html'        => $body,
                ];
            }

            if (!$response->successful()) {
                return [
                    'success'     => false,
                    'status_code' => $statusCode,
                    'url'         => $searchUrl,
                    'is_cf'       => false,
                    'message'     => "HTTP 请求失败，状态码: {$statusCode}",
                    'html'        => $body,
                ];
            }

            return [
                'success'     => true,
                'status_code' => $statusCode,
                'url'         => $searchUrl,
                'is_cf'       => false,
                'message'     => 'OK',
                'html'        => $body,
            ];
        } catch (Throwable $e) {
            Log::error("xxxclub 请求异常 [{$searchUrl}]: " . $e->getMessage());
            return [
                'success'     => false,
                'status_code' => 0,
                'url'         => $searchUrl,
                'is_cf'       => false,
                'message'     => '网络请求异常: ' . $e->getMessage(),
                'html'        => '',
            ];
        }
    }

    /**
     * 解析 HTML 中 class 为 tsearch 的种子列表并提取结构化数据
     */
    public function parseSearchHtml(string $html): array
    {
        if (empty(trim($html))) {
            return [];
        }

        // 抑制 HTML5 严格解析警告
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
            // 跳过蜜罐链接 (style 包含 left:-99999px)
            $realLink = null;
            $aNodes = $xpath->query('.//span[contains(@class, "toral")]/a', $node);
            foreach ($aNodes as $a) {
                $style = (string) $a->getAttribute('style');
                $href = (string) $a->getAttribute('href');
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
            $idAttr = (string) $realLink->getAttribute('id');
            if (!preg_match('/[a-f0-9]{40}/i', $idAttr, $hashMatch)) {
                continue;
            }
            $hash = strtolower($hashMatch[0]);

            // 3. 标题与净化
            $rawTitle = trim($realLink->textContent);
            // 去除末尾的 [XC] 或网站标记
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
            'total'    => count($items),
            'matched'  => 0,
            'inserted' => 0,
            'updated'  => 0,
            'skipped'  => 0,
        ];

        $matchedItems = [];
        foreach ($items as $item) {
            if ($this->isMatch($video, $item)) {
                $matchedItems[] = $item;
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

