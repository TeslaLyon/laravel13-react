<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;


class Feedback extends Model
{
    protected $fillable = [
        'feedbackable_type',
        'feedbackable_id',
        'user_id',
        'type',
        'content',
        'admin_reply',
        'status',
        'handled_by',
        'handled_at',
    ];

    protected $casts = [
        'status'     => FeedbackStatus::class, // 自动转换整型 <-> 枚举对象
        'handled_at' => 'datetime',
    ];

    /**
     * 获取拥有此反馈的父模型 (如 Video, Channel, Category 等)。
     */
    public function feedbackable()
    {
        return $this->morphTo();
    }

    /**
     * 关联提交反馈的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联处理该反馈的管理员
     */
    public function handler()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
