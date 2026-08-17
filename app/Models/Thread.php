<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Thread extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'node_id',
        'user_id',
        'username',
        'title',
        'slug',
        'prefix',
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
}
