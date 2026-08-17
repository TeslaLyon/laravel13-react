<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetIntendedUrl
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. 我们只处理 GET 请求，并且检查 URL 参数中是否包含 redirect
        if ($request->isMethod('GET') && $request->has('redirect')) {

            $redirectUrl = $request->query('redirect');

            // 2. 安全校验：确保跳转的是我们自己网站内的相对路径 (以 / 开头)
            if (is_string($redirectUrl) && str_starts_with($redirectUrl, '/')) {

                // 3. 将其存入 Laravel 官方认证体系所使用的键名 'url.intended' 中
                session()->put('url.intended', $redirectUrl);
            }
        }

        return $next($request);
    }
}
