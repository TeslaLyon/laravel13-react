<?php

namespace App\Observers;

use App\Enums\WalletStatus;
use App\Models\User;
use App\Services\CreditService;

class UserObserver
{
    /**
     * 依赖注入积分计算服务
     */
    public function __construct(
        protected CreditService $creditService
    ) {
    }

    /**
     * 当 User 记录准备写入数据库前（创建与更新前均会触发）
     */
    public function saving(User $user): void
    {
        // 核心参与总积分计算的 7 项互动指标
        $metricFields = [
            'post_count',
            'enthusiasm_points',
            'bounty_points',
            'contribution_points',
            'prestige_points',
            'digest_thread_count',
            'violation_count',
        ];

        // 🌟 脏检查：只要任何一项指标发生改变，即时在内存中重算总积分
        if ($user->isDirty($metricFields)) {
            $user->total_credits = $this->creditService->computeCredits($user);
        }
    }

    /**
     * 当 User 记录被成功创建并持久化到数据库后自动触发
     */
    public function created(User $user): void
    {
        // 自动初始化对应钱包
        $user->wallet()->create([
            'balance' => 0,
            'frozen_balance' => 0,
            'total_recharge' => 0,
            'total_withdrawn' => 0,
            'total_spent' => 0,
            'status' => WalletStatus::ACTIVE,
            'version' => 0,
        ]);
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        //
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
