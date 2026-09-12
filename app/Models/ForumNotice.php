<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForumNotice extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'forum_notices';

    protected $fillable = [
        'node_id',
        'title',
        'content',
        'type',
        'link_url',
        'link_text',
        'is_active',
        'is_dismissible',
        'display_order',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_dismissible' => 'boolean',
        'display_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * 🎯 查询作用域：仅查询当前处于生效时间范围内的启用公告
     */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function (Builder $q) use ($now) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * 🎯 查询作用域：获取针对当前版块的公告（同时包含全局通用公告）
     */
    public function scopeForNode(Builder $query, ?int $nodeId): Builder
    {
        return $query->where(function (Builder $q) use ($nodeId) {
            $q->where('node_id', $nodeId)->orWhereNull('node_id');
        })
            ->orderByDesc('display_order')
            ->orderByDesc('id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 🎯 查询作用域：仅获取面向首页展示的全局通告 (node_id 为 null)
     */
    public function scopeForIndex(Builder $query): Builder
    {
        return $query->whereNull('node_id')
            ->orderByDesc('display_order')
            ->orderByDesc('id');
    }
}
