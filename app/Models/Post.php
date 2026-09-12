<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'thread_id',
        'user_id',
        'username',
        'message',
        'position',
        'is_first_post',
        'message_state',
        'reaction_score',
        'ip_address',
        'edit_count',
        'edited_at',
        'edited_by_user_id',
    ];

    protected $casts = [
        'is_first_post' => 'boolean',
        'edited_at' => 'datetime',
    ];

    /**
     * 🎯 虚拟访问器：由 position 动态计算人类可读的楼层号 (0 映射为 1 楼，1 映射为 2 楼)
     */
    protected function floorNumber(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->position + 1
        );
    }

    /**
     * 查询作用域：仅查询正常可见的楼层
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('message_state', 'visible');
    }

    /**
     * 关联：所属主题
     */
    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id');
    }

    /**
     * 关联：发帖作者
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联发帖人
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 关联最后编辑人
     */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_user_id');
    }
}
