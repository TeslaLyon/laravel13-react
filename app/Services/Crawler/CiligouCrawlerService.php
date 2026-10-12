<?php

namespace App\Services\Crawler;

use App\Models\Video;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CiligouCrawlerService
{
    /**
     * 磁力狗基础域名与搜索端点
     */
    protected string $baseUrl = 'http://clg88.me';

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
     * 英文月份名称映射表（用于精准匹配类似 "August 27, 2010" 格式）
     */
    protected array $englishMonthNames = [
        1  => ['january', 'jan'],
        2  => ['february', 'feb'],
        3  => ['march', 'mar'],
        4  => ['april', 'apr'],
        5  => ['may'],
        6  => ['june', 'jun'],
        7  => ['july', 'jul'],
        8  => ['august', 'aug'],
        9  => ['september', 'sep', 'sept'],
        10 => ['october', 'oct'],
        11 => ['november', 'nov'],
        12 => ['december', 'dec'],
    ];

    /**
     * 从视频提取用于磁力狗搜索的关键词
     * 规则：优先提取 video_code 中日期后边的文字内容（例如 Faye.Reagan.Eye.On.The.Balls）
     */
    public function buildSearchWord(Video $video): ?string
    {
        $rawCode = trim($video->video_code ?? '');

        // 1. 优先从 video_code 截取日期后部（如 BigTitsInSports.10.08.27.Faye.Reagan.Eye.On.The.Balls）
        if (!empty($rawCode)) {
            // 匹配 .YY.MM.DD. 或 _YY_MM_DD_ 或 -YY-MM-DD- 或带 20YY 形式
            if (preg_match('/(?:\.|\b)(?:20)?\d{2}[._-]\d{2}[._-]\d{2}[._-](.+)$/i', $rawCode, $matches)) {
                $afterDate = trim($matches[1]);
                $clean = preg_replace('/[_\s]+/', '.', $afterDate);
                $clean = preg_replace('/[^a-zA-Z0-9.]+/', '', $clean);
                $clean = trim(preg_replace('/\.+/', '.', $clean), '.');
                if (!empty($clean) && strlen($clean) >= 3) {
                    return $clean;
                }
            }
        }

        // 2. 备选方案：通过 [演员名] + [片名] 组合
        $parts = [];
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
                return $combined;
            }
        }

        return null;
    }

    /**
     * 将搜索关键词进行 Base64 编码并去掉末尾的 '=' 填充符
     * 示例：Faye.Reagan.Eye.On.The.Balls => RmF5ZS5SZWFnYW4uRXllLk9uLlRoZS5CYWxscw
     */
    public function encodeSearchWord(string $word): string
    {
        return rtrim(base64_encode($word), '=');
    }

    /**
     * 构建完整的磁力狗搜索 URL
     */
    public function buildSearchUrl(string $word): string
    {
        $encoded = $this->encodeSearchWord($word);
        return "{$this->baseUrl}/search?word={$encoded}";
    }

    /**
     * 从视频元数据提取结构化日期信息（年、月、日、两位年）
     */
    public function extractDateFromVideo(Video $video): ?array
    {
        // 1. 优先从 video_code 中提取（如 10.08.27 或 26.10.08）
        $rawCode = trim($video->video_code ?? '');
        if (!empty($rawCode) && preg_match('/(?:\.|\b)(?:20)?(\d{2})[._-](\d{2})[._-](\d{2})(?:\.|\b)/i', $rawCode, $matches)) {
            $shortYear = $matches[1];
            $month = (int) $matches[2];
            $day = (int) $matches[3];
            $fullYear = (int) $shortYear > 70 ? 1900 + (int) $shortYear : 2000 + (int) $shortYear;

            if (checkdate($month, $day, $fullYear)) {
                return [
                    'year'       => (string) $fullYear,
                    'short_year' => $shortYear,
                    'month'      => sprintf('%02d', $month),
                    'month_int'  => $month,
                    'day'        => sprintf('%02d', $day),
                    'day_int'    => $day,
                ];
            }
        }

        // 2. 从 release_at 提取
        if (!empty($video->release_at)) {
            try {
                $dt = Carbon::parse($video->release_at);
                return [
                    'year'       => (string) $dt->year,
                    'short_year' => $dt->format('y'),
                    'month'      => $dt->format('m'),
                    'month_int'  => $dt->month,
                    'day'        => $dt->format('d'),
                    'day_int'    => $dt->day,
                ];
            } catch (Throwable) {}
        }

        return null;
    }

    /**
     * 精准判断搜索结果标题中的日期是否与视频的日期相匹配
     */
    public function matchDateInTitle(string $title, ?array $dateInfo): bool
    {
        if (empty($dateInfo)) {
            return false;
        }

        $lower = strtolower($title);
        $y = $dateInfo['year'];
        $yy = $dateInfo['short_year'];
        $mPad = $dateInfo['month'];
        $mInt = $dateInfo['month_int'];
        $dPad = $dateInfo['day'];
        $dInt = $dateInfo['day_int'];

        // 1. 数字格式模式匹配（支持 YY.MM.DD, YYYY.MM.DD, YYYY-MM-DD, DD.MM.YYYY, yymmdd 等）
        $numericPatterns = [
            "{$yy}.{$mPad}.{$dPad}",
            "{$y}.{$mPad}.{$dPad}",
            "{$y}-{$mPad}-{$dPad}",
            "{$yy}-{$mPad}-{$dPad}",
            "{$dPad}.{$mPad}.{$y}",
            "{$dPad}.{$mPad}.{$yy}",
            "{$dPad}-{$mPad}-{$y}",
            "{$dPad}-{$mPad}-{$yy}",
            "{$yy}{$mPad}{$dPad}",
            "{$y}{$mPad}{$dPad}",
        ];

        foreach ($numericPatterns as $p) {
            if (str_contains($lower, strtolower($p))) {
                return true;
            }
        }

        // 2. 英文月份格式匹配（例如 "August 27, 2010"、"27 August 2010"、"Aug 27, 2010" 等）
        $monthWords = $this->englishMonthNames[$mInt] ?? [];
        $hasMonth = false;
        foreach ($monthWords as $mw) {
            if (preg_match('/\b' . preg_quote($mw, '/') . '\b/i', $lower)) {
                $hasMonth = true;
                break;
            }
        }

        if ($hasMonth) {
            $hasDay = preg_match('/\b0?' . $dInt . '\b/', $lower);
            $hasYear = preg_match('/\b' . $y . '\b/', $lower) || preg_match('/\b' . $yy . '\b/', $lower);
            if ($hasDay && $hasYear) {
                return true;
            }
        }

        return false;
    }

    /**
     * 从种子标题中提取显式的发布日期信息（年、月、日）
     * 支持多种常见格式：
     * 1. 英文月份: "August 27, 2010", "27th August 2010"
     * 2. 国际标准与欧洲数字: YYYY-MM-DD, YYYY.MM.DD, DD.MM.YYYY
     * 3. 常见缩写数字: YY.MM.DD, YY-MM-DD, YY_MM_DD
     */
    public function extractTitleDate(string $title): ?array
    {
        $monthMap = [];
        foreach ($this->englishMonthNames as $mInt => $names) {
            foreach ($names as $n) {
                $monthMap[$n] = $mInt;
            }
        }
        $monthRegex = implode('|', array_keys($monthMap));

        // 1. "August 27, 2010" 或 "August 27th, 2010"
        if (preg_match('/\b(' . $monthRegex . ')\s+(\d{1,2})(?:st|nd|rd|th)?,?\s+((?:19|20)\d{2})\b/i', $title, $m)) {
            return ['year' => (int) $m[3], 'month' => $monthMap[strtolower($m[1])], 'day' => (int) $m[2]];
        }

        // 2. "27 August 2010" 或 "27th August 2010"
        if (preg_match('/\b(\d{1,2})(?:st|nd|rd|th)?\s+(' . $monthRegex . '),?\s+((?:19|20)\d{2})\b/i', $title, $m)) {
            return ['year' => (int) $m[3], 'month' => $monthMap[strtolower($m[2])], 'day' => (int) $m[1]];
        }

        // 3. DD.MM.YYYY (欧洲格式)
        if (preg_match('/\b(\d{1,2})[._-](\d{1,2})[._-]((?:19|20)\d{2})\b/', $title, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return ['year' => $year, 'month' => $month, 'day' => $day];
            }
        }

        // 4. YY.MM.DD / YYYY.MM.DD / YYYY-MM-DD
        if (preg_match('/\b(?:(19\d{2}|20\d{2})|(\d{2}))[._-](\d{2})[._-](\d{2})\b/', $title, $m)) {
            $year = !empty($m[1]) ? (int) $m[1] : (int) $m[2] + ((int) $m[2] > 70 ? 1900 : 2000);
            $month = (int) $m[3];
            $day = (int) $m[4];
            if (checkdate($month, $day, $year)) {
                return ['year' => $year, 'month' => $month, 'day' => $day];
            }
        }

        return null;
    }

    /**
     * 发送网络请求抓取磁力狗页面 HTML
     */
    public function fetchHtml(string $url, ?string $proxy = null): array
    {
        try {
            $req = Http::timeout(12)
                ->connectTimeout(6)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'Referer'         => $this->baseUrl . '/',
                ]);

            if (!empty($proxy)) {
                $req = $req->withOptions(['proxy' => $proxy]);
            }

            $response = $req->get($url);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'html'    => $response->body(),
                    'status'  => $response->status(),
                ];
            }

            return [
                'success' => false,
                'html'    => '',
                'status'  => $response->status(),
                'message' => "HTTP 请求失败，状态码: {$response->status()}",
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'html'    => '',
                'status'  => 0,
                'message' => "网络连接异常: {$e->getMessage()}",
            ];
        }
    }

    /**
     * 解析磁力狗搜索结果页面 HTML（提取列表项：标题、详情页URL、大小、日期、格式）
     * 优先使用 PHP 8.4 原生 \Dom\HTMLDocument (Lexbor C 引擎)，兼容老版本 DOMDocument
     */
    public function parseSearchHtml(string $html): array
    {
        if (empty(trim($html))) {
            return [];
        }

        // 优先使用 PHP 8.4 原生 HTML5 解析引擎
        if (class_exists(\Dom\HTMLDocument::class)) {
            return $this->parseSearchHtmlWithDomHtmlDocument($html);
        }

        return $this->parseSearchHtmlWithClassicDom($html);
    }

    /**
     * PHP 8.4 原生 Lexbor 引擎解析器
     */
    protected function parseSearchHtmlWithDomHtmlDocument(string $html): array
    {
        try {
            $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
            $liElements = $doc->querySelectorAll('#Search_list_wrapper > li');

            $results = [];
            foreach ($liElements as $li) {
                $a = $li->querySelector('a.SearchListTitle_result_title');
                if (!$a) {
                    continue;
                }

                $title = trim($a->textContent);
                $detailPath = $a->getAttribute('href');

                $infoEl = $li->querySelector('.Search_list_info');
                $infoText = $infoEl ? $infoEl->textContent : '';

                $sizeStr = '';
                if (preg_match('/文件大小[：:]\s*([0-9.]+\s*[KMGT]?B)/iu', $infoText, $sm)) {
                    $sizeStr = trim($sm[1]);
                }

                $dateStr = '';
                if (preg_match('/创建时间[：:]\s*([0-9-]+)/iu', $infoText, $dm)) {
                    $dateStr = trim($dm[1]);
                }

                $results[] = [
                    'title'      => $title,
                    'detail_url' => $this->normalizeDetailUrl($detailPath),
                    'size_str'   => $sizeStr,
                    'date_str'   => $dateStr,
                ];
            }

            return $results;
        } catch (Throwable $e) {
            Log::warning("CiligouCrawlerService PHP 8.4 Lexbor 解析失败: {$e->getMessage()}，回退经典解析");
            return $this->parseSearchHtmlWithClassicDom($html);
        }
    }

    /**
     * 经典 DOMDocument + XPath 解析器
     */
    protected function parseSearchHtmlWithClassicDom(string $html): array
    {
        $internalErrors = libxml_use_internal_errors(true);
        $doc = new DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query("//ul[@id='Search_list_wrapper']/li");

        if (!$nodes || $nodes->length === 0) {
            return [];
        }

        $results = [];
        foreach ($nodes as $li) {
            $aNode = $xpath->query(".//a[contains(@class, 'SearchListTitle_result_title')]", $li)->item(0);
            if (!$aNode instanceof DOMElement) {
                continue;
            }

            $title = trim($aNode->textContent);
            $detailPath = $aNode->getAttribute('href');

            $infoNode = $xpath->query(".//div[contains(@class, 'Search_list_info')]", $li)->item(0);
            $infoText = $infoNode ? $infoNode->textContent : '';

            $sizeStr = '';
            if (preg_match('/文件大小[：:]\s*([0-9.]+\s*[KMGT]?B)/iu', $infoText, $sm)) {
                $sizeStr = trim($sm[1]);
            }

            $dateStr = '';
            if (preg_match('/创建时间[：:]\s*([0-9-]+)/iu', $infoText, $dm)) {
                $dateStr = trim($dm[1]);
            }

            $results[] = [
                'title'      => $title,
                'detail_url' => $this->normalizeDetailUrl($detailPath),
                'size_str'   => $sizeStr,
                'date_str'   => $dateStr,
            ];
        }

        return $results;
    }

    /**
     * 抓取磁力狗详情页并提取真实的磁力链接与哈希
     */
    public function fetchDetailMagnet(string $detailUrl, ?string $proxy = null): ?array
    {
        $res = $this->fetchHtml($detailUrl, $proxy);
        if (!$res['success'] || empty($res['html'])) {
            return null;
        }

        $html = $res['html'];

        // 提取 magnet:?xt=urn:btih:... 链接
        if (preg_match('/magnet:\?xt=urn:btih:([a-fA-F0-9]{32,40})/i', $html, $matches)) {
            $rawHash = $matches[1];
            $cleanHash = strtoupper($rawHash);

            // 提取文件大小（如果详情页更精准）
            $fileSize = null;
            $sizeStr = '';
            if (preg_match('/文件大小[：:]\s*<b>\s*([0-9.]+\s*[KMGT]?B)\s*<\/b>/iu', $html, $sm)) {
                $sizeStr = trim($sm[1]);
                $fileSize = $this->parseSizeToBytes($sizeStr);
            }

            // 提取详情标题
            $title = '';
            if (preg_match('/<h1[^>]*class=["\'][^"\']*Information_title[^"\']*["\'][^>]*>(.*?)<\/h1>/isu', $html, $tm)) {
                $title = trim(strip_tags($tm[1]));
            }

            return [
                'hash'        => $cleanHash,
                'magnet_link' => "magnet:?xt=urn:btih:{$cleanHash}",
                'file_size'   => $fileSize,
                'size_str'    => $sizeStr,
                'title'       => $title,
            ];
        }

        return null;
    }

    /**
     * 核心门面方法：一站式检索、日期精准比对、详情解析并返回标准化待入库下载项
     */
    public function searchAndMatch(Video $video, ?string $proxy = null): array
    {
        // 1. 构建搜索关键词
        $word = $this->buildSearchWord($video);
        if (empty($word)) {
            return [];
        }

        // 2. 提取视频的发布日期信息用于比对
        $dateInfo = $this->extractDateFromVideo($video);

        // 3. 构造搜索 URL 并请求 HTML
        $searchUrl = $this->buildSearchUrl($word);
        $fetchRes = $this->fetchHtml($searchUrl, $proxy);

        if (!$fetchRes['success'] || empty($fetchRes['html'])) {
            return [];
        }

        // 4. 解析搜索结果列表
        $candidates = $this->parseSearchHtml($fetchRes['html']);
        if (empty($candidates)) {
            return [];
        }

        // 5. 遍历候选列表，精准比对
        $matchedItems = [];
        foreach ($candidates as $cand) {
            $title = $cand['title'];

            // 核心匹配：精准比对种子标题与目标视频（支持日期吻合、排除冲突日期、关键词/词元高置信度匹配）
            $isMatched = $this->isCandidateMatch($video, $title, $word, $dateInfo);

            if (!$isMatched) {
                continue;
            }

            // 6. 匹配成功，请求详情页拉取真实的 magnet 磁链与 Hash
            $detailUrl = $cand['detail_url'];
            if (empty($detailUrl)) {
                continue;
            }

            $detailRes = $this->fetchDetailMagnet($detailUrl, $proxy);
            if (empty($detailRes) || empty($detailRes['hash'])) {
                continue;
            }

            $hash = $detailRes['hash'];
            $cleanTitle = !empty($detailRes['title']) ? $detailRes['title'] : $title;
            $sizeStr = !empty($detailRes['size_str']) ? $detailRes['size_str'] : $cand['size_str'];
            $fileSizeBytes = !empty($detailRes['file_size']) ? $detailRes['file_size'] : $this->parseSizeToBytes($sizeStr);

            $resInfo = $this->detectResolution($cleanTitle, $fileSizeBytes);

            // 组装标准 Magnet 链接（附加公网 Trackers）
            $magnetWithTrackers = $this->buildMagnetLink($hash, $cleanTitle);

            $matchedItems[] = [
                'source'      => 'ciligou',
                'hash'        => $hash,
                'title'       => $cleanTitle,
                'cat'         => 'XXX',
                'resolution'  => $resInfo['resolution'],
                'sort_order'  => $resInfo['sort_order'],
                'size_str'    => $sizeStr,
                'file_size'   => $fileSizeBytes,
                'date_str'    => $cand['date_str'] ?? '',
                'seeders'     => 1,
                'leechers'    => 0,
                'uploader'    => 'ciligou',
                'magnet_link' => $magnetWithTrackers,
            ];
        }

        return $matchedItems;
    }

    /**
     * 智能判定搜索结果中的种子是否与目标视频相匹配
     * 支持两层逻辑：
     * 1. 日期匹配层：若种子标题包含日期，必须与视频日期严格一致；若包含冲突的不同日期，则坚决排除；
     * 2. 内容匹配层：若种子标题无日期（或视频无日期），通过关键词完全子串、词元重合率(>=70%)或演员片名匹配。
     */
    public function isCandidateMatch(Video $video, string $torrentTitle, string $searchWord, ?array $dateInfo): bool
    {
        $normalize = fn($s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $s));
        $titleNorm = $normalize($torrentTitle);
        $wordNorm = $normalize($searchWord);

        // 1. 如果视频具备日期信息
        if (!empty($dateInfo)) {
            // A. 标题中的日期与视频日期直接匹配
            if ($this->matchDateInTitle($torrentTitle, $dateInfo)) {
                // 如果搜索词非空，进一步确保不是同天上映的其他完全无关影片
                if (strlen($wordNorm) >= 4) {
                    if (str_contains($titleNorm, $wordNorm) || str_contains($wordNorm, $titleNorm)) {
                        return true;
                    }
                    $tokens = array_filter(preg_split('/[^a-zA-Z0-9]+/', $searchWord), fn($w) => strlen($w) >= 3);
                    if (!empty($tokens)) {
                        $matchedCount = 0;
                        foreach ($tokens as $tk) {
                            if (str_contains(strtolower($torrentTitle), strtolower($tk))) {
                                $matchedCount++;
                            }
                        }
                        if (($matchedCount / count($tokens)) >= 0.3) {
                            return true;
                        }
                    }
                }
                return true;
            }

            // B. 检查种子标题中是否包含显式的“冲突日期”
            $titleDate = $this->extractTitleDate($torrentTitle);
            if ($titleDate !== null) {
                if ($titleDate['year'] != (int) $dateInfo['year']
                    || $titleDate['month'] != (int) $dateInfo['month_int']
                    || $titleDate['day'] != (int) $dateInfo['day_int']) {
                    // 种子标题含有明确的不同日期，属于同演员或频道的其他期视频，排除！
                    return false;
                }
            }
        }

        // 2. 种子标题无日期（如 "DoctorAdventures - Nikki Sexx - You Cant Leave Til You Nut On My Beav"）
        //    或目标视频无日期：依据搜索关键词与标题内容进行高置信度比对
        if (strlen($wordNorm) >= 6) {
            // A. 直接子串完全包含
            if (str_contains($titleNorm, $wordNorm) || str_contains($wordNorm, $titleNorm)) {
                return true;
            }

            // B. 词元匹配度 (Token Overlap >= 70%)
            $tokens = array_filter(preg_split('/[^a-zA-Z0-9]+/', $searchWord), fn($w) => strlen($w) >= 3);
            if (!empty($tokens)) {
                $matchedTokens = 0;
                foreach ($tokens as $tk) {
                    if (str_contains(strtolower($torrentTitle), strtolower($tk))) {
                        $matchedTokens++;
                    }
                }
                $ratio = $matchedTokens / count($tokens);
                if ($ratio >= 0.70 && $matchedTokens >= 2) {
                    return true;
                }
            }
        }

        // 3. 兜底方案：通过已关联演员与片名组合校验
        return $this->isTitleActorsMatch($video, $torrentTitle);
    }

    /**
     * 辅助匹配：备选标题与演员特征相似度比对
     */
    public function isTitleActorsMatch(Video $video, string $torrentTitle): bool
    {
        $normalize = fn($s) => strtolower(preg_replace('/[^a-zA-Z0-9]/', '', (string) $s));
        $titleNorm = $normalize($torrentTitle);

        $hasActor = false;
        $actors = null;
        if ($video->relationLoaded('actors')) {
            $actors = $video->actors;
        } elseif ($video->exists) {
            try {
                $actors = $video->actors()->get();
            } catch (Throwable) {}
        }

        if ($actors && $actors->isNotEmpty()) {
            foreach ($actors as $actor) {
                $aNorm = $normalize($actor->name);
                if (strlen($aNorm) >= 4 && str_contains($titleNorm, $aNorm)) {
                    $hasActor = true;
                    break;
                }
            }
        }

        $rawName = $video->name ?: $video->slug;
        $hasName = false;
        if (!empty($rawName)) {
            $nNorm = $normalize($rawName);
            if (strlen($nNorm) >= 4 && str_contains($titleNorm, $nNorm)) {
                $hasName = true;
            }
        }

        return $hasActor && $hasName;
    }

    /**
     * 清晰度检测与排序权重
     */
    public function detectResolution(string $title, ?int $fileSize = null): array
    {
        $upper = strtoupper($title);

        if (preg_match('/\b(2160P|4K|UHD)\b/i', $upper)) {
            return ['resolution' => '4K', 'sort_order' => 100];
        }
        if (preg_match('/\b(1080P|FHD|FULLHD)\b/i', $upper)) {
            return ['resolution' => '1080P', 'sort_order' => 80];
        }
        if (preg_match('/\b(720P|HD)\b/i', $upper)) {
            return ['resolution' => '720P', 'sort_order' => 60];
        }

        // 文件大小估算：> 3.5GB 倾向于 1080p，< 1GB 倾向于 720p/SD
        if ($fileSize !== null) {
            if ($fileSize >= 3.5 * 1024 * 1024 * 1024) {
                return ['resolution' => '1080P', 'sort_order' => 75];
            }
            if ($fileSize >= 1024 * 1024 * 1024) {
                return ['resolution' => '720P', 'sort_order' => 55];
            }
        }

        return ['resolution' => 'HD', 'sort_order' => 50];
    }

    /**
     * 将人类易读的文件大小字符串转换为字节数
     */
    public function parseSizeToBytes(string $sizeStr): int
    {
        if (preg_match('/([0-9.]+)\s*([KMGT]?B)/i', $sizeStr, $matches)) {
            $num = (float) $matches[1];
            $unit = strtoupper($matches[2]);

            return match ($unit) {
                'TB'    => (int) ($num * 1024 * 1024 * 1024 * 1024),
                'GB'    => (int) ($num * 1024 * 1024 * 1024),
                'MB'    => (int) ($num * 1024 * 1024),
                'KB'    => (int) ($num * 1024),
                default => (int) $num,
            };
        }

        return 0;
    }

    /**
     * 规范化详情页 URL
     */
    protected function normalizeDetailUrl(string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    /**
     * 组装带 Trackers 的标准磁链
     */
    public function buildMagnetLink(string $hash, string $title): string
    {
        $params = [
            'xt' => "urn:btih:{$hash}",
            'dn' => $title,
        ];

        $trackerQuery = '';
        foreach ($this->publicTrackers as $tr) {
            $trackerQuery .= '&tr=' . urlencode($tr);
        }

        return 'magnet:?' . http_build_query($params) . $trackerQuery;
    }
}

