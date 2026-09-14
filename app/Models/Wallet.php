<?php

namespace App\Models;

use BackedEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

class Wallet extends Model
{
    use HasFactory;

    // ==================== 状态常量定义 ====================
    public const STATUS_ACTIVE = 1; // 正常
    public const STATUS_FROZEN = 2; // 已冻结 (禁止出账，允许入账)
    public const STATUS_DISABLED = 3; // 已禁用 (禁止所有资金操作)

    // ==================== 资产类型常量 ====================
    public const CURRENCY_BALANCE = 'balance';
    public const CURRENCY_COINS = 'coins';
    public const CURRENCY_FROZEN_BALANCE = 'frozen_balance';
    public const CURRENCY_FROZEN_COINS = 'frozen_coins';

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
        'user_id' => 'integer',
        'balance' => 'integer',
        'frozen_balance' => 'integer',
        'coins' => 'integer',
        'frozen_coins' => 'integer',
        'total_recharge' => 'integer',
        'total_withdrawn' => 'integer',
        'total_spent' => 'integer',
        'total_earned_coins' => 'integer',
        'status' => \App\Enums\WalletStatus::class,
        'version' => 'integer',
        'last_activity_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
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

    // ==================== 状态判定辅助方法 ====================

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

    /**
     * 校验钱包当前状态是否支持指定变动
     */
    public function validateOperableStatus(int $amount): void
    {
        $status = $this->getStatusValue();

        if ($status === self::STATUS_DISABLED) {
            throw new RuntimeException('钱包账户已被禁用，禁止进行任何资金与金币操作。');
        }

        if ($status === self::STATUS_FROZEN && $amount < 0) {
            throw new RuntimeException("钱包账户已被系统冻结，禁止出账。原因: {$this->freeze_reason}");
        }
    }

    // ==================== 安全与校验和方法 ====================

    /**
     * 刷新模型内存中的版本号并生成新的防篡改校验和（不执行 save）
     */
    public function refreshSecurityState(): void
    {
        $this->version += 1;
        $this->last_activity_at = now();
        $this->checksum = $this->generateChecksum();
    }

    /**
     * 🌟 静态纯函数（命名为 makeChecksum）：根据显式传入的 6 个资产快照生成 HMAC-SHA256 签名
     */
    public static function makeChecksum(
        int|string $userId,
        float|int $balance,
        float|int $frozenBalance,
        int $coins,
        int $frozenCoins,
        int $version
    ): string {
        $payload = implode('|', [
            $userId,
            $balance,
            $frozenBalance,
            $coins,
            $frozenCoins,
            $version,
        ]);

        return hash_hmac('sha256', $payload, (string) config('app.key'));
    }

    /**
     * 🌟 实例无参方法（命名为 generateChecksum）：根据当前模型属性自动计算校验和
     */
    public function generateChecksum(): string
    {
        return self::makeChecksum(
            $this->user_id,
            $this->balance ?? 0,
            $this->frozen_balance ?? 0,
            $this->coins ?? 0,
            $this->frozen_coins ?? 0,
            $this->version ?? 0
        );
    }

    /**
     * 实例便捷别名方法
     */
    public function calculateCurrentChecksum(): string
    {
        return $this->generateChecksum();
    }

    /**
     * 检验当前钱包记录是否被外部篡改
     */
    public function verifyIntegrity(): bool
    {
        if (empty($this->checksum)) {
            return false;
        }

        return hash_equals($this->generateChecksum(), (string) $this->checksum);
    }

    public function verifyChecksum(): bool
    {
        return $this->verifyIntegrity();
    }
}
