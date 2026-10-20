<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VideoHistory extends Model
{
    protected $fillable = [
        'video_id',
        'correction_id',
        'operator_id',
        'action_type',
        'old_values',
        'new_values',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * 关联修改的视频
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * 关联的修正提案 (可选)
     */
    public function correction(): BelongsTo
    {
        return $this->belongsTo(VideoCorrection::class, 'correction_id');
    }

    /**
     * 操作人 / 审核管理员
     */
    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}

