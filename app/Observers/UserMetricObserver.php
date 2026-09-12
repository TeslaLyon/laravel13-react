<?php

namespace App\Observers;

use App\Models\User;
use App\Services\CreditService;

class UserMetricObserver
{
    public function __construct(
        protected CreditService $creditService
    ) {
    }

    /**
     * 用户数据保存前检测指标变动
     */
    public function saving(User $user): void
    {
        $metricFields = [
            'post_count',
            'enthusiasm_points',
            'bounty_points',
            'contribution_points',
            'prestige_points',
            'digest_thread_count',
            'violation_count',
        ];

        // 只要有任意一项积分相关指标发生修改，即时更新总积分
        if ($user->isDirty($metricFields)) {
            $user->total_credits = $this->creditService->computeCredits($user);
        }
    }
}
