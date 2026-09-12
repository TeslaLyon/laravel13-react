<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\AvatarDecoration;
use App\Models\User;
use App\Services\AvatarDecorationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// TODO:添加定时清理任务，https://docs.google.com/document/d/10zZKAffLLSZyR0zNS2QvgxSig2uxcGTI1goknPRCmQ8/edit?tab=t.0

class AvatarDecorationController extends Controller
{
    /**
     * 头像挂件列表页面
     */
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // 1. 查询所有启用的挂件列表（按 display_order 升序排序）
        $allDecorations = AvatarDecoration::query()
            ->where('is_active', true)
            ->orderBy('display_order', 'asc')
            ->get();

        // 🌟 2. 提取当前佩戴 ID 并做严格的非空前置判定
        $rawWornId = $user->avatar_decoration_id;
        $effectiveDecorationId = $rawWornId !== null ? (int) $rawWornId : null;

        // 🌟 3. 惰性自愈：仅当用户有佩戴挂件时，才核验其背包记录是否过期
        if ($effectiveDecorationId !== null) {
            $isStillValid = $user->unlockedDecorations()
                ->where('avatar_decorations.id', $effectiveDecorationId)
                ->where(function ($query) {
                    $query->whereNull('user_avatar_decorations.expires_at')
                        ->orWhere('user_avatar_decorations.expires_at', '>', Carbon::now());
                })
                ->exists();

            // 若挂件已失效或已过期，就地清理数据库与内存脏数据
            if (!$isStillValid) {
                $user->update(['avatar_decoration_id' => null]);
                $effectiveDecorationId = null;
            }
        }

        // 🌟 4. 查出当前用户拥有的、未过期的挂件字典（限定中间表字段名）
        $unlockedMap = $user->unlockedDecorations()
            ->where(function ($query) {
                $query->whereNull('user_avatar_decorations.expires_at')
                    ->orWhere('user_avatar_decorations.expires_at', '>', Carbon::now());
            })
            ->get()
            ->keyBy('id');

        // 🌟 5. 组装前端所需数据结构（严格阻断 null 参与整型比对）
        $decorations = $allDecorations->map(function (AvatarDecoration $item) use ($effectiveDecorationId, $unlockedMap) {
            $userPivot = $unlockedMap->get($item->id);
            $isUnlocked = $userPivot !== null;

            // 必须在 effectiveDecorationId 不为 null 的前提下才进行比对
            $isWorn = $effectiveDecorationId !== null && ((int) $item->id === $effectiveDecorationId);

            $expiresAt = null;
            if (!empty($userPivot?->pivot?->expires_at)) {
                $expiresAt = Carbon::parse($userPivot->pivot->expires_at)->format('Y-m-d H:i');
            }

            return [
                'id' => $item->id,
                'title' => $item->title,
                'code' => $item->code,
                'imageUrl' => $item->image_url,
                'description' => $item->description,
                'isUnlocked' => $isUnlocked,
                'isWorn' => $isWorn,
                'expiresAt' => $expiresAt,
            ];
        });

        return Inertia::render('AvatarDecorations/Index', [
            'decorations' => $decorations,
            'currentDecorationId' => $effectiveDecorationId, // 为 null 时严格返回 null
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar ?? '/images/default-avatar.png',
            ],
        ]);
    }

    /**
     * 切换佩戴 / 卸下操作（含过期拦截与状态同步）
     */
    public function wear(Request $request, AvatarDecorationService $service): JsonResponse
    {
        $validated = $request->validate([
            'decoration_id' => ['nullable', 'integer', 'exists:avatar_decorations,id'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $targetId = isset($validated['decoration_id']) ? (int) $validated['decoration_id'] : null;
        $currentId = $user->avatar_decoration_id !== null ? (int) $user->avatar_decoration_id : null;

        // 1. 幂等逻辑：若传入的挂件 ID 与当前佩戴的一致，则视为主动卸下
        if ($targetId !== null && $currentId === $targetId) {
            $targetId = null;
        }

        // 2. 调用 Service 校验解锁资格与有效期，并在 users 表中更新 avatar_decoration_id
        $success = $service->wearDecoration($user, $targetId);

        if (!$success) {
            return response()->json([
                'success' => false,
                'message' => '该挂件尚未达成解锁条件或已过期！',
            ], 422);
        }

        $isWorn = $targetId !== null;
        $message = $isWorn ? '头像挂件佩戴成功！' : '已成功卸下当前挂件！';

        // 3. 构建返回的挂件数据对象
        $activeDecorationData = null;
        if ($isWorn) {
            $decoration = AvatarDecoration::query()->find($targetId);
            if ($decoration) {
                $activeDecorationData = [
                    'id' => $decoration->id,
                    'title' => $decoration->title,
                    'imageUrl' => $decoration->image_url,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => [
                'currentDecorationId' => $user->avatar_decoration_id,
                'decoration' => $activeDecorationData,
            ],
        ], 200);
    }
}
