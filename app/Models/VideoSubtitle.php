<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class VideoSubtitle extends Model
{
    protected $fillable = [
        'video_id',    // 关联的视频 ID
        'user_id',     // 上传者的用户 ID
        'title',        // 字幕标题
        'language',    // 字幕语言 (如 'zh')
        'file_path',   // 本地上传的文件路径
        'is_external', // 是否为外部链接的标识
        'source_url',  // 用户填写的外部下载链接
        'format',      // 字幕格式 (srt, ass 等)
        'file_size',   // 文件大小
        'status',      // 审核状态 (pending, approved, rejected)
    ];
    
    /**
     * 属性类型转换 (Casting)。
     * 确保从数据库取出的数据是正确的数据类型。
     */
    protected $casts = [
        'file_size' => 'integer',
        'is_external' => 'boolean', // 确保自动转换为布尔值
    ];

    /**
     * 获取拥有此字幕的视频。
     *
     * @return BelongsTo
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * 获取上传此字幕的用户。
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 本地作用域：只查询已审核通过的字幕。
     *
     * 用法示例：VideoSubtitle::approved()->where('video_id', $id)->get();
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * 本地作用域：只查询待审核的字幕。
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}
