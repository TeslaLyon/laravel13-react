<?php

namespace App\Services\Crawler;

use App\Models\Video;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CiligouCrawlerService
{
    /**
     * 磁力狗基础入口域名（门户网关）
     */
    protected string $baseUrl = 'https://ciligou.net';

    /**
     * 磁力狗实际生效的目标节点域名（经过网关跳转与验证后）
     */
    protected ?string $activeBaseUrl = null;

    /**
     * 磁力狗会话 Cookies（通过 challenge 验证后保留）
     */
    protected array $cookies = [];

    /**
     * 获取当前生效的基础域名
     */
    public function getActiveBaseUrl(): string
    {
        return $this->activeBaseUrl ?: $this->baseUrl;
    }

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
     * 构建多策略搜索候选词列表（按置信度优先级排列）
     * 策略 1：片名/日期后部全称（例如 Faye.Reagan.Eye.On.The.Balls、Sindy.Youre.All.Grown.Up.So.Lets.Fuck）
     * 策略 2：演员全名 + 英文发布日期（例如 Sindy.Lange 28.october.2011，常见于海外 RARBG / Webrip 种子命名格式）
     * 策略 3：首个演员代号 + 英文发布日期（例如 Sindy 28.october.2011）
     * 策略 4：厂牌频道名 + 英文发布日期（例如 MommyGotBoobs 28.october.2011）
     */
    public function buildSearchWords(Video $video): array
    {
        $words = [];
        $rawCode = trim($video->video_code ?? '');
        $dateInfo = $this->extractDateFromVideo($video);

        // 1. 优先从 video_code 截取日期后部（片名/标题全称）
        $primaryWord = $this->buildSearchWord($video);
        if (!empty($primaryWord)) {
            $words[] = $primaryWord;
        }

        // 2. 备选方案：英文日期组合方案（海外成人种子极常用格式：[演员/厂牌] [日].[英文月份].[完整年份]）
        if (!empty($dateInfo) && !empty($dateInfo['day_int']) && !empty($dateInfo['month_int']) && !empty($dateInfo['year'])) {
            $monthName = $this->englishMonthNames[$dateInfo['month_int']][0] ?? '';
            if (!empty($monthName)) {
                $day = $dateInfo['day_int'];
                $year = $dateInfo['year'];
                $datePart = "{$day}.{$monthName}.{$year}";

                // 2.1 从已关联演员构建（例如 Sindy Lange ➔ Sindy.Lange 28.october.2011）
                $actors = null;
                if ($video->relationLoaded('actors')) {
                    $actors = $video->actors;
                } elseif ($video->exists) {
                    try {
                        $actors = $video->actors()->get();
                    } catch (Throwable) {}
                }

                if ($actors && $actors->isNotEmpty()) {
                    foreach ($actors->take(2) as $actor) {
                        $cleanActor = preg_replace('/[_\s]+/', '.', trim($actor->name));
                        $cleanActor = preg_replace('/[^a-zA-Z0-9.]+/', '', $cleanActor);
                        $cleanActor = trim(preg_replace('/\.+/', '.', $cleanActor), '.');
                        if (!empty($cleanActor) && strlen($cleanActor) >= 3) {
                            $words[] = "{$cleanActor} {$datePart}";
                        }
                    }
                }

                // 2.2 从 video_code 截取首个演员代号（例如 Sindy.Youre... 中的 Sindy）
                if (!empty($rawCode) && preg_match('/(?:\.|\b)(?:20)?\d{2}[._-]\d{2}[._-]\d{2}[._-]([a-zA-Z0-9]+)/i', $rawCode, $am)) {
                    $firstToken = trim($am[1]);
                    if (strlen($firstToken) >= 3 && !in_array(strtolower($firstToken), ['the', 'and', 'part', 'scene'])) {
                        $codeActorWord = "{$firstToken} {$datePart}";
                        if (!in_array($codeActorWord, $words)) {
                            $words[] = $codeActorWord;
                        }
                    }
                }

                // 2.3 补充：厂牌 + 主要演员（例如 Deeper Violet Voss，解决搜索无果时的宽泛命中）
                $cleanChannel = null;
                if ($video->relationLoaded('channel') && $video->channel) {
                    $cleanChannel = preg_replace('/[^a-zA-Z0-9]+/', '', $video->channel->name ?: $video->channel->slug);
                } elseif (!empty($rawCode) && preg_match('/^([a-zA-Z0-9]+)[._-](?:20)?\d{2}[._-]\d{2}[._-]\d{2}/i', $rawCode, $cm)) {
                    $cleanChannel = preg_replace('/[^a-zA-Z0-9]+/', '', trim($cm[1]));
                }

                if (!empty($cleanChannel)) {
                    // 尝试从 video_code 提取前两位演员名
                    if (preg_match('/(?:\.|\b)(?:20)?\d{2}[._-]\d{2}[._-]\d{2}[._-]([a-zA-Z0-9]+)[._-]([a-zA-Z0-9]+)/i', $rawCode, $actm)) {
                        $chActWord = "{$cleanChannel} {$actm[1]} {$actm[2]}";
                        if (!in_array($chActWord, $words)) {
                            $words[] = $chActWord;
                        }
                    } elseif ($actors && $actors->isNotEmpty()) {
                        $firstActor = $actors->first();
                        $chActWord = "{$cleanChannel} {$firstActor->name}";
                        if (!in_array($chActWord, $words)) {
                            $words[] = $chActWord;
                        }
                    }

                    // 厂牌 + 英文发布日期 (如 MommyGotBoobs 28.october.2011)
                    $chWord = "{$cleanChannel} {$datePart}";
                    if (!in_array($chWord, $words)) {
                        $words[] = $chWord;
                    }
                }

                // 2.4 欧美发行时差容差：前一天日期（例如 8.october.2026）
                try {
                    $dtObj = Carbon::create((int) $dateInfo['year'], (int) $dateInfo['month_int'], (int) $dateInfo['day_int']);
                    $prevDt = $dtObj->subDay();
                    $prevMonthName = $this->englishMonthNames[$prevDt->month][0] ?? $monthName;
                    $prevDatePart = "{$prevDt->day}.{$prevMonthName}.{$prevDt->year}";

                    if (!empty($codeActorWord ?? null)) {
                        $prevActorWord = str_replace($datePart, $prevDatePart, $codeActorWord);
                        if (!in_array($prevActorWord, $words)) {
                            $words[] = $prevActorWord;
                        }
                    }
                } catch (Throwable) {}
            }
        }

        return array_values(array_unique(array_filter($words)));
    }

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

        // 1. 数字格式模式匹配（支持 YY.MM.DD, YY MM DD, YYYY.MM.DD, YYYY-MM-DD, DD.MM.YYYY, yymmdd 等）
        $numericPatterns = [
            "{$yy}.{$mPad}.{$dPad}",
            "{$yy} {$mPad} {$dPad}",
            "{$y}.{$mPad}.{$dPad}",
            "{$y} {$mPad} {$dPad}",
            "{$y}-{$mPad}-{$dPad}",
            "{$yy}-{$mPad}-{$dPad}",
            "{$dPad}.{$mPad}.{$y}",
            "{$dPad} {$mPad} {$y}",
            "{$dPad}.{$mPad}.{$yy}",
            "{$dPad} {$mPad} {$yy}",
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

        // 1.1 欧美发行时差容差：检查前一天与后一天的日期（解决时差造成的1天偏差）
        try {
            $dtObj = Carbon::create((int) $y, (int) $mInt, (int) $dInt);
            $adjDates = [
                $dtObj->copy()->subDay(),
                $dtObj->copy()->addDay(),
            ];
            foreach ($adjDates as $adj) {
                $adjYy = $adj->format('y');
                $adjY = (string) $adj->year;
                $adjM = $adj->format('m');
                $adjD = $adj->format('d');
                $adjPatterns = [
                    "{$adjYy}.{$adjM}.{$adjD}",
                    "{$adjYy} {$adjM} {$adjD}",
                    "{$adjY}.{$adjM}.{$adjD}",
                    "{$adjY} {$adjM} {$adjD}",
                    "{$adjY}-{$adjM}-{$adjD}",
                    "{$adjYy}-{$adjM}-{$adjD}",
                    "{$adjYy}{$adjM}{$adjD}",
                    "{$adjY}{$adjM}{$adjD}",
                ];
                foreach ($adjPatterns as $ap) {
                    if (str_contains($lower, strtolower($ap))) {
                        return true;
                    }
                }
            }
        } catch (Throwable) {}

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
        if (preg_match('/\b(\d{1,2})[._\s-](\d{1,2})[._\s-]((?:19|20)\d{2})\b/', $title, $m)) {
            $day = (int) $m[1];
            $month = (int) $m[2];
            $year = (int) $m[3];
            if (checkdate($month, $day, $year)) {
                return ['year' => $year, 'month' => $month, 'day' => $day];
            }
        }

        // 4. YY.MM.DD / YYYY.MM.DD / YYYY-MM-DD / YY MM DD
        if (preg_match('/\b(?:(19\d{2}|20\d{2})|(\d{2}))[._\s-](\d{2})[._\s-](\d{2})\b/', $title, $m)) {
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
     * 支持 ciligou.net 门户网关的 JavaScript 跳转解析与反爬验证页（Challenge Page）自动通行
     */
    public function fetchHtml(string $url, ?string $proxy = null): array
    {
        try {
            $options = [
                'headers' => [
                    'User-Agent'      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                    'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'Referer'         => $this->getActiveBaseUrl() . '/',
                ],
                'timeout'         => 15,
                'connect_timeout' => 8,
                'allow_redirects' => true,
            ];

            if (!empty($proxy)) {
                $options['proxy'] = $proxy;
            }

            // 使用 CookieJar 保持会话
            $jar = new CookieJar();
            if (!empty($this->cookies)) {
                $host = parse_url($this->getActiveBaseUrl(), PHP_URL_HOST);
                foreach ($this->cookies as $name => $val) {
                    $jar->setCookie(new SetCookie([
                        'Name'   => $name,
                        'Value'  => $val,
                        'Domain' => $host,
                        'Path'   => '/',
                    ]));
                }
            }
            $options['cookies'] = $jar;

            $client = new Client($options);

            // 1. 发起初始请求
            $targetUrl = $url;
            $response = $client->get($targetUrl);
            $html = (string) $response->getBody();

            // 2. 检测并处理 ciligou.net 门户网关的 JavaScript 跳转（Base64 或 location.href）
            $jumpUrl = null;
            if (preg_match('/window\.atob\([\'"]([A-Za-z0-9+\/=]+)[\'"]\)/', $html, $m)) {
                $decoded = urldecode(base64_decode($m[1]));
                if (preg_match('/location\.href\s*=\s*[\'"](https?:\/\/[^\'"]+)[\'"]/i', $decoded, $jm)) {
                    $jumpUrl = $jm[1];
                }
            } elseif (preg_match('/location\.href\s*=\s*[\'"](https?:\/\/[^\'"]+)[\'"]/i', $html, $jm)) {
                $jumpUrl = $jm[1];
            }

            if (!empty($jumpUrl)) {
                $parsedHost = parse_url($jumpUrl, PHP_URL_SCHEME) . '://' . parse_url($jumpUrl, PHP_URL_HOST);
                $this->activeBaseUrl = $parsedHost;
                $targetUrl = $jumpUrl;
                $response = $client->get($targetUrl);
                $html = (string) $response->getBody();
            }

            // 3. 检测并自动完成反爬验证页（Challenge Page: POST act=challenge）
            if (str_contains($html, 'act=challenge') || str_contains($html, 'challenge-c')) {
                Log::info("CiligouCrawlerService 触发验证页，自动提交 act=challenge 验证通过...");
                $client->post($targetUrl, [
                    'form_params' => ['act' => 'challenge'],
                ]);

                // 提取 Set-Cookie 保留复用
                foreach ($jar->toArray() as $c) {
                    if (!empty($c['Name']) && isset($c['Value'])) {
                        $this->cookies[$c['Name']] = $c['Value'];
                    }
                }

                // 重新拉取目标页面
                $response = $client->get($targetUrl);
                $html = (string) $response->getBody();
            }

            return [
                'success' => true,
                'html'    => $html,
                'status'  => $response->getStatusCode(),
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
        // 1. 构建多策略搜索候选词列表（按优先级排列尝试）
        $searchWords = $this->buildSearchWords($video);
        if (empty($searchWords)) {
            return [];
        }

        // 2. 提取视频的发布日期信息用于比对
        $dateInfo = $this->extractDateFromVideo($video);

        // 3. 逐个策略词尝试搜索，首个命中且成功匹配后即刻返回
        foreach ($searchWords as $idx => $word) {
            $searchUrl = $this->buildSearchUrl($word);
            $fetchRes = $this->fetchHtml($searchUrl, $proxy);

            if (!$fetchRes['success'] || empty($fetchRes['html'])) {
                continue;
            }

            // 4. 解析搜索结果列表
            $candidates = $this->parseSearchHtml($fetchRes['html']);
            if (empty($candidates)) {
                continue;
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

            if (!empty($matchedItems)) {
                return $matchedItems;
            }
        }

        return [];
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
                // 排除年、月、日等日期词，提取搜索词中的非日期内容特征词（如演员、厂牌、片名）
                $dateTokens = [
                    strtolower((string) ($dateInfo['year'] ?? '')),
                    strtolower((string) ($dateInfo['short_year'] ?? '')),
                    strtolower((string) ($dateInfo['month'] ?? '')),
                    strtolower((string) ($dateInfo['day'] ?? '')),
                    (string) ($dateInfo['day_int'] ?? ''),
                    (string) ($dateInfo['month_int'] ?? ''),
                ];
                if (isset($this->englishMonthNames[$dateInfo['month_int']])) {
                    foreach ($this->englishMonthNames[$dateInfo['month_int']] as $mn) {
                        $dateTokens[] = strtolower($mn);
                    }
                }

                $allTokens = array_filter(preg_split('/[^a-zA-Z0-9]+/', $searchWord), fn($w) => strlen($w) >= 3);
                $contentTokens = array_filter($allTokens, fn($w) => !in_array(strtolower($w), $dateTokens));

                // 如果搜索词中含有非日期特征词（如演员/厂牌/片名），要求种子标题中至少命中 1 个，防止串入同天上映的其他演员作品
                if (!empty($contentTokens)) {
                    $matchedCount = 0;
                    foreach ($contentTokens as $ct) {
                        if (str_contains(strtolower($torrentTitle), strtolower($ct))) {
                            $matchedCount++;
                        }
                    }
                    if ($matchedCount > 0) {
                        return true;
                    }
                } else {
                    return true;
                }
            }

            // B. 检查种子标题中是否包含显式的“冲突日期”
            $titleDate = $this->extractTitleDate($torrentTitle);
            if ($titleDate !== null) {
                $isExactDate = ($titleDate['year'] == (int) $dateInfo['year']
                    && $titleDate['month'] == (int) $dateInfo['month_int']
                    && $titleDate['day'] == (int) $dateInfo['day_int']);

                if (!$isExactDate) {
                    // 判断是否为欧美时差引起的 +-1 天微小偏差
                    $diffDays = 999;
                    try {
                        $vDt = Carbon::create((int) $dateInfo['year'], (int) $dateInfo['month_int'], (int) $dateInfo['day_int']);
                        $tDt = Carbon::create((int) $titleDate['year'], (int) $titleDate['month'], (int) $titleDate['day']);
                        $diffDays = abs($vDt->diffInDays($tDt));
                    } catch (Throwable) {}

                    if ($diffDays === 1) {
                        // 偏差 1 天：检查内容词元重合度，若核心词高度重合（如 Deeper + Violet Voss + Absolution），确认为同一部片
                        $checkWords = [];
                        if (!empty($video->video_code)) {
                            $checkWords = array_values(array_filter(explode('.', preg_replace('/[_\s-]+/', '.', (string) $video->video_code)), fn($w) => strlen($w) >= 3 && !preg_match('/^\d+$/', $w)));
                        }
                        if (empty($checkWords) && !empty($video->name)) {
                            $checkWords = array_values(array_filter(explode(' ', preg_replace('/[^a-zA-Z0-9]+/', ' ', (string) $video->name)), fn($w) => strlen($w) >= 3));
                        }

                        $matchedWords = 0;
                        foreach ($checkWords as $cw) {
                            if (str_contains(strtolower($torrentTitle), strtolower($cw))) {
                                $matchedWords++;
                            }
                        }

                        $ratio = !empty($checkWords) ? $matchedWords / count($checkWords) : 0;
                        if ($matchedWords >= 2 && $ratio >= 0.5) {
                            return true;
                        }
                    }

                    // 确属明确冲突的不同日期（大于1天差异），排除！
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

        return rtrim($this->getActiveBaseUrl(), '/') . '/' . ltrim($path, '/');
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

