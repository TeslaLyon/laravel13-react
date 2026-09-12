<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\UserCheckIn;
use App\Models\UserCreditLog;
use App\Models\UserGroup;
use Carbon\Carbon;

class GrowthService
{
    public function __construct(
        protected CreditService $creditService
    ) {
    }

    public function getGrowthCenterData(User $user): array
    {
        $this->creditService->syncUserCreditsAndGroup($user);
        $user->refresh();

        $currentCredits = (float) ($user->total_credits ?? 0.00);

        $creditGroups = UserGroup::query()
            ->where('is_credit_based', true)
            ->orderBy('level', 'asc')
            ->get();

        /** @var UserGroup $currentGroup */
        $currentGroup = $user->primaryGroup ?? $creditGroups->first();
        $currentLevel = (int) ($currentGroup?->level ?? 1);

        /** @var UserGroup|null $nextGroup */
        $nextGroup = $creditGroups->firstWhere('level', $currentLevel + 1);

        $currentMin = (float) ($currentGroup?->credits_min ?? 0.00);
        $nextMin = $nextGroup ? (float) $nextGroup->credits_min : $currentCredits;
        $neededCredits = max(0.00, round($nextMin - $currentCredits, 2));

        $levelSpan = max(1.00, $nextMin - $currentMin);
        $progressPercent = $nextGroup
            ? min(100, (int) round((($currentCredits - $currentMin) / $levelSpan) * 100))
            : 100;

        $metricsBreakdown = [
            [
                'key' => 'post_count',
                'title' => '发帖与回复',
                'count' => (int) $user->post_count,
                'weight' => 0.1,
                'subtotal' => round($user->post_count * 0.1, 2),
                'unit' => '篇',
                'is_deduction' => false,
            ],
            [
                'key' => 'enthusiasm_points',
                'title' => '热心值',
                'count' => (int) $user->enthusiasm_points,
                'weight' => 1.2,
                'subtotal' => round($user->enthusiasm_points * 1.2, 2),
                'unit' => '点',
                'is_deduction' => false,
            ],
            [
                'key' => 'bounty_points',
                'title' => '悬赏值',
                'count' => (int) $user->bounty_points,
                'weight' => 1.5,
                'subtotal' => round($user->bounty_points * 1.5, 2),
                'unit' => '点',
                'is_deduction' => false,
            ],
            [
                'key' => 'contribution_points',
                'title' => '贡献值',
                'count' => (int) $user->contribution_points,
                'weight' => 1.5,
                'subtotal' => round($user->contribution_points * 1.5, 2),
                'unit' => '点',
                'is_deduction' => false,
            ],
            [
                'key' => 'prestige_points',
                'title' => '社区威望',
                'count' => (int) $user->prestige_points,
                'weight' => 20.0,
                'subtotal' => round($user->prestige_points * 20.0, 2),
                'unit' => '点',
                'is_deduction' => false,
            ],
            [
                'key' => 'digest_thread_count',
                'title' => '精华主题帖',
                'count' => (int) $user->digest_thread_count,
                'weight' => 100.0,
                'subtotal' => round($user->digest_thread_count * 100.0, 2),
                'unit' => '篇',
                'is_deduction' => false,
            ],
            [
                'key' => 'violation_count',
                'title' => '违规处罚记录',
                'count' => (int) $user->violation_count,
                'weight' => -20.0,
                'subtotal' => round($user->violation_count * -20.0, 2),
                'unit' => '次',
                'is_deduction' => true,
            ],
        ];

        $todayStr = Carbon::today()->toDateString();
        $isTodayChecked = UserCheckIn::where('user_id', $user->id)
            ->where('check_in_date', $todayStr)
            ->exists();

        $tasks = [
            [
                'id' => 'daily_checkin',
                'title' => '每日打卡签到',
                'description' => '保持连续签到，获取金币与活跃成长',
                'reward_desc' => '+10 金币',
                'is_completed' => $isTodayChecked,
                'action_url' => '/checkin',
                'action_text' => $isTodayChecked ? '已完成' : '去打卡',
            ],
            [
                'id' => 'post_interaction',
                'title' => '参与主题讨论',
                'description' => '在论坛板块发布新帖或回复他人观点 (+0.1 积分/篇)',
                'reward_desc' => '+0.1 积分/次',
                'is_completed' => false,
                'action_url' => '/forum',
                'action_text' => '去发帖',
            ],
            [
                'id' => 'earn_prestige',
                'title' => '创作优质内容获精华/威望',
                'description' => '发表深度技术文章，被设为精华可获 +100 巨额积分',
                'reward_desc' => '+100 积分/篇',
                'is_completed' => false,
                'action_url' => '/forum',
                'action_text' => '去创作',
            ],
        ];

        // 提取最近 20 条日志
        $creditLogs = UserCreditLog::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar,
                'custom_title' => $user->custom_title,
                'cached_title' => $user->cached_title,
                'display_title' => $user->display_title,
                'total_credits' => $currentCredits,
                'needed_credits' => $neededCredits,
                'progress_percent' => $progressPercent,
            ],
            'current_group' => $currentGroup,
            'next_group' => $nextGroup,
            'all_groups' => $creditGroups,
            'metrics_breakdown' => $metricsBreakdown,
            'tasks' => $tasks,
            'credit_logs' => $creditLogs,
        ];
    }
}
