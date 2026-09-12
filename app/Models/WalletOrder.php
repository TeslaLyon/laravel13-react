<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_no',
        'gateway_order_id',
        'gateway_pay_id',
        'payment_method',
        'amount',
        'really_amount',
        'status',
        'pay_url',
        'subject',
        'paid_at',
        'raw_callback',
    ];

    protected $casts = [
        'amount' => 'integer',
        'really_amount' => 'integer',
        'status' => 'integer',
        'paid_at' => 'datetime',
        'raw_callback' => 'array',
    ];

    // 订单状态常量定义
    public const STATUS_PENDING = 0; // 待支付
    public const STATUS_PAID = 1; // 支付成功
    public const STATUS_CLOSED = 2; // 已取消/失效

    /**
     * 关联归属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
