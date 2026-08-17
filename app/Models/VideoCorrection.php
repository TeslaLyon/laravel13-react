<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;


class VideoCorrection extends Model
{

    protected $fillable = [
        'user_id',
        'video_id',
        'type',
        'payload',
        'status',
        'reviewer_id',
        'reject_reason',
        'reviewed_at'
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * 智能获取格式化后的 payload 载荷数据。
     * 使用最新 Attribute::make(get: ...) 命名参数语法。
     *
     * 调用方式: $correction->formatted_payload
     */
    protected function formattedPayload(): Attribute
    {
        return Attribute::make(
            get: function (): mixed {
                // 如果是关联 ID 数组类型，自动解析为 PHP 数组
                if (in_array($this->type, ['actors', 'categories', 'tags'], true)) {
                    return json_decode($this->payload ?? '[]', true) ?? [];
                }

                // 如果是纯文本类型（如 title_cn, description），返回原文本字符串
                return $this->payload;
            }
        );
    }

    /**
     * 关联提交提案的用户。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联修正的视频。
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * 关联审核该提案的管理员。
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

}
