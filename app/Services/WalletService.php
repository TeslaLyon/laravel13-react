<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    /**
     * 获取或初始化用户钱包主体
     */
    public function getOrCreateWallet(User $user): Wallet
    {
        $wallet = $user->wallet ?? Wallet::where('user_id', $user->id)->first();

        if (!$wallet) {
            $wallet = new Wallet([
                'user_id' => $user->id,
                'balance' => 0,
                'frozen_balance' => 0,
                'coins' => 0,
                'frozen_coins' => 0,
                'total_recharge' => 0,
                'total_withdrawn' => 0,
                'total_spent' => 0,
                'total_earned_coins' => 0,
                'status' => WalletStatus::ACTIVE,
                'version' => 1,
                'last_activity_at' => now(),
            ]);

            // 使用无参实例方法计算初始签名
            $wallet->checksum = $wallet->generateChecksum();
            $wallet->save();
        }

        return $wallet;
    }

    /**
     * 核心资产变动方法（单位：分）
     */
    public function changeBalance(
        Wallet|int $wallet,
        int $amountCents,
        string|BackedEnum $type,
        string $description,
        ?Model $source = null,
        ?string $referenceId = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amountCents === 0) {
            throw new InvalidArgumentException('变动金额不能为 0');
        }

        $walletId = $wallet instanceof Wallet ? $wallet->id : $wallet;
        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        return DB::transaction(function () use ($walletId, $amountCents, $typeValue, $description, $source, $referenceId, $metadata) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
            $lockedWallet->validateOperableStatus($amountCents);

            $before = $lockedWallet->balance;
            $after = $before + $amountCents;

            if ($after < 0) {
                throw new RuntimeException("现金余额不足，当前可用: {$before} 分，尝试扣减: " . abs($amountCents) . " 分");
            }

            // 更新余额与累计指标
            $lockedWallet->balance = $after;
            if ($amountCents > 0 && in_array($typeValue, ['recharge', 'deposit'])) {
                $lockedWallet->total_recharge += $amountCents;
            } elseif ($amountCents < 0 && in_array($typeValue, ['consume', 'payment'])) {
                $lockedWallet->total_spent += abs($amountCents);
            }

            // 内部自动自增 version 并通过 generateChecksum() 刷新签名
            $lockedWallet->refreshSecurityState();
            $lockedWallet->save();

            // 写入流水日志
            return $lockedWallet->transactions()->create([
                'user_id' => $lockedWallet->user_id,
                'currency_type' => Wallet::CURRENCY_BALANCE,
                'type' => $typeValue,
                'direction' => $amountCents > 0 ? 1 : -1,
                'amount' => abs($amountCents),
                'balance_before' => $before,
                'balance_after' => $after,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * 兼容接口：充值 (以“元”为单位)
     */
    public function deposit(
        User $user,
        float $amount,
        string $description = '账户充值',
        ?string $referenceId = null,
        array $metadata = []
    ): WalletTransaction {
        if ($amount <= 0) {
            throw new InvalidArgumentException('充值金额必须大于 0');
        }

        $wallet = $this->getOrCreateWallet($user);
        $amountCents = (int) bcmul((string) $amount, '100', 0);

        return $this->changeBalance(
            wallet: $wallet,
            amountCents: $amountCents,
            type: TransactionType::RECHARGE,
            description: $description,
            source: null,
            referenceId: $referenceId,
            metadata: $metadata
        );
    }

    /**
     * 社区金币变动
     */
    public function changeCoins(
        Wallet|int $wallet,
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

        $walletId = $wallet instanceof Wallet ? $wallet->id : $wallet;
        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        return DB::transaction(function () use ($walletId, $amount, $typeValue, $description, $source, $referenceId, $metadata) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
            $lockedWallet->validateOperableStatus($amount);

            $before = $lockedWallet->coins;
            $after = $before + $amount;

            if ($after < 0) {
                throw new RuntimeException("金币余额不足，当前拥有: {$before}，尝试消耗: " . abs($amount));
            }

            $lockedWallet->coins = $after;
            if ($amount > 0) {
                $lockedWallet->total_earned_coins += $amount;
            }

            $lockedWallet->refreshSecurityState();
            $lockedWallet->save();

            return $lockedWallet->transactions()->create([
                'user_id' => $lockedWallet->user_id,
                'currency_type' => Wallet::CURRENCY_COINS,
                'type' => $typeValue,
                'direction' => $amount > 0 ? 1 : -1,
                'amount' => abs($amount),
                'balance_before' => $before,
                'balance_after' => $after,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * 冻结现金余额（单位：分）
     */
    public function freezeBalance(
        Wallet|int $wallet,
        int $amountCents,
        string|BackedEnum $type,
        string $description,
        ?Model $source = null
    ): WalletTransaction {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('冻结金额必须大于 0');
        }

        $walletId = $wallet instanceof Wallet ? $wallet->id : $wallet;
        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        return DB::transaction(function () use ($walletId, $amountCents, $typeValue, $description, $source) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();
            $lockedWallet->validateOperableStatus(-$amountCents);

            if ($lockedWallet->balance < $amountCents) {
                throw new RuntimeException("可用余额不足，无法执行冻结。当前可用: {$lockedWallet->balance} 分");
            }

            $beforeFrozen = $lockedWallet->frozen_balance;
            $lockedWallet->balance -= $amountCents;
            $lockedWallet->frozen_balance += $amountCents;

            $lockedWallet->refreshSecurityState();
            $lockedWallet->save();

            return $lockedWallet->transactions()->create([
                'user_id' => $lockedWallet->user_id,
                'currency_type' => Wallet::CURRENCY_FROZEN_BALANCE,
                'type' => $typeValue,
                'direction' => 1,
                'amount' => $amountCents,
                'balance_before' => $beforeFrozen,
                'balance_after' => $lockedWallet->frozen_balance,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'description' => $description,
            ]);
        });
    }

    /**
     * 解冻现金余额（单位：分）
     */
    public function unfreezeBalance(
        Wallet|int $wallet,
        int $amountCents,
        string|BackedEnum $type,
        string $description,
        ?Model $source = null
    ): WalletTransaction {
        if ($amountCents <= 0) {
            throw new InvalidArgumentException('解冻金额必须大于 0');
        }

        $walletId = $wallet instanceof Wallet ? $wallet->id : $wallet;
        $typeValue = $type instanceof BackedEnum ? (string) $type->value : (string) $type;

        return DB::transaction(function () use ($walletId, $amountCents, $typeValue, $description, $source) {
            /** @var Wallet $lockedWallet */
            $lockedWallet = Wallet::where('id', $walletId)->lockForUpdate()->firstOrFail();

            if ($lockedWallet->frozen_balance < $amountCents) {
                throw new RuntimeException("冻结账户金额不足，当前已冻结: {$lockedWallet->frozen_balance} 分");
            }

            $beforeBalance = $lockedWallet->balance;
            $lockedWallet->frozen_balance -= $amountCents;
            $lockedWallet->balance += $amountCents;

            $lockedWallet->refreshSecurityState();
            $lockedWallet->save();

            return $lockedWallet->transactions()->create([
                'user_id' => $lockedWallet->user_id,
                'currency_type' => Wallet::CURRENCY_BALANCE,
                'type' => $typeValue,
                'direction' => 1,
                'amount' => $amountCents,
                'balance_before' => $beforeBalance,
                'balance_after' => $lockedWallet->balance,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->getKey(),
                'description' => $description,
            ]);
        });
    }
}
