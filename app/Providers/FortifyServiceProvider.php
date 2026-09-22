<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Contracts\LoginResponse;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Symfony\Component\HttpFoundation\Response;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->instance(LoginResponse::class, new class implements LoginResponse {

            /**
             * 处理登录成功后的响应
             *
             * @param Request $request 当前的 POST 请求实例
             */
            public function toResponse($request)
            {
                // 2. 检查前端 POST 表单中是否传来了 redirect 参数
                if ($request->filled('redirect')) {
                    $redirectUrl = $request->input('redirect');

                    // 3. 安全校验：确保是站内跳转
                    if (is_string($redirectUrl) && str_starts_with($redirectUrl, '/')) {
                        return redirect()->to($redirectUrl);
                    }
                }

                // 4. 如果没有重定向参数，或者不符合安全规则，则跳转到默认后台
                // 这里通常使用 config 里的配置，或者 fallback 到 dashboard
                return redirect()->intended(config('fortify.home', '/dashboard'));
            }
        });

        // 🌟 核心：覆写 Fortify 默认的登出响应契约
        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse {
            /**
             * 处理退出登录后的 HTTP 响应
             */
            public function toResponse($request): Response
            {
                // 1. 如果是纯 REST API / 移动端请求（非 Inertia 发起），返回 204 状态码
                if ($request->wantsJson() && !$request->header('X-Inertia')) {
                    return new JsonResponse('', 204);
                }

                // 2. 针对 Inertia.js 页面请求：
                // 执行 back() 原地回跳到当前页面（如视频详情页），触发 Inertia 重新下发游客状态 Props
                return redirect()->back();
            }
        });

        // 🌟 2. 两步验证响应（涵盖进入 2FA 挑战页与 2FA 验证通过两个阶段）
        $this->app->instance(TwoFactorLoginResponse::class, new class implements TwoFactorLoginResponse {
            /**
             * 处理两步验证流程中的响应
             *
             * @param  Request  $request
             */
            public function toResponse($request): Response
            {
                // API 请求（非 Inertia）直接返回 JSON
                if ($request->wantsJson() && !$request->header('X-Inertia')) {
                    return new JsonResponse('', 200);
                }

                $redirectUrl = $request->input('redirect');

                // 阶段 B：验证码通过，用户已真正登录（Auth::check 为 true）
                if (Auth::check()) {
                    if (is_string($redirectUrl) && str_starts_with($redirectUrl, '/') && !str_starts_with($redirectUrl, '//')) {
                        return redirect()->to($redirectUrl);
                    }

                    return redirect()->intended(config('fortify.home', '/dashboard'));
                }

                // 阶段 A：密码正确但需要输入 2FA 验证码（将 redirect 参数接力传递给挑战页）
                $params = [];
                if (is_string($redirectUrl) && str_starts_with($redirectUrl, '/') && !str_starts_with($redirectUrl, '//')) {
                    $params['redirect'] = $redirectUrl;
                }

                return redirect()->route('two-factor.login', $params);
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // 🌟 登录时进行 Cloudflare Turnstile 验证并校验账号密码
        Fortify::authenticateUsing(function (Request $request) {
            $request->validate([
                Fortify::username() => 'required|string',
                'password' => 'required|string',
                'cf-turnstile-response' => [new \App\Rules\Turnstile],
            ]);

            $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

            if ($user && Hash::check($request->password, $user->password)) {
                return $user;
            }

            return null;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn(Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn(Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn(Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn(Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn() => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn() => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn() => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()) . '|' . $request->ip(),
            );
        });
    }
}
