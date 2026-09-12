<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\VideoSubtitleFeedbackStatus;


class VideoSubtitleFeedback extends Model
{
    protected $fillable = [
        'video_subtitle_id', // 关联的字幕 ID
        'user_id',           // 提交反馈的用户 ID
        'content',           // 反馈的具体内容
        'status',            // 处理状态 (如 pending, resolved, ignored)
        'admin_notes',       // 管理员后台处理备注
    ];

    /**
     * 原生属性类型转换
     */
    protected $casts = [
        'status' => VideoSubtitleFeedbackStatus::class, // 🌟 自动双向转换枚举与整型
    ];

    /**
     * 关联到具体的字幕模型
     */
    public function subtitle()
    {
        // 第二个参数是外键名，如果遵循命名规范，可以省略
        return $this->belongsTo(VideoSubtitle::class, 'video_subtitle_id');
    }

    /**
     * 关联到提交反馈的用户
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
