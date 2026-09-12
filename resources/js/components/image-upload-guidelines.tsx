import React from 'react';
import { ShieldAlert, AlertTriangle } from 'lucide-react';

export interface GuidelineItem {
    title: string;
    description: string;
}

// 默认 6 项核心合规规则
const DEFAULT_RULES: GuidelineItem[] = [
    {
        title: '严禁违法反动内容',
        description: '包含涉政敏感、分裂煽动、颠覆政权、恐怖暴力或极端宗教思想。',
    },
    {
        title: '严禁惊悚、恶心与自残',
        description: '包含血腥肢解、自残虐待、致人生理极度不适的恶心或恐怖画面。',
    },
    {
        title: '严禁仇恨言论与网暴',
        description: '包含人身攻击、地域/种族歧视、侮辱诽谤或公开他人隐私（开盒人肉）。',
    },
    {
        title: '严禁诈骗与违规广告',
        description: '包含诱导欺诈、博彩/黑产外链二维码或仿冒官方虚假认证图标。',
    },
    {
        title: '严禁侵权抄袭',
        description: '未经授权盗用他人商业版权设计、商标或未授权的肖像照片。',
    },
];

export interface ImageUploadGuidelinesProps {
    /** 标题文案（默认：图片上传规范与合规提示） */
    title?: string;
    /** 自定义规则列表（不传则使用默认 6 项） */
    rules?: GuidelineItem[];
    /** 底部警告提醒文案 */
    warningText?: string;
    /** 紧凑模式（适合头像上传弹窗或狭窄空间，改为 1~2 列排版） */
    compact?: boolean;
    /** 自定义外层样式类名 */
    className?: string;
}

export const ImageUploadGuidelines: React.FC<ImageUploadGuidelinesProps> = ({
    title = '图片上传规范与合规提示',
    rules = DEFAULT_RULES,
    warningText = '上传违规图片将被系统自动清理，多次违规将面临账号封禁、限制展示等处罚。',
    compact = false,
    className = '',
}) => {
    return (
        <div
            className={`rounded-2xl border border-amber-500/30 bg-amber-500/5 p-5 sm:p-6 text-sm leading-relaxed space-y-3.5 ${className}`}
        >
            {/* 头部标题栏 */}
            <div className="flex items-center gap-2.5 text-amber-600 dark:text-amber-400 font-semibold text-sm">
                <ShieldAlert className="w-4 h-4 shrink-0" />
                <span>{title}</span>
            </div>

            {/* 规则条目栅格 */}
            <div
                className={`grid gap-3 text-muted-foreground ${compact
                        ? 'grid-cols-1 sm:grid-cols-2'
                        : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'
                    }`}
            >
                {rules.map((rule, index) => (
                    <div
                        key={index}
                        className="flex items-start gap-2 bg-background/60 p-2.5 rounded-xl border border-border/50"
                    >
                        <span className="w-1.5 h-1.5 rounded-full bg-destructive mt-1.5 shrink-0" />
                        <div>
                            <span className="font-semibold text-foreground block mb-0.5">
                                {rule.title}
                            </span>
                            {rule.description}
                        </div>
                    </div>
                ))}
            </div>

            {/* 底部严重性警示 */}
            <div className="flex items-center gap-1.5 text-amber-600/90 dark:text-amber-400/90 pt-1">
                <AlertTriangle className="w-3.5 h-3.5 shrink-0" />
                <span>{warningText}</span>
            </div>
        </div>
    );
};
