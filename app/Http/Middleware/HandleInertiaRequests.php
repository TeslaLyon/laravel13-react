<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => function () use ($request) {
                    if (!$user = $request->user()) {
                        return null;
                    }

                    // 🌟 1. 自动自愈：若佩戴已过期则立即清理置空
                    $user->cleanIfDecorationExpired();

                    // 🌟 2. 预加载挂件信息，补全 is_active 字段
                    $user->loadMissing([
                        'avatarDecoration' => function ($query) {
                        $query->select(['id', 'title', 'code', 'image_url', 'is_active']);
                    },
                    ]);

                    return $user;
                },
            ],
            'sidebarOpen' => $request->cookie('sidebar_state') === 'true',
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'cdnUrl' => config('app.cdn_url'),
        ];
    }
}
