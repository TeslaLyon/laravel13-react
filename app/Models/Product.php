<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\PreviewType;
use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    use HasFactory;

    /**
     * 可批量赋值的属性白名单
     */
    protected $fillable = [
        'title',
        'slug',
        'type',
        'list_img',
        'preview_type',
        'preview_data',
        'duration_seconds',
        'resolution',
        'spec_badge',
        'price',
        'original_price',
        'views_count',
        'sales_count',
        'status',
        'sort_order',
    ];

    /**
     * 属性类型转换器
     */
    protected $casts = [
        'type' => ProductType::class,
        'preview_type' => PreviewType::class,
        'preview_data' => 'array',
        'spec_badge' => 'array',
        'price' => MoneyCast::class,       // 自动进行 分(DB) <-> 元(PHP) 转换
        'original_price' => MoneyCast::class,       // 自动进行 分(DB) <-> 元(PHP) 转换
        'duration_seconds' => 'integer',
        'views_count' => 'integer',
        'sales_count' => 'integer',
        'status' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * 追加到序列化结果中的访问器属性
     */
    protected $appends = [
        'duration_formatted',
        'has_discount',
    ];

    /* =========================================================================
     *  关联关系定义 (Relationships)
     * ========================================================================= */

    /**
     * 关联商品详情表 (1:1 关系)
     */
    public function detail(): HasOne
    {
        return $this->hasOne(ProductDetail::class);
    }

    /**
     * 关联标签 (N:N 多对多关系)
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'product_tag')
            ->withTimestamps();
    }

    /**
     * 关联创作者 / 讲师 / 演员 (N:N 多对多关系)
     */
    public function actors(): BelongsToMany
    {
        return $this->belongsToMany(Actor::class, 'actor_product')
            ->withTimestamps();
    }

    /* =========================================================================
     *  访问器与修改器 (Accessors & Mutators)
     * ========================================================================= */

    /**
     * 格式化视频播放时长 (例如 45900 秒 -> "12:45:00", 320 秒 -> "05:20")
     */
    protected function durationFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                $seconds = $this->duration_seconds ?? 0;
                if ($seconds <= 0) {
                    return '';
                }

                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                $secs = $seconds % 60;

                if ($hours > 0) {
                    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
                }

                return sprintf('%02d:%02d', $minutes, $secs);
            }
        );
    }

    /**
     * 判断当前商品是否处于折扣特价状态
     */
    protected function hasDiscount(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->original_price !== null && $this->original_price > $this->price
        );
    }

    /* =========================================================================
     *  查询作用域 (Query Scopes)
     * ========================================================================= */

    /**
     * 作用域：仅查询正常上架销售中的商品
     */
    public function scopeOnSale(Builder $query): Builder
    {
        return $query->where('status', 1);
    }

    /**
     * 作用域：按商品类型过滤
     */
    public function scopeOfType(Builder $query, ProductType|string $type): Builder
    {
        $typeVal = $type instanceof ProductType ? $type->value : $type;
        return $query->where('type', $typeVal);
    }

    /**
     * 作用域：商城默认排序（按自定义权重倒序，再按发布时间倒序）
     */
    public function scopeDefaultSort(Builder $query): Builder
    {
        return $query->orderByDesc('sort_order')->orderByDesc('created_at');
    }
}
