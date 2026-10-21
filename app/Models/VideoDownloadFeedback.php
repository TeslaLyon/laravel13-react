<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\VideoDownloadFeedbackType;
use App\Enums\VideoDownloadFeedbackStatus;

/**
 * 视频下载信息 / 磁力链接纠错反馈模型
 *
 * @property int $id
 * @property int $video_id
 * @property int $video_download_id
 * @property int|null $user_id
 * @property VideoDownloadFeedbackType $type
 * @property string|null $content
 * @property VideoDownloadFeedbackStatus $status
 * @property string|null $admin_notes
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Video $video
 * @property-read \App\Models\VideoDownload $download
 * @property-read \App\Models\User|null $user
 */
class VideoDownloadFeedback extends Model
{
    protected $table = 'video_download_feedbacks';

    protected $fillable = [
        'video_id',
        'video_download_id',
        'user_id',
        'type',
        'content',
        'status',
        'admin_notes',
    ];

    protected $casts = [
        'video_id' => 'integer',
        'video_download_id' => 'integer',
        'user_id' => 'integer',
        'type' => VideoDownloadFeedbackType::class,
        'status' => VideoDownloadFeedbackStatus::class,
    ];

    /**
     * 关联视频模型
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * 关联具体的下载项 (磁力/网盘)
     */
    public function download(): BelongsTo
    {
        return $this->belongsTo(VideoDownload::class, 'video_download_id');
    }

    /**
     * 关联提交反馈的用户
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

