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
        'red_packet_code',
        'amount',
        'really_amount',
        'status',
        'pay_url',
        'subject',
        'admin_remark',
        'paid_at',
        'reviewed_by',
        'reviewed_at',
        'raw_callback',
    ];

    protected $casts = [
        'amount'        => 'integer',
        'really_amount' => 'integer',
        'status'        => 'integer',
        'paid_at'       => 'datetime',
        'reviewed_at'   => 'datetime',
        'raw_callback'  => 'array',
    ];

    // 订单状态常量定义
    public const STATUS_PENDING = 0; // 待支付 / 待审核
    public const STATUS_PAID    = 1; // 支付成功 / 已到账
    public const STATUS_CLOSED  = 2; // 已取消 / 已驳回 / 已失效

    // 支付方式常量定义
    public const METHOD_WECHAT            = 'wechat';
    public const METHOD_ALIPAY            = 'alipay';
    public const METHOD_ALIPAY_RED_PACKET = 'alipay_red_packet';

    /**
     * 关联归属用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联审核管理员
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * 是否为支付宝口令红包订单
     */
    public function isAlipayRedPacket(): bool
    {
        return $this->payment_method === self::METHOD_ALIPAY_RED_PACKET;
    }

    /**
     * 获取支付方式中文名称
     */
    public function paymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            self::METHOD_WECHAT, 'wxpay'   => '微信支付',
            self::METHOD_ALIPAY            => '支付宝',
            self::METHOD_ALIPAY_RED_PACKET => '支付宝口令红包',
            default                        => (string) $this->payment_method,
        };
    }
}
