<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Node extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 追加到模型数组/JSON 序列化中的虚拟字段
     */
    protected $appends = ['icon_url'];

    protected $fillable = [
        'parent_id',
        'node_type',
        'title',
        'slug',
        'description',
        'icon',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * 🎯 核心访问器：自动解析 icon 字段是否为自定义图片
     *
     * 规则：
     * 1. 若为空，返回 null
     * 2. 若为 http/https 完整外链，直接返回
     * 3. 若为本地上传的图片路径（含常见图片扩展名），自动补全 Storage 访问 URL
     * 4. 若为普通图标名称 (如 messages-square)，返回 null 让前端渲染矢量图标
     */
    protected function iconUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->icon) {
                    return null;
                }

                // 1. 如果是完整外链地址
                if (Str::startsWith($this->icon, ['http://', 'https://', '//'])) {
                    return $this->icon;
                }

                // 2. 如果包含图片扩展名或 storage 标识，说明是上传的自定义图片
                if (preg_match('/\.(png|jpg|jpeg|webp|gif|svg)$/i', $this->icon) || Str::startsWith($this->icon, 'storage/')) {
                    return Str::startsWith($this->icon, '/storage/')
                        ? $this->icon
                        : Storage::disk('public')->url($this->icon);
                }

                // 3. 否则视为普通矢量图标标识
                return null;
            }
        );
    }

    /**
     * 关联：父节点
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'parent_id');
    }

    /**
     * 关联：子节点列表
     */
    public function children(): HasMany
    {
        return $this->hasMany(Node::class, 'parent_id')->orderBy('display_order', 'asc');
    }

    /**
     * 🎯 核心方案：前台专用的“已启用且按顺序排列”的子节点关联
     * 自带 where('is_active', true) 和 orderBy('display_order', 'asc')
     */
    public function activeChildren(): HasMany
    {
        return $this->children()
            ->where('is_active', true)
            ->orderBy('display_order', 'asc');
    }

    /**
     * 关联：版块特有属性 (1:1 扩展表)
     */
    public function forum(): HasOne
    {
        return $this->hasOne(Forum::class, 'node_id');
    }

    /**
     * 关联：该节点下的主题帖
     */
    public function threads(): HasMany
    {
        return $this->hasMany(Thread::class, 'node_id');
    }

    /**
     * 🎯 查询作用域：仅查询已启用的节点
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * 🎯 查询作用域：按显示顺序升序排列
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order', 'asc');
    }

    /**
     * 🎯 查询作用域：仅查询顶级根节点
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }
}
