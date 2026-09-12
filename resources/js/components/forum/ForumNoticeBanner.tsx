import React, { useState, useEffect } from 'react';
import { Megaphone, AlertTriangle, AlertCircle, CheckCircle2, ChevronRight, X } from 'lucide-react';
import { ForumNoticeItem } from '@/types/forum';

interface ForumNoticeBannerProps {
    notices?: ForumNoticeItem[];
    className?: string;
}

// 🎯 类型对应的色彩映射与图标
const NOTICE_STYLES: Record<
    ForumNoticeItem['type'],
    {
        container: string;
        badge: string;
        iconColor: string;
        Icon: React.ElementType;
    }
> = {
    info: {
        container: 'bg-primary/5 dark:bg-primary/10 border-primary/20 text-primary-950 dark:text-primary-100',
        badge: 'bg-primary/15 text-primary dark:text-primary-300 border-primary/25',
        iconColor: 'text-primary',
        Icon: Megaphone,
    },
    warning: {
        container: 'bg-amber-500/10 dark:bg-amber-500/15 border-amber-500/30 text-amber-950 dark:text-amber-100',
        badge: 'bg-amber-500/20 text-amber-700 dark:text-amber-300 border-amber-500/30',
        iconColor: 'text-amber-600 dark:text-amber-400',
        Icon: AlertTriangle,
    },
    danger: {
        container: 'bg-red-500/10 dark:bg-red-500/15 border-red-500/30 text-red-950 dark:text-red-100',
        badge: 'bg-red-500/20 text-red-700 dark:text-red-300 border-red-500/30',
        iconColor: 'text-red-600 dark:text-red-400',
        Icon: AlertCircle,
    },
    success: {
        container: 'bg-emerald-500/10 dark:bg-emerald-500/15 border-emerald-500/30 text-emerald-950 dark:text-emerald-100',
        badge: 'bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-emerald-500/30',
        iconColor: 'text-emerald-600 dark:text-emerald-400',
        Icon: CheckCircle2,
    },
};

export default function ForumNoticeBanner({ notices = [], className = '' }: ForumNoticeBannerProps) {
    const [dismissedIds, setDismissedIds] = useState<Array<number | string>>([]);

    // 🎯 初始化时从 localStorage 加载已被用户关闭的公告 ID
    useEffect(() => {
        try {
            const cached = localStorage.getItem('forum_dismissed_notices');
            if (cached) {
                setDismissedIds(JSON.parse(cached));
            }
        } catch {
            // 忽略存储读取异常
        }
    }, []);

    // 处理关闭动作
    const handleDismiss = (id: number | string) => {
        const next = [...dismissedIds, id];
        setDismissedIds(next);
        try {
            localStorage.setItem('forum_dismissed_notices', JSON.stringify(next));
        } catch {
            // 忽略写入异常
        }
    };

    // 过滤掉被用户手动忽略的公告
    const visibleNotices = notices.filter((n) => !dismissedIds.includes(n.id));

    if (visibleNotices.length === 0) {
        return null;
    }

    return (
        <div className={`space-y-3 ${className}`}>
            {visibleNotices.map((notice) => {
                const style = NOTICE_STYLES[notice.type] || NOTICE_STYLES.info;
                const IconComponent = style.Icon;

                return (
                    <div
                        key={notice.id}
                        className={`relative flex items-start justify-between gap-3 p-4 rounded-2xl border transition-all shadow-2xs ${style.container}`}
                    >
                        {/* 左侧：图标 + 标题与正文 */}
                        <div className="flex items-start gap-3 flex-1 min-w-0">
                            <div className="mt-0.5 shrink-0 p-1.5 rounded-lg bg-background/60 dark:bg-background/40 backdrop-blur-xs border border-border/40 shadow-2xs">
                                <IconComponent className={`w-4 h-4 ${style.iconColor}`} />
                            </div>

                            <div className="flex-1 min-w-0 space-y-1">
                                <div className="flex items-center gap-2 flex-wrap">
                                    <h4 className="text-sm font-bold text-foreground leading-snug tracking-tight">
                                        {notice.title}
                                    </h4>
                                </div>

                                {notice.content && (
                                    <p className="text-xs text-foreground/80 leading-relaxed break-words whitespace-pre-line">
                                        {notice.content}
                                    </p>
                                )}

                                {/* 跳转操作按钮 */}
                                {notice.linkUrl && (
                                    <div className="pt-1">
                                        <a
                                            href={notice.linkUrl}
                                            className="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline group/link"
                                        >
                                            <span>{notice.linkText || '查看详情'}</span>
                                            <ChevronRight className="w-3.5 h-3.5 transition-transform group-hover/link:translate-x-0.5" />
                                        </a>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* 右侧：关闭按钮 */}
                        {notice.isDismissible && (
                            <button
                                type="button"
                                onClick={() => handleDismiss(notice.id)}
                                className="p-1 rounded-lg text-muted-foreground/70 hover:text-foreground hover:bg-background/60 transition-colors shrink-0"
                                title="忽略并关闭本条公告"
                                aria-label="Close"
                            >
                                <X className="w-4 h-4" />
                            </button>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
