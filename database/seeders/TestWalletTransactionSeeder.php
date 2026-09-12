<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TestWalletTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 13;
        $user = User::find($userId);

        if (!$user) {
            $this->command->error("未找到 ID 为 {$userId} 的用户，请先创建该用户！");
            return;
        }

        DB::transaction(function () use ($user) {
            // 1. 获取或初始化钱包
            /** @var Wallet $wallet */
            $wallet = Wallet::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'balance' => 0,
                    'frozen_balance' => 0,
                    'coins' => 0,
                    'frozen_coins' => 0,
                    'total_recharge' => 0,
                    'total_spent' => 0,
                    'total_withdrawn' => 0,
                    'total_earned_coins' => 0,
                    'status' => WalletStatus::ACTIVE,
                    'version' => 0,
                ]
            );

            // 2. 清理旧测试流水
            WalletTransaction::where('wallet_id', $wallet->id)->delete();

            // 3. 初始记账游标
            $currentBalance = 0.00;
            $currentCoins = 0;
            $totalRecharge = 0.00;
            $totalSpent = 0.00;
            $totalEarnedCoins = 0;

            // 4. 构建 25 条模拟业务事件 (按时间先后顺序排列)
            $events = [
                // Day 1
                ['type' => 'balance', 'action' => TransactionType::RECHARGE->value, 'amount' => 100.00, 'direction' => 1, 'desc' => '微信充值现金余额', 'days_ago' => 12],
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 12],
                ['type' => 'coins', 'action' => TransactionType::REWARD->value, 'amount' => 50, 'direction' => 1, 'desc' => '回答技术问答《Laravel Docker 配置》被采纳为最佳答案', 'days_ago' => 12],

                // Day 2
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 11],
                ['type' => 'coins', 'action' => TransactionType::CONSUME->value, 'amount' => 30, 'direction' => -1, 'desc' => '发布悬赏求助帖《Vue3 状态丢失排查》支付悬赏金币', 'days_ago' => 11],

                // Day 3
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 10],
                ['type' => 'coins', 'action' => TransactionType::MAKE_UP_REWARD->value, 'amount' => 20, 'direction' => 1, 'desc' => '达成当月连续签到 3 天里程碑礼包', 'days_ago' => 10],
                ['type' => 'balance', 'action' => TransactionType::CONSUME->value, 'amount' => 25.00, 'direction' => -1, 'desc' => '购买社区专栏《Go 微服务实战》', 'days_ago' => 10],

                // Day 4
                ['type' => 'balance', 'action' => TransactionType::RECHARGE->value, 'amount' => 200.00, 'direction' => 1, 'desc' => '支付宝快捷充值', 'days_ago' => 9],
                ['type' => 'coins', 'action' => TransactionType::REWARD->value, 'amount' => 100, 'direction' => 1, 'desc' => '解答悬赏问题《PostgreSQL 复合索引调优》获得悬赏', 'days_ago' => 9],
                ['type' => 'coins', 'action' => TransactionType::CONSUME->value, 'amount' => 60, 'direction' => -1, 'desc' => '发布悬赏求助帖《FFmpeg 推流卡顿分析》支付悬赏金币', 'days_ago' => 9],

                // Day 5
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 8],
                ['type' => 'balance', 'action' => TransactionType::CONSUME->value, 'amount' => 15.00, 'direction' => -1, 'desc' => '兑换站内置顶推荐位 (3天)', 'days_ago' => 8],

                // Day 6
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 7],
                ['type' => 'coins', 'action' => TransactionType::REWARD->value, 'amount' => 80, 'direction' => 1, 'desc' => '解答《Inertia.js SSR 部署指南》获得悬赏金币', 'days_ago' => 7],

                // Day 7
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 6],
                ['type' => 'coins', 'action' => TransactionType::MAKE_UP_REWARD->value, 'amount' => 50, 'direction' => 1, 'desc' => '达成当月连续签到 7 天全勤礼包', 'days_ago' => 6],
                ['type' => 'coins', 'action' => TransactionType::CONSUME->value, 'amount' => 40, 'direction' => -1, 'desc' => '发布悬赏求助帖《Caddy 反向代理 WebSocket 异常》支付悬赏', 'days_ago' => 6],

                // Day 8
                ['type' => 'balance', 'action' => TransactionType::RECHARGE->value, 'amount' => 500.00, 'direction' => 1, 'desc' => '网银在线充值', 'days_ago' => 5],
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 4],

                // Day 9
                ['type' => 'coins', 'action' => TransactionType::REWARD->value, 'amount' => 150, 'direction' => 1, 'desc' => '解答技术悬赏《GORM 联合索引唯一约束失效》获得高额悬赏', 'days_ago' => 3],
                ['type' => 'coins', 'action' => TransactionType::CONSUME->value, 'amount' => 50, 'direction' => -1, 'desc' => '发布悬赏求助帖《macOS 内存优化脚本》支付悬赏', 'days_ago' => 2],

                // Day 10
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '每日打卡签到奖励', 'days_ago' => 1],
                ['type' => 'balance', 'action' => TransactionType::CONSUME->value, 'amount' => 60.00, 'direction' => -1, 'desc' => '订阅技术峰会线上门票', 'days_ago' => 1],
                ['type' => 'coins', 'action' => TransactionType::CHECK_IN->value, 'amount' => 10, 'direction' => 1, 'desc' => '今日每日打卡签到奖励', 'days_ago' => 0],
            ];

            // 5. 循环入库流水并累加快照
            foreach ($events as $index => $event) {
                $isCoins = $event['type'] === 'coins';
                $direction = $event['direction'];
                $amount = $event['amount'];

                if ($isCoins) {
                    $before = $currentCoins;
                    $currentCoins += ($direction * (int) $amount);
                    $after = $currentCoins;

                    if ($direction === 1) {
                        $totalEarnedCoins += (int) $amount;
                    }
                } else {
                    $before = $currentBalance;
                    $currentBalance += ($direction * (float) $amount);
                    $after = $currentBalance;

                    if ($direction === 1) {
                        $totalRecharge += (float) $amount;
                    } else {
                        $totalSpent += (float) $amount;
                    }
                }

                $createdAt = Carbon::now()->subDays($event['days_ago'])->addMinutes($index * 15);

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'user_id' => $user->id,
                    'trx_no' => 'TRX' . $createdAt->format('Ymd') . Str::padLeft((string) ($index + 1), 6, '0'),
                    'currency_type' => $event['type'],
                    'type' => $event['action'],
                    'direction' => $direction,
                    'amount' => $amount,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'description' => $event['desc'],
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }

            // 6. 回写更新钱包统计主表
            $wallet->update([
                'balance' => $currentBalance,
                'coins' => $currentCoins,
                'total_recharge' => $totalRecharge,
                'total_spent' => $totalSpent,
                'total_earned_coins' => $totalEarnedCoins,
                'version' => $wallet->version + count($events),
                'last_activity_at' => now(),
            ]);

            $this->command->info("🎉 成功为用户 (ID: 13) 注入 25 条全场景流水！");
            $this->command->table(
                ['可用现金余额 (元)', '可用金币 (币)', '累计充值', '累计消费', '累计获得金币', '生成流水总条数'],
                [
                    [
                        "¥" . number_format($currentBalance, 2),
                        number_format($currentCoins) . " 币",
                        "¥" . number_format($totalRecharge, 2),
                        "¥" . number_format($totalSpent, 2),
                        number_format($totalEarnedCoins) . " 币",
                        count($events) . " 条 (每页10条，共3页)",
                    ]
                ]
            );
        });
    }
}
