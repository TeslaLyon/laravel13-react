<?php

namespace App\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class Wallet extends Model
{
    use HasFactory;

    // ==================== 状态常量定义 ====================
    public const STATUS_ACTIVE   = 1; // 正常
    public const STATUS_FROZEN   = 2; // 已冻结 (禁止出账，允许入账)
    public const STATUS_DISABLED = 3; // 已禁用 (禁止所有资金操作)

    // ==================== 资产类型常量 ====================
    public const CURRENCY_BALANCE        = 'balance';
    public const CURRENCY_COINS          = 'coins';
    public const CURRENCY_FROZEN_BALANCE = 'frozen_balance';
    public const CURRENCY_FROZEN_COINS   = 'frozen_coins';

    protected $fillable = [
        'user_id',
        'balance',
        'frozen_balance',
        'coins',
        'frozen_coins',
        'total_recharge',
        'total_withdrawn',
        'total_spent',
        'total_earned_coins',
        'status',
        'freeze_reason',
        'version',
        'checksum',
        'last_activity_at',
    ];

    protected $casts = [
        'user_id'            => 'integer',
        'balance'            => 'integer',
        'frozen_balance'     => 'integer',
        'coins'              => 'integer',
        'frozen_coins'       => 'integer',
        'total_recharge'     => 'integer',
        'total_withdrawn'    => 'integer',
        'total_spent'        => 'integer',
        'total_earned_coins' => 'integer',
        'status'             => \App\Enums\WalletStatus::class,
        'version'            => 'integer',
        'last_activity_at'   => 'datetime',
        'created_at'         => 'datetime',
        'updated_at'         => 'datetime',
    ];

