<?php

namespace App\Services\Payment;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Exception;

class ThirdPartyApiClient
{
    protected string $baseUrl;
    protected string $appKey;
    protected string $appSecret;
    protected int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.vmq.base_url', 'localhost'), '/');
        $this->appKey = (string) config('services.vmq.app_key');
        $this->appSecret = (string) config('services.vmq.app_secret');
        $this->timeout = (int) config('services.vmq.timeout', 10);

        if (empty($this->appKey) || empty($this->appSecret)) {
            throw new Exception('第三方 API 客户端初始化失败：请在 .env 中配置 VMQ_APP_KEY 与 VMQ_APP_SECRET');
        }
    }

    /**
     * 构建带有 X- 签名 Header 的 HTTP 客户端请求
     */
    protected function buildSignedRequest(): PendingRequest
    {
        // 1. 生成 10 位时间戳和 16 位随机 Nonce
        $timestamp = (string) time();
        $nonce = Str::random(16);

        // 2. 拼接规则：AppKey + Timestamp + Nonce
        $signPayload = $this->appKey . $timestamp . $nonce;

        // 3. HMAC-SHA256 生成十六进制小写签名
        $signature = hash_hmac('sha256', $signPayload, $this->appSecret);

        return Http::baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withHeaders([
                'X-App-Key' => $this->appKey,
                'X-Timestamp' => $timestamp,
                'X-Nonce' => $nonce,
                'X-Signature' => $signature,
            ]);
    }

    /**
     * 创建充值订单（精确对齐 /openapi/orders 规范）
     *
     * @param int    $userId        当前系统用户 ID
     * @param int    $amountInCents 充值金额（单位：分，如 1000 代表 10 纸巾）
     * @param string $channel       渠道（'wechat' 或 'alipay'）
     * @param string $subject       订单标题
     * @param string $notifyUrl     异步回调通知地址
     * @param string $returnUrl     支付成功跳转地址
     * @return array                网关返回的 data 字段内容
     */
    public function createPaymentOrder(
        int $userId,
        int $amountInCents,
        string $channel,
        string $subject,
        string $notifyUrl,
        string $returnUrl
    ): array {
        // 1. 支付渠道映射：1 为微信，2 为支付宝
        $type = match ($channel) {
            'alipay' => 2,
            'wechat' => 1,
            default => 2,
        };

        // 2. 将系统内部存储的“分”换算为第三方接口期望的“元”
        // 例如：1000 分 -> 10 元；100 分 -> 1 元
        $price = $amountInCents / 100;

        // 3. 构建与 API 完全一致的请求体
        $payload = [
            'user_id' => $userId,
            'type' => $type,
            'price' => $price,
            'subject' => $subject,
            'notify_url' => $notifyUrl,
            'return_url' => $returnUrl,
        ];

        try {
            // 发起 POST 请求至目标端点 /openapi/orders
            $response = $this->buildSignedRequest()->post('/openapi/orders', $payload);

            if (!$response->successful()) {
                Log::error('第三方创建订单接口网络或HTTP错误', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                throw new Exception("支付网关连接失败 (HTTP {$response->status()})");
            }

            $result = $response->json();

            // 4. 判断响应是否符合 { "success": true, "code": 200 }
            if (empty($result['success']) || ($result['code'] ?? 0) !== 200) {
                Log::warning('第三方创建订单业务逻辑报错', ['response' => $result]);
                throw new Exception($result['msg'] ?? '支付订单创建失败');
            }

            // 返回核心数据包（包含 pay_url, order_id 等）
            return $result['data'];
        } catch (Exception $e) {
            Log::error('调用充值订单接口异常: ' . $e->getMessage());
            throw $e;
        }
    }
}
