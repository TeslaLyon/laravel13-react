<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserCheckIn;
use App\Models\UserCheckInCardLog;
use App\Models\UserCheckInStat;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MockFourteenDayStreakSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1;

        $user = User::find($userId);
        if (!$user) {
            $this->command->error("未找到 ID 为 [{$userId}] 的用户，请先确保该用户存在。");
            return;
        }

        DB::transaction(function () use ($userId) {
            // 1. 清理该用户所有的历史签到、补签卡流水
            UserCheckIn::where('user_id', $userId)->delete();
            UserCheckInCardLog::where('user_id', $userId)->delete();

            // 2. 初始化钱包并清理旧交易明细
            $wallet = Wallet::firstOrCreate(['user_id' => $userId]);
            WalletTransaction::where('wallet_id', $wallet->id)->delete();

            // 3. 动态生成前 13 天的连续日期序列 (从 13 天前到昨天)
            $today = Carbon::today();
            $totalCoins = 0;
            $baseCoinPerDay = 10;
            $initialCards = 1; // 初始赠送 1 张补签卡
            $earnedCards = 0;

            // 🌟 修复：补充 balance_before 字段 (从 0 到 1)
            UserCheckInCardLog::create([
                'user_id' => $userId,
                'action' => 'initial_gift',
                'change_count' => 1,
                'balance_before' => 0,
                'balance_after' => 1,
                'description' => '新用户初始化赠送补签卡',
            ]);

            for ($i = 13; $i >= 1; $i--) {
                $dayStreak = 14 - $i; // 当前打卡对应的连签天数 (1 ~ 13)
                $dateStr = $today->copy()->subDays($i)->toDateString();

                $bonusCoins = 0;
                $cardReward = 0;
                $extraRewards = null;

                // 模拟第 3 天里程碑结算 (+20 金币)
                if ($dayStreak === 3) {
                    $bonusCoins = 20;
                    $extraRewards = ['title' => '连签3天宝箱', 'coins' => 20, 'cards' => 0];
                }

                // 模拟第 7 天里程碑结算 (+50 金币, +1 补签卡)
                if ($dayStreak === 7) {
                    $bonusCoins = 50;
                    $cardReward = 1;
                    $earnedCards += 1;
                    $extraRewards = ['title' => '连签7天进阶礼包', 'coins' => 50, 'cards' => 1];
                }

                $dayTotalCoins = $baseCoinPerDay + $bonusCoins;
                $balanceBefore = $totalCoins;
                $totalCoins += $dayTotalCoins;

                // 写入打卡记录
                $checkIn = UserCheckIn::create([
                    'user_id' => $userId,
                    'check_in_date' => $dateStr,
                    'type' => 'normal',
                    'continuous_days_snapshot' => $dayStreak,
                    'total_days_snapshot' => $dayStreak,
                    'reward_coins' => $baseCoinPerDay,
                    'bonus_coins' => $bonusCoins,
                    'extra_rewards' => $extraRewards,
                ]);

                // 写入资金流水
                WalletTransaction::create([
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
                    'trx_no' => 'TRX' . date('YmdHis') . strtoupper(Str::random(8)),
                    'direction' => 1, // 1: 进账 (smallint)
                    'amount' => $dayTotalCoins,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $totalCoins,
                    'type' => 'check_in',
                    'description' => "每日签到打卡 ({$dateStr})" . ($bonusCoins > 0 ? " (含连签{$dayStreak}天奖励+{$bonusCoins})" : ''),
                    'source_type' => UserCheckIn::class,
                    'source_id' => $checkIn->id,
                ]);

                // 🌟 修复：若第 7 天赠送了补签卡，写入日志时补齐 balance_before (从 1 到 2)
                if ($cardReward > 0) {
                    UserCheckInCardLog::create([
                        'user_id' => $userId,
                        'action' => 'milestone_reward',
                        'change_count' => $cardReward,
                        'balance_before' => $initialCards,
                        'balance_after' => $initialCards + $earnedCards,
                        'description' => "连续打卡达成 {$dayStreak} 天阶梯礼包赠送",
                        'source_type' => UserCheckIn::class,
                        'source_id' => $checkIn->id,
                    ]);
                }
            }

            // 4. 初始化用户连签统计主表（连签 13 天，持有 2 张卡）
            UserCheckInStat::updateOrCreate(
                ['user_id' => $userId],
                [
                    'last_check_in_date' => $today->copy()->subDays(1)->toDateString(),
                    'continuous_days' => 13,
                    'longest_continuous_days' => 13,
                    'total_days' => 13,
                    'make_up_cards' => $initialCards + $earnedCards,
                    'total_make_up_cards_earned' => $initialCards + $earnedCards,
                    'month_make_up_count' => 0,
                ]
            );

            // 5. 同步钱包余额为 200 金币
            $wallet->coins = $totalCoins;
            if (array_key_exists('balance', $wallet->getAttributes())) {
                $wallet->balance = $totalCoins;
            }
            $wallet->save();

            $this->command->info("✅ 14天连签测试数据就绪！已模拟签到前 13 天，今日点击即可触发 14 天豪华大奖。");
        });
    }
}
