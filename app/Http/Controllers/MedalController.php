<?php

namespace App\Http\Controllers;

use App\Models\Medal;
use App\Models\MedalCategory;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MedalController extends Controller
{
    /**
     * 1. 全站成就勋章中心大页 (Medals/Index)
     */
    public function index(Request $request): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $request->user();
        $userMedals = $currentUser ? $currentUser->userMedals()->get()->keyBy('medal_id') : collect();

        $categories = MedalCategory::query()
            ->active()
            ->ordered()
            ->with(['medals' => fn($q) => $q->active()->ordered()])
            ->get()
            ->map(function (MedalCategory $category) use ($userMedals) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'medals' => $category->medals->map(function (Medal $medal) use ($userMedals) {
                        $pivot = $userMedals->get($medal->id);
                        return [
                            'id' => $medal->id,
                            'code' => $medal->code,
                            'title' => $medal->title,
                            'description' => $medal->description,
                            'conditionText' => $medal->condition_text,
                            'iconUrl' => $medal->icon_url,
                            'rarity' => $medal->rarity,
                            'trophyPoints' => $medal->trophy_points,
                            'isUnlocked' => $pivot !== null,
                            'unlockedAt' => $pivot?->unlocked_at?->format('Y-m-d'),
                            'unlockedRate' => 12.5, // 全站解锁百分比
                        ];
                    }),
                ];
            });

        $totalMedals = Medal::query()->active()->count();
        $unlockedCount = $userMedals->count();

        return Inertia::render('medal/index', [
            'breadcrumbs' => [
                ['title' => '首页', 'href' => route('home')],
                ['title' => '成就勋章中心', 'href' => null],
            ],
            'stats' => [
                'isLoggedIn' => $currentUser !== null,
                'totalCount' => $totalMedals,
                'unlockedCount' => $unlockedCount,
                'completionRate' => $totalMedals > 0 ? (int) round(($unlockedCount / $totalMedals) * 100) : 0,
                'totalTrophyPoints' => $currentUser ? (int) $currentUser->medals()->sum('trophy_points') : 0,
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * 2. 指定用户的专属勋章荣誉馆 (UserSpace/Medals)
     */
    public function userMedals(Request $request, User $user): Response
    {
        /** @var User|null $currentUser */
        $currentUser = $request->user();
        $isSelf = $currentUser?->id === $user->id;

        // 获取目标用户的全部勋章关联
        $userMedalRecords = $user->userMedals()->get()->keyBy('medal_id');

        // 按分类获取全量勋章及该用户的点亮状态
        $categories = MedalCategory::query()
            ->active()
            ->ordered()
            ->with(['medals' => fn($q) => $q->active()->ordered()])
            ->get()
            ->map(function (MedalCategory $category) use ($userMedalRecords) {
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'medals' => $category->medals->map(function (Medal $medal) use ($userMedalRecords) {
                        $pivot = $userMedalRecords->get($medal->id);
                        $isUnlocked = $pivot !== null;

                        return [
                            'id' => $medal->id,
                            'code' => $medal->code,
                            'title' => $medal->title,
                            'description' => $medal->description,
                            'conditionText' => $medal->condition_text,
                            'iconUrl' => $medal->icon_url,
                            'rarity' => $medal->rarity,
                            'trophyPoints' => $medal->trophy_points,
                            'isUnlocked' => $isUnlocked,
                            'isWorn' => (bool) ($pivot?->is_worn ?? false),
                            'wearSlot' => $pivot?->wear_slot,
                            'unlockedAt' => $pivot?->unlocked_at?->format('Y-m-d'),
                            'awardReason' => $pivot?->award_reason,
                        ];
                    }),
                ];
            });

        // 荣誉数据统计
        $totalMedalsCount = Medal::query()->active()->count();
        $unlockedCount = $userMedalRecords->count();
        $totalTrophyPoints = $user->medals()->sum('trophy_points');

        return Inertia::render('medal/show', [
            'breadcrumbs' => [
                [
                    'title' => '首页',
                    'href' => route('home'),
                ],
                [
                    'title' => $user->nickname ?? $user->name,
                    'href' => route('userspace.show', ['user' => $user->name]), // 跳转至个人空间
                ],
                [
                    'title' => '勋章馆',
                    'href' => null, // 当前末尾项
                ],
            ],
            'targetUser' => [
                'id' => $user->id,
                'name' => $user->name,
                'nickname' => $user->nickname ?? $user->name,
                'avatar' => $user->avatar ?? '/images/default-avatar.png',
                'isSelf' => $isSelf,
            ],
            'stats' => [
                'totalCount' => $totalMedalsCount,
                'unlockedCount' => $unlockedCount,
                'completionRate' => $totalMedalsCount > 0 ? (int) round(($unlockedCount / $totalMedalsCount) * 100) : 0,
                'totalTrophyPoints' => (int) $totalTrophyPoints,
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * 3. 佩戴 / 卸下勋章操作（限制最多佩戴 4 枚）
     */
    public function toggleWear(Request $request, Medal $medal): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        // 校验该用户是否真实拥有该勋章
        $pivot = $user->userMedals()->where('medal_id', $medal->id)->firstOrFail();

        if ($pivot->is_worn) {
            // 执行卸下
            $pivot->update([
                'is_worn' => false,
                'wear_slot' => null,
            ]);
        } else {
            // 校验最大佩戴上限
            $wornCount = $user->userMedals()->where('is_worn', true)->count();
            if ($wornCount >= 4) {
                return back()->with('error', '最多同时佩戴 4 枚勋章，请先卸下其它勋章后再试。');
            }

            // 执行佩戴并分配槽位
            $pivot->update([
                'is_worn' => true,
                'wear_slot' => $wornCount + 1,
            ]);
        }

        return back();
    }
}
