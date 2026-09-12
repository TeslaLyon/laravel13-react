<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoSubtitleFeedbackStatus: int
{
    case PENDING = 1; // 待处理
    case RESOLVED = 2; // 已解决 / 已修复
    case IGNORED = 3; // 已忽略 / 无效反馈

    /**
     * 获取状态中文文本描述
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::RESOLVED => '已解决',
            self::IGNORED => '已忽略',
        };
    }

    /**
     * 获取前端 Tailwind CSS 徽章样式类名
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            self::RESOLVED => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            self::IGNORED => 'bg-muted text-muted-foreground border-border/60',
        };
    }

    /**
     * 下拉选项列表（供筛选组件使用）
     *
     * @return array<int, array{label: string, value: int}>
     */
    public static function options(): array
    {
        return array_map(fn(self $status) => [
            'label' => $status->label(),
            'value' => $status->value,
        ], self::cases());
    }
}
