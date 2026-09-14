<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionType: string
{
    // ==================== 核心社区与互动业务 ====================
    case CHECK_IN = 'check_in';        // 每日签到
    case MAKE_UP_REWARD = 'make_up_reward';  // 补签打卡奖励
    case REWARD = 'reward';          // 社区打赏/点赞奖励

    // ==================== 资金与电商流转业务 ====================
    case RECHARGE = 'recharge';        // 账户充值
    case CONSUME = 'consume';         // 业务消费/道具购买
    case WITHDRAW = 'withdraw';        // 提现支出
    case REFUND = 'refund';          // 交易退款

    // ==================== 账户安全与系统调账 ====================
    case FREEZE = 'freeze';          // 资金冻结
    case UNFREEZE = 'unfreeze';        // 资金解冻
    case ADMIN_ADJUST = 'admin_adjust';    // 管理员人工调账

    /**
     * 获取业务类型的中文描述
     */
    public function label(): string
    {
        return match ($this) {
            self::CHECK_IN => '每日签到',
            self::MAKE_UP_REWARD => '补签打卡',
            self::REWARD => '社区打赏',
            self::RECHARGE => '账户充值',
            self::CONSUME => '业务消费',
            self::WITHDRAW => '提现支出',
            self::REFUND => '交易退款',
            self::FREEZE => '资金冻结',
            self::UNFREEZE => '资金解冻',
            self::ADMIN_ADJUST => '系统调账',
        };
    }

    /**
     * 判断当前类型是否为入账（增加资产）
     */
    public function isIncome(): bool
    {
        return match ($this) {
            self::CHECK_IN,
            self::MAKE_UP_REWARD,
            self::REWARD,
            self::RECHARGE,
            self::REFUND => true,

            self::CONSUME,
            self::WITHDRAW,
            self::FREEZE,
            self::UNFREEZE,
            self::ADMIN_ADJUST => false,
        };
    }

    /**
     * 前端 Badge 徽章样式类名 (Tailwind CSS)
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::CHECK_IN,
            self::MAKE_UP_REWARD,
            self::RECHARGE,
            self::REWARD,
            self::REFUND => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',

            self::CONSUME,
            self::WITHDRAW => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',

            self::FREEZE,
            self::UNFREEZE => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',

            self::ADMIN_ADJUST => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
        };
    }

    /**
     * 获取所有枚举字符串值的数组列表
     *
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * 获取供前端下拉筛选框（Select Options）使用的键值对列表
     *
     * @return array<int, array{label: string, value: string}>
     */
    public static function options(): array
    {
        return array_map(fn(self $type) => [
            'label' => $type->label(),
            'value' => $type->value,
        ], self::cases());
    }
}
