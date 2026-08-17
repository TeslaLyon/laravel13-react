<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;


class VideoSubtitleRequest extends Model
{
    protected $fillable = [
        'video_id',
        'user_id',
        'status',
    ];

    /**
     * 获取申请对应的视频。
     *
     * @return BelongsTo
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * 获取发起该申请的用户。
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 本地作用域：只查询正在求字幕（待处理）的申请。
     *
     * 用法示例：SubtitleRequest::pending()->get();
     */
    public function scopePending(Builder $query)
    {
        return $query->where('status', 'pending');
    }
}
