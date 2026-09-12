<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Enums\WalletStatus;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class WalletService
{
    public function getOrCreateWallet(User $user): Wallet
    {
        return $user->wallet ?? $user->wallet()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'frozen_balance' => 0,
                'status' => WalletStatus::ACTIVE,
                'version' => 0,
            ]
        );
    }

    /**
     * 充值 / 入账 (单位：元)
     */
    public function deposit(User $user, float $amount, string $description = '账户充值', ?string $referenceId = null, array $metadata = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('充值金额必须大于 0');
        }

        return DB::transaction(function () use ($user, $amount, $description, $referenceId, $metadata) {
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

            if (!$wallet) {
                $wallet = $this->getOrCreateWallet($user);
                $wallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();
            }

            if ($wallet->status !== WalletStatus::ACTIVE) {
                throw new RuntimeException('钱包状态异常，无法完成充值');
            }

            $amountCents = (int) bcmul((string) $amount, '100', 0);
            $rawAttributes = $wallet->getAttributes();

            $beforeCents = (int) ($rawAttributes['balance'] ?? 0);
            $afterCents = $beforeCents + $amountCents;

            // 更新钱包属性（原始“分”单位与版本号）
            $wallet->setRawAttributes(array_merge($rawAttributes, [
                'balance' => $afterCents,
                'total_recharge' => ((int) ($rawAttributes['total_recharge'] ?? 0)) + $amountCents,
                'version' => ((int) ($rawAttributes['version'] ?? 0)) + 1,
                'last_activity_at' => now(),
            ]));
            $wallet->checksum = $wallet->generateChecksum();
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => TransactionType::DEPOSIT,
                'amount' => $amount, // MoneyCast 自动转为分
                'balance_before' => round($beforeCents / 100, 2),
                'balance_after' => round($afterCents / 100, 2),
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * 扣款 / 消费支出 (单位：元)
     */
    public function withdraw(User $user, float $amount, string $description = '账户扣款', ?string $referenceId = null, array $metadata = []): WalletTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('扣款金额必须大于 0');
        }

        return DB::transaction(function () use ($user, $amount, $description, $referenceId, $metadata) {
            /** @var Wallet $wallet */
            $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->firstOrFail();

            if ($wallet->status !== WalletStatus::ACTIVE) {
                throw new RuntimeException('钱包已被冻结或禁用');
            }

            $amountCents = (int) bcmul((string) $amount, '100', 0);
            $rawAttributes = $wallet->getAttributes();
            $beforeCents = (int) ($rawAttributes['balance'] ?? 0);

            if ($beforeCents < $amountCents) {
                throw new RuntimeException('钱包可用余额不足');
            }

            $afterCents = $beforeCents - $amountCents;

            $wallet->setRawAttributes(array_merge($rawAttributes, [
                'balance' => $afterCents,
                'total_spent' => ((int) ($rawAttributes['total_spent'] ?? 0)) + $amountCents,
                'version' => ((int) ($rawAttributes['version'] ?? 0)) + 1,
                'last_activity_at' => now(),
            ]));
            $wallet->checksum = $wallet->generateChecksum();
            $wallet->save();

            return WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'user_id' => $user->id,
                'type' => TransactionType::PAYMENT,
                'amount' => -$amount,
                'balance_before' => round($beforeCents / 100, 2),
                'balance_after' => round($afterCents / 100, 2),
                'reference_id' => $referenceId,
                'description' => $description,
                'metadata' => $metadata,
            ]);
        });
    }
}
