<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoSubtitleRequestStatus: int
{
    case PENDING   = 1; // 求字幕中
    case FULFILLED = 2; // 愿望达成
    case CLOSED    = 3; // 已关闭 / 已取消

    /**
     * 获取状态中文文本描述
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING   => '求字幕中',
            self::FULFILLED => '愿望达成',
            self::CLOSED    => '已关闭',
        };
    }

    /**
     * 前端 Tailwind CSS 徽章样式类名
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING   => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            self::FULFILLED => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            self::CLOSED    => 'bg-muted text-muted-foreground border-border/60',
        };
    }

    /**
     * 下拉选项列表（供筛选框或表单选择使用）
     *
     * @return array<int, array{label: string, value: int}>
     */
    public static function options(): array
    {
        return array_map(fn (self $status) => [
            'label' => $status->label(),
            'value' => $status->value,
        ], self::cases());
    }
}
