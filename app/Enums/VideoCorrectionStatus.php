<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoCorrectionStatus: int
{
    case PENDING = 1; // 待审核
    case APPROVED = 2; // 已通过
    case REJECTED = 3; // 已拒绝

    /**
     * 获取状态中文文案
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING => '待审核',
            self::APPROVED => '已通过',
            self::REJECTED => '已拒绝',
        };
    }

    /**
     * 前端 Tailwind CSS 徽章样式类
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::PENDING => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            self::APPROVED => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
            self::REJECTED => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
        };
    }

    /**
     * 下拉筛选选项列表
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
