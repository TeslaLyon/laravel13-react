<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserSpaceTabService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserSpaceController extends Controller
{
    public function __construct(
        protected UserSpaceTabService $tabService
    ) {
    }

    public function show(Request $request, User $user, ?string $tab = null): Response
    {
        // 🌟 1. 合法 Tab 白名单（完全剔除 videos 作品选项卡）
        $validTabs = [
            'home',
            'video-collections',
            'image-collections',
            'video-watch-later',
            'video-likes',
            'community',
            'about',
        ];

        // 🌟 2. 核心 404 拦截：防止穿透到其他兜底路由
        abort_if($tab !== null && !in_array($tab, $validTabs, true), 404);

        // 3. 缺省定位到 home
        $currentTab = $tab ?? 'home';

        /** @var User|null $currentUser */
        $currentUser = $request->user();

        // 🌟 4. 过期装扮自愈与批量预加载（合并 group 关联，避免 N+1 查询）
        $user->cleanIfDecorationExpired();
        $user->loadMissing(['avatarDecoration', 'group']);

        // 5. 粉丝与勋章统计
        $followersCount = $user->getEntitySubscribersCount($user);
        $activeNotificationType = $currentUser?->getSubscriptionNotificationType($user);

        $medals = $user->medals()
            ->where('is_active', true)
            ->orderByPivot('is_worn', 'desc')
            ->orderByPivot('wear_slot', 'asc')
            ->orderByPivot('unlocked_at', 'desc')
            ->take(6)
            ->get()
            ->map(fn($medal) => [
                'id' => $medal->id,
                'code' => $medal->code,
                'title' => $medal->title,
                'description' => $medal->description,
                'iconUrl' => $medal->icon_url,
                'rarity' => $medal->rarity,
                'isWorn' => (bool) $medal->pivot->is_worn,
                'unlockedAt' => $medal->pivot->unlocked_at?->format('Y-m-d'),
            ]);

        $decoration = $user->avatarDecoration;
        $avatarDecorationData = ($decoration && ($decoration->is_active ?? true)) ? [
            'id' => $decoration->id,
            'title' => $decoration->title,
            'imageUrl' => $decoration->image_url,
            'image_url' => $decoration->image_url,
        ] : null;

        return Inertia::render('UserSpace/Show', [
            'activeTab' => $currentTab,

            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname ?? $user->name,
                'avatar' => $user->avatar ?? '/images/default-avatar.png',
                'bannerUrl' => $user->banner_url ?? $user->banner,
                'bio' => $user->bio,
                'followersCount' => $followersCount,
                // 🌟 优先读取字段缓存的 post_count，不存在时再回退到关系统计
                'worksCount' => $user->post_count ?? (method_exists($user, 'posts') ? $user->posts()->count() : 0),
                'isFollowing' => $activeNotificationType !== null,
                'isSelf' => $currentUser ? $currentUser->id === $user->id : false,
                'group' => [
                    'id' => $user->group?->id ?? 1,
                    'name' => $user->group?->name ?? '普通成员',
                    'slug' => $user->group?->slug ?? 'member',
                ],
                'tier' => [
                    'level' => $user->level ?? 1,
                    'title' => $user->level_title ?? '青铜探索者',
                    'currentExp' => $user->current_exp ?? 320,
                    'nextLevelExp' => $user->next_level_exp ?? 500,
                ],
                'medals' => $medals,
                'avatarDecoration' => $avatarDecorationData,
            ],

            'permissions' => $this->tabService->resolvePermissions($currentUser, $user),

            // 🌟 6. 按需延迟并发通道（仅挂载内容消费相关的收藏/点赞/稍后观看）
            'collectedVideos' => $currentTab === 'video-collections'
                ? Inertia::defer(fn() => $this->tabService->resolveVideoCollections($currentUser, $user))
                : null,

            'collectedImages' => $currentTab === 'image-collections'
                ? Inertia::defer(fn() => $this->tabService->resolveImageCollections($currentUser, $user))
                : null,

            'likedVideos' => $currentTab === 'video-likes'
                ? Inertia::defer(fn() => $this->tabService->resolveLikedVideos($currentUser, $user))
                : null,

            'watchLaterVideos' => $currentTab === 'video-watch-later'
                ? Inertia::defer(fn() => $this->tabService->resolveWatchLaterVideos($currentUser, $user))
                : null,
        ]);
    }
}
