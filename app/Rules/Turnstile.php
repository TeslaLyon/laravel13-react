<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class Turnstile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secretKey = config('services.turnstile.secret_key');

        // 本地/测试环境下若未配置密钥，则容错放行，避免阻碍日常开发测试
        if (empty($secretKey)) {
            return;
        }

        if (empty($value) || !is_string($value)) {
            $fail('请完成人机安全验证。');
            return;
        }

        try {
            $payload = [
                'secret'   => $secretKey,
                'response' => $value,
            ];

            // 仅在真实公网 IP 时传递 remoteip，避免本地内网/Docker/反向代理 IP 干扰 Cloudflare 校验
            $ip = request()->ip();
            if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                $payload['remoteip'] = $ip;
            }

            $response = Http::asForm()->timeout(10)->post(
                'https://challenges.cloudflare.com/turnstile/v0/siteverify',
                $payload
            );

            if (!$response->successful() || !$response->json('success')) {
                \Illuminate\Support\Facades\Log::warning('Cloudflare Turnstile 验证未通过', [
                    'status' => $response->status(),
                    'error_codes' => $response->json('error-codes'),
                    'response' => $response->json(),
                ]);
                $fail('人机安全验证未通过，请重试。');
            }
        } catch (\Throwable $e) {
            report($e);
            $fail('人机验证通信异常，请稍后重试。');
        }
    }
}

