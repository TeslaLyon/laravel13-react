<?php

declare(strict_types=1);

namespace App\Enums;

enum VideoDownloadFeedbackType: int
{
    case DEAD_LINK = 1;           // 磁力死链 / 无法下载 / 无做种
    case CONTENT_MISMATCH = 2;    // 视频内容不符 / 货不对板
    case PASSWORD_ERROR = 3;       // 提取码 / 解压密码错误
    case RESOLUTION_MISMATCH = 4; // 清晰度与标称不符
    case MALICIOUS_OR_AD = 5;     // 广告捆绑 / 虚假资源 / 恶意文件
    case OTHER = 6;               // 其他问题

    /**
     * 获取类型中文文本描述
     */
    public function label(): string
    {
        return match ($this) {
            self::DEAD_LINK => '死链/无速度',
            self::CONTENT_MISMATCH => '内容不符/货不对板',
            self::PASSWORD_ERROR => '密码/提取码错误',
            self::RESOLUTION_MISMATCH => '清晰度不符',
            self::MALICIOUS_OR_AD => '广告捆绑/恶意文件',
            self::OTHER => '其他问题',
        };
    }

    /**
     * 获取详细辅助解释
     */
    public function description(): string
    {
        return match ($this) {
            self::DEAD_LINK => '链接无法连接、迅雷/BT 客户端无做种、长时间 0KB 速度',
            self::CONTENT_MISMATCH => '下载后的影片与当前标题/番号/演员严重不符',
            self::PASSWORD_ERROR => '网盘提取码失效或压缩包解压密码错误',
            self::RESOLUTION_MISMATCH => '标称 4K/1080P，实际为模糊标清或画质严重压缩',
            self::MALICIOUS_OR_AD => '解压后包含恶意软件、病毒、非视频文件或大量引流广告',
            self::OTHER => '其他未列出的资源异常问题',
        };
    }

    /**
     * 获取前端 Tailwind CSS 徽章样式类名
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::DEAD_LINK => 'bg-red-500/10 text-red-600 dark:text-red-400 border-red-500/20',
            self::CONTENT_MISMATCH => 'bg-orange-500/10 text-orange-600 dark:text-orange-400 border-orange-500/20',
            self::PASSWORD_ERROR => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
            self::RESOLUTION_MISMATCH => 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20',
            self::MALICIOUS_OR_AD => 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border-rose-500/20',
            self::OTHER => 'bg-muted text-muted-foreground border-border/60',
        };
    }

    /**
     * 下拉选项列表（供前后端组件使用）
     *
     * @return array<int, array{label: string, description: string, value: int}>
     */
    public static function options(): array
    {
        return array_map(fn(self $type) => [
            'label' => $type->label(),
            'description' => $type->description(),
            'value' => $type->value,
        ], self::cases());
    }
}

