<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserCreditLog;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreditService
{
    /**
     * 各指标权重系数配置字典
     */
    public const METRIC_WEIGHTS = [
        'post_count' => 0.1,
        'enthusiasm_points' => 1.2,
        'bounty_points' => 1.5,
        'contribution_points' => 1.5,
        'prestige_points' => 20.0,
        'digest_thread_count' => 100.0,
        'violation_count' => -20.0,
    ];

    /**
     * 根据公式计算用户的总积分
     */
    public function computeCredits(User $user): float
    {
        $credits = (float) (
            ($user->post_count * self::METRIC_WEIGHTS['post_count']) +
            ($user->enthusiasm_points * self::METRIC_WEIGHTS['enthusiasm_points']) +
            ($user->bounty_points * self::METRIC_WEIGHTS['bounty_points']) +
            ($user->contribution_points * self::METRIC_WEIGHTS['contribution_points']) +
            ($user->prestige_points * self::METRIC_WEIGHTS['prestige_points']) +
            ($user->digest_thread_count * self::METRIC_WEIGHTS['digest_thread_count']) +
            ($user->violation_count * self::METRIC_WEIGHTS['violation_count'])
        );

        return round(max(0.00, $credits), 2);
    }

    /**
     * 增减用户指标并同步写入变动流水日志
     */
    public function incrementMetric(
        User $user,
        string $metricColumn,
        int $amount = 1,
        string $action = 'metric_change',
        string $remark = '活跃指标变动',
        ?Model $source = null,
        ?int $operatorId = null
    ): array {
        if (!array_key_exists($metricColumn, self::METRIC_WEIGHTS)) {
            throw new InvalidArgumentException("不支持的指标字段: {$metricColumn}");
        }

        return DB::transaction(function () use ($user, $metricColumn, $amount, $action, $remark, $source, $operatorId) {
            $user->refresh();

            // 1. 更新指定指标
            $user->increment($metricColumn, $amount);
            $user->refresh();

            // 2. 重新计算最新总积分
            $afterCredits = $this->computeCredits($user);
            $user->total_credits = $afterCredits;

            // 3. 自动晋升/降级判定
            $currentGroup = $user->primaryGroup;
            $oldGroupId = $user->primary_group_id;
            $groupChanged = false;
            $newGroup = $currentGroup;

            if (!$currentGroup || $currentGroup->is_credit_based) {
                $matchedGroup = UserGroup::query()
                    ->where('is_credit_based', true)
                    ->where('credits_min', '<=', $afterCredits)
                    ->where(function ($query) use ($afterCredits) {
                        $query->whereNull('credits_max')
                            ->orWhere('credits_max', '>=', $afterCredits);
                    })
                    ->orderByDesc('level')
                    ->first();

                if ($matchedGroup && $matchedGroup->id !== $oldGroupId) {
                    $user->primary_group_id = $matchedGroup->id;
                    $user->cached_title = $matchedGroup->title;
                    $groupChanged = true;
                    $newGroup = $matchedGroup;
                }
            }

            $user->save();

            // 4. 写入审计日志流水 (匹配字段: field, change_amount, after_value, current_total_credits, remark)
            UserCreditLog::create([
                'user_id' => $user->id,
                'operator_id' => $operatorId,
                'action' => $action,
                'field' => $metricColumn,
                'change_amount' => $amount,
                'after_value' => (float) $user->{$metricColumn},
                'current_total_credits' => $afterCredits,
                'remark' => $remark,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);

            return [
                'total_credits' => $afterCredits,
                'group_changed' => $groupChanged,
                'old_group_id' => $oldGroupId,
                'new_group_id' => $user->primary_group_id,
                'current_group' => $newGroup,
            ];
        });
    }

    /**
     * 同步用户的总积分与用户组
     */
    public function syncUserCreditsAndGroup(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $user->refresh();

            $newTotalCredits = $this->computeCredits($user);
            $user->total_credits = $newTotalCredits;

            $currentGroup = $user->primaryGroup;
            $oldGroupId = $user->primary_group_id;
            $groupChanged = false;
            $newGroup = $currentGroup;

            if (!$currentGroup || $currentGroup->is_credit_based) {
                $matchedGroup = UserGroup::query()
                    ->where('is_credit_based', true)
                    ->where('credits_min', '<=', $newTotalCredits)
                    ->where(function ($query) use ($newTotalCredits) {
                        $query->whereNull('credits_max')
                            ->orWhere('credits_max', '>=', $newTotalCredits);
                    })
                    ->orderByDesc('level')
                    ->first();

                if ($matchedGroup && $matchedGroup->id !== $oldGroupId) {
                    $user->primary_group_id = $matchedGroup->id;
                    $user->cached_title = $matchedGroup->title;
                    $groupChanged = true;
                    $newGroup = $matchedGroup;
                }
            }

            $user->save();

            return [
                'total_credits' => $newTotalCredits,
                'group_changed' => $groupChanged,
                'old_group_id' => $oldGroupId,
                'new_group_id' => $user->primary_group_id,
                'current_group' => $newGroup,
            ];
        });
    }
}
