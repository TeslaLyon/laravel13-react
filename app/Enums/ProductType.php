<?php

namespace App\Enums;

enum ProductType: string
{
    case VIDEO = 'video';
    case PHOTO = 'photo';

    public function label(): string
    {
        return match ($this) {
            self::VIDEO => '视频',
            self::PHOTO => '图片',
        };
    }
}
