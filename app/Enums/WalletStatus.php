<?php

namespace App\Enums;

enum WalletStatus: int
{
    case ACTIVE = 1;    // 正常
    case FROZEN = 2;    // 已冻结 (只进不出/不可操作)
    case DISABLED = 3;  // 已注销

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => '正常',
            self::FROZEN => '已冻结',
            self::DISABLED => '已禁用',
        };
    }
}
