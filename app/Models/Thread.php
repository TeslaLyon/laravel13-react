<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Thread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'node_id',
        'user_id',
        'username',
        'title',
        'slug',
        'sticky',
        'discussion_open',
        'discussion_state',
        'view_count',
        'reply_count',
        'first_post_id',
        'last_post_id',
        'last_post_user_id',
        'last_post_username',
        'last_post_date',
    ];

    protected $casts = [
        'sticky' => 'boolean',
        'discussion_open' => 'boolean',
        'last_post_date' => 'datetime',
    ];

    /**
     * 🎯 访问器：支持通过 $thread->is_sticky 访问 sticky
     */
    protected function isSticky(): Attribute
    {
        return Attribute::make(
            get: fn() => (bool) $this->sticky,
            set: fn($value) => ['sticky' => (bool) $value]
        );
    }

    /**
     * 🎯 访问器：支持通过 $thread->is_locked 访问 !discussion_open
     */
    protected function isLocked(): Attribute
    {
        return Attribute::make(
            get: fn() => !(bool) $this->discussion_open,
            set: fn($value) => ['discussion_open' => !(bool) $value]
        );
    }

    /**
     * 🎯 多对多关联：该主题拥有的所有彩色前缀标签
     */
    public function prefixes(): BelongsToMany
    {
        return $this->belongsToMany(ThreadPrefix::class, 'thread_prefix_thread', 'thread_id', 'prefix_id')
            ->orderBy('display_order', 'asc');
    }

    /**
     * 关联：归属节点
     */
    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    /**
     * 关联：主题作者
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联：回复/楼层
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class, 'thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastPostUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }
}
