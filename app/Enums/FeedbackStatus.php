<?php

namespace App\Enums;

enum FeedbackStatus: int
{
    case PENDING = 1;     // 待处理
    case PROCESSING = 2;  // 处理中
    case RESOLVED = 3;    // 已解决

    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::PROCESSING => '处理中',
            self::RESOLVED => '已解决',
        };
    }

    /**
     * Tailwind CSS 徽章样式类名
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            self::PROCESSING => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
            self::RESOLVED => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
        };
    }
}
