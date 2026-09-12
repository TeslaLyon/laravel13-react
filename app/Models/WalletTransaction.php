<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'user_id',
        'trx_no',
        'currency_type',
        'type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'source_type',
        'source_id',
        'reference_id',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'direction' => 'integer',
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            // 运行期安全检测：存在枚举则转为枚举对象，不存在则作为标准字符串处理
            'type' => class_exists(\App\Enums\TransactionType::class)
                ? \App\Enums\TransactionType::class
                : 'string',
        ];
    }

    /**
     * 自动生成唯一流水号 (如果未显式提供)
     */
    protected static function booted(): void
    {
        static::creating(function (WalletTransaction $transaction) {
            if (empty($transaction->trx_no)) {
                $transaction->trx_no = 'TRX' . date('YmdHis') . strtoupper(Str::random(8));
            }
        });
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 获取触发该流水的业务源对象（如 WalletOrder, UserCheckIn 等）
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
