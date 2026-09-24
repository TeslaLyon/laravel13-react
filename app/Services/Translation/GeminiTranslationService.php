<?php

namespace App\Services\Translation;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GeminiTranslationService
{
    protected ?string $apiKey;
    protected string $model;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.api_key');
        $this->model = config('services.gemini.model', 'gemini-3.6-flash');
        $this->baseUrl = rtrim(config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
    }

    /**
     * 检查 Gemini API Key 是否已配置
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * 批量翻译视频标题为简体中文
     *
     * @param array<int|string, string> $items [ id => 原始标题, ... ]
     * @return array<int|string, string> [ id => 翻译后的中文标题, ... ]
     */
    public function translateBatch(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        if (!$this->isConfigured()) {
            Log::warning('[GeminiTranslationService] GEMINI_API_KEY 未配置，跳过翻译');
            return [];
        }

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent";

        $prompt = <<<PROMPT
你是一位精通全球成人影视文化、经验丰富的成人娱乐平台资深中文编辑与影视汉化专家。
你的核心任务是将以下外语（欧美/日韩等）成人视频标题，翻译并润色为地道、香艳抓人、富有性张力、符合华人老司机与成人平台浏览习惯的高质量简体中文标题。

【翻译与润色准则】
1. **风格与语境（拒绝生硬机翻）**：
   - 标题需富有吸引力、情节感与刺激感，精准提炼视频的性感情境与兴奋点。
   - 杜绝字面死板直译！例如："A Family Affair" 意译为 "家庭背德秘事/禁忌情事"（而非"家庭事务"）；"Cheating Wife" 意译为 "绿帽出轨人妻/少妇私会"；"After Hours" 意译为 "加班后的夜间激情"。
2. **成人行业专业行话本地化**：
   - MILF / Mature -> 熟女 / 风骚人妻 / 极品美妇
   - Step-mom / Step-sister -> 继母 / 干姐姐 / 义妹
   - POV -> 第一视角 / 男友主视角
   - Creampie -> 中出 / 内射
   - Threesome / FFM / MMF -> 3P / 双飞 / 三人行
   - Deepthroat / Blowjob -> 绝顶深喉 / 销魂口交
   - Squirting -> 潮吹喷水 / 绝顶高潮
   - Public / Outdoor -> 户外露出 / 野外偷欢
   - Taboo / Affair -> 禁忌沉沦 / 偷情出轨
   - Casting / Audition -> 试镜潜规则 / 私拍调教
   - Maid / Nurse / Teacher / Babysitter -> 女仆 / 俏护士 / 教师 / 保姆
3. **专有名词与标识保留**：
   - 知名演员/模特姓名（如 Angela White, Demi Sutra 等）可直接保留原英文名或置于译名后，方便用户精准搜片。
   - 厂牌/系列名（如 Brazzers, Naughty America, Blacked, Tushy, Fake Taxi 等）保留英文原名。
   - 分辨率与特殊标识（如 4K, 8K, VR, 60FPS 等）保持原样。
4. **输出格式**：
   - 严格且仅输出标准 JSON 格式，其中键为视频 ID（字符串），值为润色翻译后的简体中文标题。

待翻译视频列表 (JSON):
PROMPT;

        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [
                        [
                            'text' => $prompt . "\n" . json_encode($items, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
                        ],
                    ],
                ],
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'temperature' => 0.4,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_NONE',
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE',
                ],
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE',
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_NONE',
                ],
            ],
        ];

        // 构造候选模型队列：优先尝试用户配置的模型，失败时依次自动降级回退
        $modelsToTry = array_values(array_unique(array_filter([
            $this->model,
            'gemini-3.5-flash-lite',
            'gemini-3.1-flash-lite',
            'gemini-3.8-flash',
            'gemini-3.7-flash',
            'gemini-3.6-flash',
            'gemini-3.5-flash',
        ])));

        foreach ($modelsToTry as $model) {
            $url = "{$this->baseUrl}/models/{$model}:generateContent";
            $maxRetries = 2;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $response = Http::withHeaders([
                        'x-goog-api-key' => $this->apiKey,
                        'Content-Type' => 'application/json',
                    ])
                        ->timeout(45)
                        ->post($url, $payload);

                    // 1. 成功返回
                    if ($response->successful()) {
                        $data = $response->json();
                        $rawText = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

                        if (empty($rawText)) {
                            Log::warning('[GeminiTranslationService] API 返回中未提取到有效文本', ['response' => $data]);
                            break;
                        }

                        $translations = json_decode($rawText, true);
                        if (!is_array($translations)) {
                            Log::error('[GeminiTranslationService] JSON 解析失败: ' . $rawText);
                            break;
                        }

                        // 若成功使用的不是初始配置的模型，记录提示并更新实例模型
                        if ($model !== $this->model) {
                            Log::info("[GeminiTranslationService] 原配置模型 [{$this->model}] 繁忙或配额耗尽，已自动切换为可用模型 [{$model}]。");
                            $this->model = $model;
                        }

                        // 保持与原数组键类型一致（映射回传入的 id）
                        $result = [];
                        foreach ($items as $id => $originalTitle) {
                            $stringId = (string) $id;
                            if (isset($translations[$stringId]) && filled($translations[$stringId])) {
                                $result[$id] = trim((string) $translations[$stringId]);
                            }
                        }

                        return $result;
                    }

                    $status = $response->status();

                    // 2. HTTP 503 (模型临时高并发繁忙 / High demand)
                    if ($status === 503) {
                        if ($attempt < $maxRetries) {
                            Log::warning("[GeminiTranslationService] 模型 [{$model}] 暂时高负载繁忙 (HTTP 503)，等待 2 秒后进行第 {$attempt} 次重试...");
                            sleep(2);
                            continue;
                        } else {
                            Log::warning("[GeminiTranslationService] 模型 [{$model}] 持续繁忙 (HTTP 503)，自动切换尝试备选模型...");
                            break; // 跳出本模型重试，尝试下一个候选模型
                        }
                    }

                    // 3. HTTP 404 (模型已下线或不存在)
                    if ($status === 404) {
                        Log::warning("[GeminiTranslationService] 模型 [{$model}] 已下线或不存在 (HTTP 404)，正在自动切换尝试备选模型...");
                        break; // 无需重试当前模型，直接尝试下一个候选模型
                    }

                    // 4. HTTP 429 (频率限流或每日配额超限)
                    if ($status === 429) {
                        $body = $response->body();
                        // 若是每日配额耗尽 (Quota exceeded / Resource has been exhausted)，重试无意义，立即尝试下一个备选模型
                        if (str_contains($body, 'Quota exceeded') || str_contains($body, 'Resource has been exhausted')) {
                            Log::warning("[GeminiTranslationService] 模型 [{$model}] 每日配额已用尽 (429 Quota Exceeded)，正在自动切换至下一个备选模型...");
                            break;
                        }

                        if ($attempt < $maxRetries) {
                            Log::warning("[GeminiTranslationService] 模型 [{$model}] 触发瞬时 429 限流，等待 3 秒后重试...");
                            sleep(3);
                            continue;
                        } else {
                            Log::warning("[GeminiTranslationService] 模型 [{$model}] 持续触发 429 限流，自动切换尝试备选模型...");
                            break;
                        }
                    }

                    // 5. 其他异常 HTTP 状态码
                    Log::error("[GeminiTranslationService] 模型 [{$model}] API 请求失败 [HTTP {$status}]: " . $response->body());
                    break;
                } catch (Throwable $e) {
                    Log::error('[GeminiTranslationService] 模型 [' . $model . '] 请求异常: ' . $e->getMessage());
                    break;
                }
            }
        }

        Log::error('[GeminiTranslationService] 所有候选模型均调用失败');
        return [];
    }

    /**
     * 单条翻译视频标题为简体中文
     *
     * @param string $title
     * @return string|null
     */
    public function translateSingle(string $title): ?string
    {
        $batchResult = $this->translateBatch([1 => $title]);
        return $batchResult[1] ?? null;
    }
}

