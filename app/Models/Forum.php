<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Forum extends Model
{
    use HasFactory;

    // 🎯 主键为 node_id，且非自增（来源于 Node 表的 id）
    protected $primaryKey = 'node_id';
    public $incrementing = false;

    protected $fillable = [
        'node_id',
        'allow_posting',
        'link_url',
        'discussion_count',
        'message_count',
        'last_thread_id',
        'last_thread_title',
        'last_thread_slug',
        'last_thread_prefixes',
        'last_post_id',
        'last_post_user_id',
        'last_post_username',
        'last_post_date',
    ];

    protected $casts = [
        'allow_posting' => 'boolean',
        'last_thread_prefixes' => 'array',
        'last_post_date' => 'datetime',
    ];

    /**
     * 关联：归属的节点
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function lastPostUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }

    /**
     * 🎯 2. 安全访问器：当历史旧数据为空时，自动使用标题临时生成 slug 兜底
     */
    protected function lastThreadSlug(): Attribute
    {
        return Attribute::make(
            get: fn(?string $value) => $value ?: ($this->last_thread_title ? Str::slug($this->last_thread_title) : null),
        );
    }
}
