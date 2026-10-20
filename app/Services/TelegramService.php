<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramService
{
    /**
     * 发送 Telegram HTML 消息
     *
     * @param string $htmlContent HTML 格式的消息内容
     * @param string|null $chatId 可选的目标 Chat ID（留空默认读取 config('services.telegram.chat_id')）
     * @return bool 是否全部发送成功
     */
    public function sendMessage(string $htmlContent, ?string $chatId = null): bool
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = $chatId ?: config('services.telegram.chat_id');

        if (empty($botToken) || empty($chatId)) {
            Log::info("未配置 TELEGRAM_BOT_TOKEN 或 TELEGRAM_CHAT_ID，跳过 Telegram 消息发送。");
            return false;
        }

        // Telegram 单条消息最大长度为 4096 字符，安全截断阈值设为 4000
        $maxLength = 4000;
        if (mb_strlen($htmlContent, 'UTF-8') <= $maxLength) {
            return $this->sendRawMessage($botToken, (string) $chatId, $htmlContent);
        }

        // 超过单条限制时按行进行智能分段推送
        $lines = explode("\n", $htmlContent);
        $currentChunk = '';
        $allSuccess = true;

        foreach ($lines as $line) {
            if (mb_strlen($currentChunk . "\n" . $line, 'UTF-8') > $maxLength) {
                if (!empty($currentChunk)) {
                    $sent = $this->sendRawMessage($botToken, (string) $chatId, trim($currentChunk));
                    if (!$sent) {
                        $allSuccess = false;
                    }
                    $currentChunk = '';
                }
            }
            $currentChunk .= ($currentChunk === '' ? '' : "\n") . $line;
        }

        if (!empty($currentChunk)) {
            $sent = $this->sendRawMessage($botToken, (string) $chatId, trim($currentChunk));
            if (!$sent) {
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    /**
     * 底层发送单条 HTTP 请求
     */
    protected function sendRawMessage(string $botToken, string $chatId, string $content): bool
    {
        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id'                  => $chatId,
                'text'                     => $content,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => true,
            ]);

            if (!$response->successful()) {
                Log::warning("发送 Telegram 消息失败 [HTTP {$response->status()}]: " . $response->body());
                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::warning("发送 Telegram 消息异常: " . $e->getMessage());
            return false;
        }
    }
}

