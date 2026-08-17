<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;


class VideoDownloadSubmission extends Model
{

    protected $fillable = [
        'user_id',
        'video_id',
        'type',
        'content',
        'extraction_code',  // 提取码
        'archive_password', // 解压密码
        'remark',           // 备注说明
        'status',
    ];
}
