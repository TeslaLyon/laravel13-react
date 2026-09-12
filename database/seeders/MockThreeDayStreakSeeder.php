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

class MockThreeDayStreakSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1;

        $user = User::find($userId);
        if (!$user) {
            $this->command->error("未找到 ID 为 [{$userId}] 的用户，请先创建该用户。");
            return;
        }

        DB::transaction(function () use ($userId) {
            // 1. 清空当前用户的所有签到打卡、补签卡变动记录
            UserCheckIn::where('user_id', $userId)->delete();
            UserCheckInCardLog::where('user_id', $userId)->delete();

            // 2. 初始化钱包并清空旧流水
            $wallet = Wallet::firstOrCreate(['user_id' => $userId]);
            WalletTransaction::where('wallet_id', $wallet->id)->delete();

            // 3. 动态获取前天与昨天的日期
            $today = Carbon::today();
            $signedDates = [
                $today->copy()->subDays(2)->toDateString(), // 连签第 1 天 (前天)
                $today->copy()->subDays(1)->toDateString(), // 连签第 2 天 (昨天)
            ];

            $totalCoins = 0;
            $baseCoinPerDay = 10;

            // 4. 写入前两天的签到记录与流水
            foreach ($signedDates as $index => $date) {
                $dayStreak = $index + 1;
                $balanceBefore = $totalCoins;
                $totalCoins += $baseCoinPerDay;

                $checkIn = UserCheckIn::create([
                    'user_id' => $userId,
                    'check_in_date' => $date,
                    'type' => 'normal',
                    'continuous_days_snapshot' => $dayStreak,
                    'total_days_snapshot' => $dayStreak,
                    'reward_coins' => $baseCoinPerDay,
                    'bonus_coins' => 0,
                    'extra_rewards' => null,
                ]);

                // 写入交易明细，满足 PostgreSQL 约束
                WalletTransaction::create([
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
                    'trx_no' => 'TRX' . date('YmdHis') . strtoupper(Str::random(8)),
                    'direction' => 1, // 1: 资金流入
                    'amount' => $baseCoinPerDay,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $totalCoins,
                    'type' => 'check_in',
                    'description' => "每日签到打卡 ({$date})",
                    'source_type' => UserCheckIn::class,
                    'source_id' => $checkIn->id,
                ]);
            }

            // 5. 初始化连续签到主状态表（连签 2 天，初始赠送 1 张补签卡）
            UserCheckInStat::updateOrCreate(
                ['user_id' => $userId],
                [
                    'last_check_in_date' => $today->copy()->subDays(1)->toDateString(),
                    'continuous_days' => 2,
                    'longest_continuous_days' => 2,
                    'total_days' => 2,
                    'make_up_cards' => 1,
                    'total_make_up_cards_earned' => 1,
                    'month_make_up_count' => 0,
                ]
            );

            // 6. 钱包金币严格同步为 20 金币（2天 x 10金币）
            $wallet->coins = $totalCoins;
            if (array_key_exists('balance', $wallet->getAttributes())) {
                $wallet->balance = $totalCoins;
            }
            $wallet->save();

            $this->command->info("✅ 3天连签测试数据就绪！已模拟签到前 2 天，今日处于待签到状态。");
        });
    }
}
