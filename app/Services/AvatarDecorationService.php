<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\AvatarDecoration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AvatarDecorationService
{
    /**
     * 校验并自动解锁达标挂件
     *
     * @param User $user 当前用户
     * @param array<string, int|float> $userData 当前用户各类累计统计数值
     */
    public function evaluateAndUnlock(User $user, array $userData): void
    {
        $activeDecorationIds = $user->unlockedDecorations()
            ->where(function ($query) {
                $query->whereNull('user_avatar_decorations.expires_at')
                    ->orWhere('user_avatar_decorations.expires_at', '>', Carbon::now());
            })
            ->pluck('avatar_decorations.id')
            ->toArray();

        $decorations = AvatarDecoration::query()
            ->where('is_active', true)
            ->whereNotIn('id', $activeDecorationIds)
            ->get();

        foreach ($decorations as $decoration) {
            if ($this->isCriteriaSatisfied($decoration->criteria, $userData)) {
                $user->unlockedDecorations()->syncWithoutDetaching([
                    $decoration->id => [
                        'unlocked_at' => Carbon::now(),
                        'expires_at' => null,
                    ],
                ]);
            }
        }
    }

    /**
     * 判定是否满足全部配置规则（AND 关系）
     */
    protected function isCriteriaSatisfied(?array $criteriaList, array $userData): bool
    {
        if (empty($criteriaList)) {
            return false;
        }

        foreach ($criteriaList as $condition) {
            $rule = $condition['rule'] ?? '';
            $targetValue = $condition['value'] ?? 0;
            $userValue = $userData[$rule] ?? 0;

            if ($userValue < $targetValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * 佩戴或卸载挂件（严格防穿透校验）
     */
    public function wearDecoration(User $user, ?int $decorationId): bool
    {
        // 1. 卸下操作：直接清空
        if ($decorationId === null) {
            $user->update(['avatar_decoration_id' => null]);
            return true;
        }

        // 2. 挂件基础有效性检查
        $decoration = AvatarDecoration::query()->find($decorationId);
        if (!$decoration || !$decoration->is_active) {
            return false;
        }

        // 3. 查询背包记录
        $pivotRecord = DB::table('user_avatar_decorations')
            ->where('user_id', $user->id)
            ->where('avatar_decoration_id', $decorationId)
            ->first();

        if (!$pivotRecord) {
            return false;
        }

        // 4. 严格过期校验：全局时区为 Asia/Shanghai 时，isPast() 会精准比对本地当前时间
        if ($pivotRecord->expires_at !== null) {
            $expiresAt = Carbon::parse($pivotRecord->expires_at);
            if ($expiresAt->isPast()) {
                return false;
            }
        }

        // 5. 校验通过，更新 users 表外键
        $user->update(['avatar_decoration_id' => $decorationId]);

        return true;
    }
}
