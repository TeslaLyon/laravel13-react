<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;


class Feedback extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'content',
        'status',
    ];

    protected $casts = [
        'status' => FeedbackStatus::class, // 自动转换整型 <-> 枚举对象
    ];

    /**
     * 获取拥有此反馈的父模型 (如 Video, Image, Forum 等)。
     */
    public function feedbackable()
    {
        return $this->morphTo();
    }
}
