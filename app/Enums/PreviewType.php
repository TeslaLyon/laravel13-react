<?php

namespace App\Enums;

enum PreviewType: string
{
    case VIDEO = 'video';       // 视频试看 (带播放器与时长)
    case CAROUSEL = 'carousel'; // 多图轮播画廊
    case GIF = 'gif';           // 动态演示图
    case IMAGE = 'image';       // 单张高清大图
}