    // ==================== 关联关系 ====================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('id');
    }

    // ==================== 状态判定辅助方法（兼容 Enum 与整数） ====================

    /**
     * 获取状态整型数值（抹平 Enum 与原生存根差异）
     */
    public function getStatusValue(): int
    {
        if ($this->status instanceof BackedEnum) {
            return (int) $this->status->value;
        }

        return (int) $this->status;
    }

    public function isActive(): bool
    {
        return $this->getStatusValue() === self::STATUS_ACTIVE;
    }

    public function isFrozen(): bool
    {
        return $this->getStatusValue() === self::STATUS_FROZEN;
    }

    public function isDisabled(): bool
    {
        return $this->getStatusValue() === self::STATUS_DISABLED;
    }

    // ==================== 核心资产操作方法 ====================

    /**
     * 现金余额变动（充值、消费、退款、管理员调账）
     *
     * @param int $amount 变动金额(分)，正数为增加，负数为扣减
     * @param string|BackedEnum $type 业务类型 (如: recharge, consume, refund)
     * @param string $description 账单明细描述
     * @param Model|null $source 关联业务对象 (如 Order, WalletOrder)
     * @param string|null $referenceId 外部三方支付单号
     * @param array $metadata 扩展元数据 (IP, UA 等)
     */
    public function changeBalance(
        int $amount,
        string|BackedEnum $type,
        string $description,
        ?Model $source = null,
        ?string $referenceId = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount === 0) {
            throw new InvalidArgumentException('变动金额不能为 0');
        }

        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        $transaction = DB::transaction(function () use ($amount, $typeValue, $description, $source, $referenceId, $metadata) {
            /** @var Wallet $wallet */
            $wallet = self::query()->lockForUpdate()->findOrFail($this->id);
            $wallet->validateOperableStatus($amount);

            $before = $wallet->balance;
            $after  = $before + $amount;

            if ($after < 0) {
                throw new RuntimeException("现金余额不足，当前可用: {$before} 分，尝试扣减: " . abs($amount) . " 分");
            }

            // 更新余额与累计统计
            $wallet->balance = $after;
            if ($amount > 0 && in_array($typeValue, ['recharge', 'deposit'])) {
                $wallet->total_recharge += $amount;
            } elseif ($amount < 0 && $typeValue === 'consume') {
                $wallet->total_spent += abs($amount);
            }

            $wallet->refreshSecurityState();
            $wallet->save();

            // 写入流水日志
            return $wallet->transactions()->create([
                'user_id'        => $wallet->user_id,
                'currency_type'  => self::CURRENCY_BALANCE,
                'type'           => $typeValue,
                'direction'      => $amount > 0 ? 1 : -1,
                'amount'         => abs($amount),
                'balance_before' => $before,
                'balance_after'  => $after,
                'source_type'    => $source ? get_class($source) : null,
                'source_id'      => $source?->getKey(),
                'reference_id'   => $referenceId,
                'description'    => $description,
                'metadata'       => $metadata,
            ]);
        });

        // 🌟 关键：同步刷新外部当前实例的内存数据，防止读取到脏数据
        $this->refresh();

        return $transaction;
    }

    /**
     * 虚拟金币变动（签到打卡、发帖奖励、金币消费、兑换）
     *  // TODO：测试total_earned_coins有没有问题
     */
    public function changeCoins(
        int $amount,
        string|BackedEnum $type,
        string $description,
        ?Model $source = null,
        ?string $referenceId = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount === 0) {
            throw new InvalidArgumentException('变动金币数不能为 0');
        }

        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        $transaction = DB::transaction(function () use ($amount, $typeValue, $description, $source, $referenceId, $metadata) {
            /** @var Wallet $wallet */
            $wallet = self::query()->lockForUpdate()->findOrFail($this->id);
            $wallet->validateOperableStatus($amount);

            $before = $wallet->coins;
            $after  = $before + $amount;

            if ($after < 0) {
                throw new RuntimeException("金币余额不足，当前拥有: {$before}，尝试消耗: " . abs($amount));
            }

            // 更新金币与累计获取统计（仅入账时累加历史获得总额）
            $wallet->coins = $after;
            if ($amount > 0) {
                $wallet->total_earned_coins += $amount;
            }

            $wallet->refreshSecurityState();
            $wallet->save();

            return $wallet->transactions()->create([
                'user_id'        => $wallet->user_id,
                'currency_type'  => self::CURRENCY_COINS,
                'type'           => $typeValue,
                'direction'      => $amount > 0 ? 1 : -1,
                'amount'         => abs($amount),
                'balance_before' => $before,
                'balance_after'  => $after,
                'source_type'    => $source ? get_class($source) : null,
                'source_id'      => $source?->getKey(),
                'reference_id'   => $referenceId,
                'description'    => $description,
                'metadata'       => $metadata,
            ]);
        });

        $this->refresh();

        return $transaction;
    }

    /**
     * 冻结现金余额（如：发起提现申请、担保交易预扣）
     */
    public function freezeBalance(
        int $amount,
        string|BackedEnum $type,
        string $description,
        ?Model $source = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('冻结金额必须大于 0');
        }

        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        $transaction = DB::transaction(function () use ($amount, $typeValue, $description, $source) {
            /** @var Wallet $wallet */
            $wallet = self::query()->lockForUpdate()->findOrFail($this->id);
            $wallet->validateOperableStatus(-$amount);

            if ($wallet->balance < $amount) {
                throw new RuntimeException("可用余额不足，无法执行冻结操作。当前可用: {$wallet->balance} 分");
            }

            $beforeFrozen = $wallet->frozen_balance;
            $wallet->balance        -= $amount;
            $wallet->frozen_balance += $amount;

            $wallet->refreshSecurityState();
            $wallet->save();

            // 🌟 修复：currency_type 为 frozen_balance 时，记录冻结账户对应的快照
            return $wallet->transactions()->create([
                'user_id'        => $wallet->user_id,
                'currency_type'  => self::CURRENCY_FROZEN_BALANCE,
                'type'           => $typeValue,
                'direction'      => 1, // 冻结账户资金增加 (+)
                'amount'         => $amount,
                'balance_before' => $beforeFrozen,
                'balance_after'  => $wallet->frozen_balance,
                'source_type'    => $source ? get_class($source) : null,
                'source_id'      => $source?->getKey(),
                'description'    => $description,
            ]);
        });

        $this->refresh();

        return $transaction;
    }

    /**
     * 解冻现金余额（如：提现审核拒绝，退回可用余额）
     */
    public function unfreezeBalance(
        int $amount,
        string|BackedEnum $type,
        string $description,
        ?Model $source = null
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('解冻金额必须大于 0');
        }

        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        $transaction = DB::transaction(function () use ($amount, $typeValue, $description, $source) {
            /** @var Wallet $wallet */
            $wallet = self::query()->lockForUpdate()->findOrFail($this->id);

            if ($wallet->frozen_balance < $amount) {
                throw new RuntimeException("冻结账户金额不足，当前已冻结: {$wallet->frozen_balance} 分");
            }

            $beforeBalance = $wallet->balance;
            $wallet->frozen_balance -= $amount;
            $wallet->balance        += $amount;

            $wallet->refreshSecurityState();
            $wallet->save();

            // 资金解冻回到可用余额
            return $wallet->transactions()->create([
                'user_id'        => $wallet->user_id,
                'currency_type'  => self::CURRENCY_BALANCE,
                'type'           => $typeValue,
                'direction'      => 1, // 可用余额增加 (+)
                'amount'         => $amount,
                'balance_before' => $beforeBalance,
                'balance_after'  => $wallet->balance,
                'source_type'    => $source ? get_class($source) : null,
                'source_id'      => $source?->getKey(),
                'description'    => $description,
            ]);
        });

        $this->refresh();

        return $transaction;
    }

    // ==================== 安全与校验内部方法 ====================

    /**
     * 校验钱包当前状态是否支持操作
     */
    protected function validateOperableStatus(int $amount): void
    {
        $status = $this->getStatusValue();

        if ($status === self::STATUS_DISABLED) {
            throw new RuntimeException('钱包账户已被禁用，禁止进行任何资金与金币操作。');
        }

        if ($status === self::STATUS_FROZEN && $amount < 0) {
            throw new RuntimeException("钱包账户已被系统冻结，禁止出账。原因: {$this->freeze_reason}");
        }
    }

    /**
     * 刷新版本号、最后活跃时间并重新计算 HMAC 校验和
     */
    protected function refreshSecurityState(): void
    {
        $this->version += 1;
        $this->last_activity_at = now();
        $this->checksum = $this->generateChecksum();
    }

    /**
     * 生成当前资产状态的 HMAC-SHA256 防篡改校验签名
     */
    public function generateChecksum(): string
    {
        $payload = implode('|', [
            $this->user_id,
            $this->balance,
            $this->frozen_balance,
            $this->coins,
            $this->frozen_coins,
            $this->version,
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * 校验钱包数据是否被外部直接篡改
     */
    public function verifyChecksum(): bool
    {
        if (empty($this->checksum)) {
            return false;
        }

        return hash_equals($this->checksum, $this->generateChecksum());
    }
}
