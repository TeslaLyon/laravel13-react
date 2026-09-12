<?php

namespace App\Models;

use App\Enums\DeliveryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDetail extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性白名单
     */
    protected $fillable = [
        'product_id',
        'specs',
        'content',
        'delivery_type',
        'delivery_content',
    ];

    /**
     * 属性类型转换器
     */
    protected $casts = [
        'specs' => 'array',
        'delivery_type' => DeliveryType::class,
        'delivery_content' => 'array',
    ];

    /**
     * 默认在序列化为 JSON / 数组时隐藏敏感发货资产
     * 确保未支付用户查阅详情 API 时绝对不会泄露网盘密码、卡密或直链
     */
    protected $hidden = [
        'delivery_content',
    ];

    /* =========================================================================
     *  关联关系定义 (Relationships)
     * ========================================================================= */

    /**
     * 反向关联商品主表 (BelongsTo 关系)
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /* =========================================================================
     *  业务辅助方法
     * ========================================================================= */

    /**
     * 获取脱敏后的交付信息说明（供未支付用户在详情页了解发货形态）
     */
    public function getDeliverySummary(): string
    {
        return match ($this->delivery_type) {
            DeliveryType::NETDISK => '购买后自动展示百度网盘/夸克网盘链接与解压密码',
            DeliveryType::CARD_KEY => '支付成功后系统自动发放激活授权码',
            DeliveryType::DOWNLOAD => '购买后解锁高速直链附件下载权限',
            DeliveryType::ONLINE_VIEW => '购买后立即解锁在线播放与完整章节阅读权限',
            default => '系统自动发货',
        };
    }
}
