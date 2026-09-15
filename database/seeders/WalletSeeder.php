<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WalletSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        // 1. 创建固定演示主账号（补充 nickname 字段）
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => '演示账号',
                'nickname' => 'DemoUser', // 补充非空字段
                'password' => Hash::make('password123'),
            ]
        );

        // 为该用户创建/重置钱包
        $wallet = Wallet::updateOrCreate(
            ['user_id' => $demoUser->id],
            [
                'balance' => 0,
                'frozen_balance' => 50.00, // 模拟 50 元提现中冻结金额
                'total_recharge' => 0,
                'total_withdrawn' => 0,
                'total_spent' => 0,
                'status' => WalletStatus::ACTIVE,
                'version' => 0,
                'last_activity_at' => now(),
            ]
        );

        // 清理旧的关联测试流水
        $wallet->transactions()->delete();

        // 2. 模拟一组真实的连续交易记录
        $timelineEvents = [
            [
                'type' => TransactionType::RECHARGE,
                'amount' => 1000.00,
                'desc' => '微信支付快速充值',
                'created_at' => now()->subDays(10)->setHour(10)->setMinute(15),
            ],
            [
                'type' => TransactionType::CONSUME,
                'amount' => -199.00,
                'desc' => '购买年度专业版会员服务',
                'created_at' => now()->subDays(9)->setHour(14)->setMinute(30),
            ],
            [
                'type' => TransactionType::CONSUME,
                'amount' => -45.50,
                'desc' => 'API 接口调用额度抵扣',
                'created_at' => now()->subDays(8)->setHour(9)->setMinute(12),
            ],
            [
                'type' => TransactionType::RECHARGE,
                'amount' => 500.00,
                'desc' => '支付宝充值入账',
                'created_at' => now()->subDays(7)->setHour(16)->setMinute(45),
            ],
            [
                'type' => TransactionType::WITHDRAW,
                'amount' => -200.00,
                'desc' => '用户发起银行卡余额提现',
                'created_at' => now()->subDays(6)->setHour(11)->setMinute(20),
            ],
            [
                'type' => TransactionType::CONSUME,
                'amount' => -88.00,
                'desc' => '购买开发者资源包',
                'created_at' => now()->subDays(5)->setHour(20)->setMinute(05),
            ],
            [
                'type' => TransactionType::REFUND,
                'amount' => 88.00,
                'desc' => '资源包购买失败原路退款',
                'created_at' => now()->subDays(5)->setHour(20)->setMinute(10),
            ],
            [
                'type' => TransactionType::CONSUME,
                'amount' => -320.00,
                'desc' => '云存储容量扩容续费',
                'created_at' => now()->subDays(3)->setHour(15)->setMinute(33),
            ],
            [
                'type' => TransactionType::RECHARGE,
                'amount' => 2000.00,
                'desc' => '企业对公转账充值',
                'created_at' => now()->subDays(2)->setHour(10)->setMinute(00),
            ],
            [
                'type' => TransactionType::CONSUME,
                'amount' => -699.00,
                'desc' => '续费企业高级支持席位',
                'created_at' => now()->subDays(1)->setHour(18)->setMinute(40),
            ],
            [
                'type' => TransactionType::CONSUME,
                'amount' => -12.50,
                'desc' => '短信验证码增量发送费',
                'created_at' => now()->subHours(3),
            ],
            [
                'type' => TransactionType::RECHARGE,
                'amount' => 300.00,
                'desc' => '活动奖励金充值',
                'created_at' => now()->subMinutes(25),
            ],
        ];

        // 3. 逐条计算变动前后结余并录入流水
        $currentBalance = 0.00;
        $totalRecharge = 0.00;
        $totalWithdrawn = 0.00;
        $totalSpent = 0.00;
        $version = 0;

        foreach ($timelineEvents as $item) {
            $amount = $item['amount'];
            $balanceBefore = $currentBalance;
            $balanceAfter = round($currentBalance + $amount, 2);
            $currentBalance = $balanceAfter;
            $version++;

            if ($item['type'] === TransactionType::RECHARGE || $item['type'] === TransactionType::REFUND) {
                $totalRecharge += $amount;
            } elseif ($item['type'] === TransactionType::WITHDRAW) {
                $totalWithdrawn += abs($amount);
            } elseif ($item['type'] === TransactionType::CONSUME) {
                $totalSpent += abs($amount);
            }

            WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $demoUser->id,
                'type' => $item['type'],
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'reference_id' => 'ORD' . date('Ymd') . strtoupper(Str::random(10)),
                'description' => $item['desc'],
                'created_at' => $item['created_at'],
                'updated_at' => $item['created_at'],
            ]);
        }

        // 4. 将最终计算出来的真实统计与余额回写至钱包
        $wallet->balance = $currentBalance;
        $wallet->total_recharge = $totalRecharge;
        $wallet->total_withdrawn = $totalWithdrawn;
        $wallet->total_spent = $totalSpent;
        $wallet->version = $version;
        $wallet->last_activity_at = now();
        $wallet->checksum = $wallet->generateChecksum();
        $wallet->save();

        // 5. 创建辅助测试账号
        $this->createSecondaryTestUsers();
    }

    private function createSecondaryTestUsers(): void
    {
        // 冻结账户测试用户
        $frozenUser = User::firstOrCreate(
            ['email' => 'frozen@example.com'],
            [
                'name' => '冻结测试用户',
                'nickname' => 'FrozenUser',
                'password' => Hash::make('password123'),
            ]
        );
        $frozenWallet = Wallet::updateOrCreate(
            ['user_id' => $frozenUser->id],
            [
                'balance' => 888.00,
                'frozen_balance' => 888.00,
                'status' => WalletStatus::FROZEN,
                'freeze_reason' => '检测到高频异常异地登录请求，系统自动风控拦截',
                'version' => 1,
            ]
        );
        $frozenWallet->checksum = $frozenWallet->generateChecksum();
        $frozenWallet->save();

        // 新用户
        $newUser = User::firstOrCreate(
            ['email' => 'newbie@example.com'],
            [
                'name' => '新注册用户',
                'nickname' => 'NewbieUser',
                'password' => Hash::make('password123'),
            ]
        );
        $newWallet = Wallet::updateOrCreate(
            ['user_id' => $newUser->id],
            [
                'balance' => 0.00,
                'frozen_balance' => 0.00,
                'status' => WalletStatus::ACTIVE,
                'version' => 0,
            ]
        );
        $newWallet->checksum = $newWallet->generateChecksum();
        $newWallet->save();
    }
}
