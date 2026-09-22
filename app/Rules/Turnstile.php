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
            $response = Http::asForm()->timeout(10)->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                'secret'   => $secretKey,
                'response' => $value,
                'remoteip' => request()->ip(),
            ]);

            if (!$response->successful() || !$response->json('success')) {
                $fail('人机安全验证未通过，请刷新后重试。');
            }
        } catch (\Throwable $e) {
            report($e);
            $fail('人机验证通信异常，请稍后重试。');
        }
    }
}

