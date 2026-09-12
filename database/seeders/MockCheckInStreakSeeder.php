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

class MockCheckInStreakSeeder extends Seeder
{
    public function run(): void
    {
        $userId = 1;

        $user = User::find($userId);
        if (!$user) {
            $this->command->error("未找到 ID 为 [{$userId}] 的用户。");
            return;
        }

        DB::transaction(function () use ($userId) {
            // 1. 清理该用户现存打卡记录、补签卡日志及流水
            UserCheckIn::where('user_id', $userId)->delete();
            UserCheckInCardLog::where('user_id', $userId)->delete();

            $wallet = Wallet::firstOrCreate(['user_id' => $userId]);
            WalletTransaction::where('wallet_id', $wallet->id)->delete();

            // 2. 动态生成 6 天打卡数据，留出“前天 (subDays(2))”作为待补签断点
            $today = Carbon::today();
            $targetMakeUpDate = $today->copy()->subDays(2)->toDateString();

            $signedDates = [
                $today->copy()->subDays(6)->toDateString(),
                $today->copy()->subDays(5)->toDateString(),
                $today->copy()->subDays(4)->toDateString(),
                $today->copy()->subDays(3)->toDateString(),
                // subDays(2) 故意缺失，待用户点击补签
                $today->copy()->subDays(1)->toDateString(),
                $today->toDateString(),
            ];

            $totalCoins = 0;
            $baseCoinPerDay = 10;

            foreach ($signedDates as $date) {
                $balanceBefore = $totalCoins;
                $totalCoins += $baseCoinPerDay;

                $checkIn = UserCheckIn::create([
                    'user_id' => $userId,
                    'check_in_date' => $date,
                    'type' => 'normal',
                    'continuous_days_snapshot' => 1,
                    'total_days_snapshot' => 1,
                    'reward_coins' => $baseCoinPerDay,
                    'bonus_coins' => 0,
                    'extra_rewards' => null,
                ]);

                WalletTransaction::create([
                    'user_id' => $userId,
                    'wallet_id' => $wallet->id,
                    'trx_no' => 'TRX' . date('YmdHis') . strtoupper(Str::random(8)),
                    'direction' => 1, // smallint 整数 1: 进账
                    'amount' => $baseCoinPerDay,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $totalCoins,
                    'type' => 'check_in',
                    'description' => "每日签到打卡 ({$date})",
                    'source_type' => UserCheckIn::class,
                    'source_id' => $checkIn->id,
                ]);
            }

            // 3. 初始连签状态设为 2 天 (昨日与今日)
            UserCheckInStat::updateOrCreate(
                ['user_id' => $userId],
                [
                    'last_check_in_date' => $today->toDateString(),
                    'continuous_days' => 2,
                    'longest_continuous_days' => 4,
                    'total_days' => count($signedDates),
                    'make_up_cards' => 2,
                    'total_make_up_cards_earned' => 2,
                    'month_make_up_count' => 0,
                ]
            );

            // 4. 钱包余额严格同步为 60 金币
            $wallet->coins = $totalCoins;
            if (array_key_exists('balance', $wallet->getAttributes())) {
                $wallet->balance = $totalCoins;
            }
            $wallet->save();

            $this->command->info("🎯 模拟断签数据就绪！请在浏览器日历中点击补签日期：[{$targetMakeUpDate}]");
        });
    }
}
