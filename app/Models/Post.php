<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
