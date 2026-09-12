<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserCheckInCardLog;
use App\Models\UserCheckInStat;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AddMakeUpCardSeeder extends Seeder
{
    /**
     * 执行补签卡充值
     */
    public function run(): void
    {
        // ------------------ 可配置参数 ------------------
        $targetUserId = 13;                 // 目标用户 ID
        $cardsToAdd   = 5;                 // 增加的补签卡数量（正整数）
        $action       = 'admin_manual_grant'; // 变动动作标识
        $description  = '管理员手动发放测试补签卡'; // 变动描述说明
        // ------------------------------------------------

        if ($cardsToAdd <= 0) {
            $this->command->error('发放数量必须大于 0！');
            return;
        }

        $user = User::find($targetUserId);
        if (!$user) {
            $this->command->error("未找到 ID 为 [{$targetUserId}] 的用户，请确认用户存在。");
            return;
        }

        $result = DB::transaction(function () use ($targetUserId, $cardsToAdd, $action, $description) {
            // 1. 获取或初始化用户签到统计记录并加行锁
            /** @var UserCheckInStat $stats */
            $stats = UserCheckInStat::query()->lockForUpdate()->firstOrCreate(
                ['user_id' => $targetUserId],
                [
                    'last_check_in_date'          => null,
                    'continuous_days'             => 0,
                    'longest_continuous_days'     => 0,
                    'total_days'                  => 0,
                    'make_up_cards'               => 0,
                    'total_make_up_cards_earned'  => 0,
                    'month_make_up_count'         => 0,
                ]
            );

            $balanceBefore = (int) $stats->make_up_cards;
            $balanceAfter  = $balanceBefore + $cardsToAdd;

            // 2. 更新统计资产（可用卡数与历史累计获得数）
            $stats->make_up_cards               = $balanceAfter;
            $stats->total_make_up_cards_earned += $cardsToAdd;
            $stats->save();

            // 3. 记录日志流水（补齐 balance_before 等非空字段）
            UserCheckInCardLog::create([
                'user_id'        => $targetUserId,
                'action'         => $action,
                'change_count'   => $cardsToAdd,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'description'    => $description,
                'source_type'    => null,
                'source_id'      => null,
            ]);

            return [
                'user_id'        => $targetUserId,
                'added_count'    => $cardsToAdd,
                'balance_before' => $balanceBefore,
                'balance_after'  => $balanceAfter,
                'total_earned'   => $stats->total_make_up_cards_earned,
            ];
        });

        // 4. 控制台输出执行结果
        $this->command->info("🎉 补签卡充值成功！");
        $this->command->table(
            ['用户 ID', '本次增加', '充值前余额', '最新可用余额', '累计获得总数'],
            [
                [
                    $result['user_id'],
                    '+' . $result['added_count'] . ' 张',
                    $result['balance_before'] . ' 张',
                    $result['balance_after'] . ' 张',
                    $result['total_earned'] . ' 张',
                ]
            ]
        );
    }
}
