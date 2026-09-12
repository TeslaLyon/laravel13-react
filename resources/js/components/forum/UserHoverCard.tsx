import React, { useState, useCallback } from 'react';
import { Link, useHttp } from '@inertiajs/react';
import { Calendar, MessageSquare, ThumbsUp, Award, ShieldCheck, Sparkles, ChevronRight } from 'lucide-react';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { BasicAuthor, PostAuthor, UserBadge, UserHoverCardData } from '@/types/forum';
import { userHoverCard } from '@/actions/App/Http/Controllers/Forum/ForumController';

const userCardCache = new Map<string | number, UserHoverCardData>();

// 🎯 单行最多展示勋章数量
const MAX_VISIBLE_BADGES = 4;

interface UserHoverCardProps {
    author?: BasicAuthor | PostAuthor | null;
    children: React.ReactNode;
}

/**
 * 🎯 纯图形拟物化奖章组件（已移除底部文字，支持 Tooltip 提示）
 */
function MedalItem({ badge }: { badge: UserBadge }) {
    return (
        <TooltipProvider delayDuration={100}>
            <Tooltip>
                <TooltipTrigger asChild>
                    {/* 🎯 纯图形容器：居中对齐、支持悬停上浮，自带完整边框 */}
                    <div className="group/medal relative flex items-center justify-center h-16 w-full rounded-xl bg-muted/40 hover:bg-muted/80 border border-border/60 hover:border-amber-500/60 transition-all duration-200 cursor-pointer hover:-translate-y-1 hover:shadow-md select-none">
                        {badge.icon && (badge.icon.startsWith('http') || badge.icon.startsWith('/')) ? (
                            <img
                                src={badge.icon}
                                alt={badge.name}
                                className="w-10 h-10 object-contain drop-shadow-md"
                            />
                        ) : (
                            <div className="relative flex flex-col items-center justify-center">
                                {/* 1. 顶部挂绳绶带 */}
                                <div className="w-6 h-3.5 bg-linear-to-r from-purple-500 via-pink-500 to-indigo-500 rounded-t-xs shadow-2xs relative overflow-hidden flex">
                                    <div className="w-1 h-full bg-white/25 ml-1" />
                                    <div className="w-1 h-full bg-white/35 ml-1" />
                                </div>
                                {/* 2. 金属挂环 */}
                                <div className="w-2.5 h-1.5 border-2 border-slate-300 dark:border-slate-500 -mt-0.5 rounded-sm z-10" />

                                {/* 3. 五边形金牌主体 */}
                                <div className="relative -mt-0.5 w-7.5 h-7.5 bg-linear-to-b from-amber-300 via-amber-400 to-amber-500 rounded-lg shadow-sm border border-amber-200/60 flex items-center justify-center transform transition-transform group-hover/medal:scale-105">
                                    <span className="text-[11px] font-black text-amber-900 tracking-tighter drop-shadow-xs">
                                        1
                                    </span>
                                    <Sparkles className="w-2.5 h-2.5 text-amber-100 absolute -top-1 -right-1 animate-pulse" />
                                </div>
                            </div>
                        )}
                    </div>
                </TooltipTrigger>

                {/* Hover 弹出的 Tooltip 说明卡 */}
                <TooltipContent
                    side="top"
                    className="p-3 max-w-[220px] bg-popover/98 backdrop-blur-md border border-border/80 shadow-2xl rounded-xl z-60 space-y-1"
                >
                    <div className="flex items-center gap-1.5">
                        <Award className="w-3.5 h-3.5 text-amber-500 shrink-0" />
                        <span className="font-bold text-xs text-foreground">{badge.name}</span>
                    </div>
                    <p className="text-[11px] text-muted-foreground leading-relaxed">
                        {badge.description || '该荣誉由社区官方认证颁发'}
                    </p>
                </TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}

export default function UserHoverCard({ author, children }: UserHoverCardProps) {
    const { get, processing } = useHttp();

    const [cardData, setCardData] = useState<UserHoverCardData | null>(() => {
        return author?.id ? userCardCache.get(author.id) || null : null;
    });
    const [hasError, setHasError] = useState(false);

    const handleOpenChange = useCallback(
        (open: boolean) => {
            if (!open || !author?.id) {
                return;
            }

            if (userCardCache.has(author.id)) {
                setCardData(userCardCache.get(author.id)!);
                return;
            }

            setHasError(false);
            get(userHoverCard.url({ id: author.id }), {
                onSuccess: (response: unknown) => {
                    const data = response as UserHoverCardData;
                    userCardCache.set(author.id, data);
                    setCardData(data);
                },
                onError: () => {
                    setHasError(true);
                },
            });
        },
        [author?.id, get]
    );

    if (!author || !author.id) {
        return <>{children}</>;
    }

    const displayName = cardData?.name || author.name || '匿名用户';
    const displayAvatar = cardData?.avatar || author.avatar || '';
    const displayCover = cardData?.coverUrl || author.coverUrl || null;
    const userInitial = displayName.charAt(0).toUpperCase();

    // 🎯 徽章数据处理：最多截取 4 个，计算总数
    const allBadges = cardData?.badges || [];
    const displayedBadges = allBadges.slice(0, MAX_VISIBLE_BADGES);
    const totalBadgesCount = allBadges.length;

    return (
        <HoverCard openDelay={200} closeDelay={150} onOpenChange={handleOpenChange}>
            <HoverCardTrigger asChild>{children}</HoverCardTrigger>

            <HoverCardContent
                side="top"
                align="start"
                className="w-[360px] p-0 overflow-hidden bg-popover/95 backdrop-blur-md border border-border/80 shadow-2xl rounded-2xl z-50 select-none"
            >
                {/* 1. 骨架屏加载状态 */}
                {processing && !cardData ? (
                    <div className="space-y-4">
                        <Skeleton className="h-24 w-full" />
                        <div className="px-5 pb-5 space-y-4">
                            <div className="flex items-end justify-between -mt-10">
                                <Skeleton className="w-18 h-18 rounded-full border-4 border-background" />
                                <Skeleton className="h-6 w-20 rounded-md mb-1" />
                            </div>
                            <div className="space-y-1.5">
                                <Skeleton className="h-5 w-32" />
                                <Skeleton className="h-4 w-20" />
                            </div>
                            <Skeleton className="h-16 w-full rounded-2xl" />
                            {/* 骨架屏对应 4 个奖章块 */}
                            <div className="grid grid-cols-4 gap-2.5">
                                <Skeleton className="h-16 w-full rounded-xl" />
                                <Skeleton className="h-16 w-full rounded-xl" />
                                <Skeleton className="h-16 w-full rounded-xl" />
                                <Skeleton className="h-16 w-full rounded-xl" />
                            </div>
                        </div>
                    </div>
                ) : hasError && !cardData ? (
                    /* 2. 异常回退展示 */
                    <div className="p-5 flex items-center gap-4">
                        <Avatar className="w-14 h-14 border">
                            <AvatarImage src={displayAvatar} alt={displayName} />
                            <AvatarFallback className="text-base font-bold">{userInitial}</AvatarFallback>
                        </Avatar>
                        <div>
                            <p className="font-bold text-base text-foreground">{displayName}</p>
                            <p className="text-xs text-muted-foreground mt-0.5">暂无更多详细信息</p>
                        </div>
                    </div>
                ) : (
                    /* 3. 正常数据渲染 */
                    <div className="flex flex-col">
                        {/* 顶部：封面背景图 */}
                        <div className="relative h-24 w-full bg-linear-to-r from-violet-600 via-indigo-600 to-primary overflow-hidden">
                            {displayCover ? (
                                <img
                                    src={displayCover}
                                    alt="Cover Banner"
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <div className="w-full h-full bg-linear-to-br from-indigo-500/80 via-purple-500/70 to-pink-500/80" />
                            )}
                            <div className="absolute inset-0 bg-black/10 backdrop-brightness-95" />
                        </div>

                        {/* 下半部分内容区 */}
                        <div className="px-5 pb-5 space-y-4">
                            {/* 头像叠放区 + 身份组 Badge */}
                            <div className="flex items-end justify-between -mt-10 relative z-10">
                                <Avatar className="w-18 h-18 border-4 border-card shadow-lg shrink-0 ring-1 ring-border/40">
                                    <AvatarImage src={displayAvatar} alt={displayName} className="object-cover" />
                                    <AvatarFallback className="bg-primary/10 text-primary font-bold text-2xl">
                                        {userInitial}
                                    </AvatarFallback>
                                </Avatar>

                                {cardData?.role && (
                                    <Badge
                                        variant="secondary"
                                        className="px-2.5 py-1 text-xs h-6 gap-1 bg-primary/10 text-primary border-primary/20 font-semibold mb-1 shadow-2xs"
                                    >
                                        <ShieldCheck className="w-3.5 h-3.5 shrink-0" />
                                        <span>{cardData.role}</span>
                                    </Badge>
                                )}
                            </div>

                            {/* 用户名与 Handle */}
                            <div className="pt-0.5">
                                <Link
                                    href={`/members/${author.id}`}
                                    className="font-bold text-base text-foreground hover:text-primary hover:underline transition-colors block truncate"
                                >
                                    {displayName}
                                </Link>
                                {cardData?.username && (
                                    <p className="text-xs text-muted-foreground mt-0.5 truncate">
                                        @{cardData.username}
                                    </p>
                                )}
                            </div>

                            {/* 活跃统计栏 */}
                            <div className="grid grid-cols-3 gap-2.5 py-2.5 px-3.5 bg-muted/50 rounded-2xl border border-border/50 text-center">
                                <div>
                                    <span className="block text-[11px] text-muted-foreground font-medium">主题/帖子</span>
                                    <span className="text-sm font-bold text-foreground flex items-center justify-center gap-1 mt-0.5">
                                        <MessageSquare className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                                        {cardData?.threadCount ?? 0}
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-[11px] text-muted-foreground font-medium">获得赞同</span>
                                    <span className="text-sm font-bold text-foreground flex items-center justify-center gap-1 mt-0.5">
                                        <ThumbsUp className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                                        {cardData?.likeCount ?? 0}
                                    </span>
                                </div>
                                <div>
                                    <span className="block text-[11px] text-muted-foreground font-medium">注册时间</span>
                                    <span className="text-sm font-bold text-foreground flex items-center justify-center gap-1 mt-0.5">
                                        <Calendar className="w-3.5 h-3.5 text-muted-foreground shrink-0" />
                                        {cardData?.joinedAt || '近期'}
                                    </span>
                                </div>
                            </div>

                            {/* 🎯 荣誉勋章展示区（单行最多 4 个 + 查看全部跳转按钮） */}
                            <div className="space-y-2.5 pt-2 border-t border-border/50">
                                <div className="flex items-center justify-between text-xs">
                                    <span className="flex items-center gap-1.5 font-semibold text-foreground/85">
                                        <Award className="w-4 h-4 text-amber-500 shrink-0" /> 获得勋章
                                    </span>

                                    {/* 🎯 跳转全部勋章页面按钮 */}
                                    {totalBadgesCount > 0 ? (
                                        <Link
                                            href={`/members/${author.id}/badges`}
                                            className="text-[11px] font-medium text-muted-foreground hover:text-primary hover:underline transition-colors flex items-center gap-0.5 group/link"
                                        >
                                            <span>全部 ({totalBadgesCount})</span>
                                            <ChevronRight className="w-3 h-3 transition-transform group-hover/link:translate-x-0.5" />
                                        </Link>
                                    ) : (
                                        <span className="text-[11px] text-muted-foreground">暂无</span>
                                    )}
                                </div>

                                {displayedBadges.length > 0 ? (
                                    /* 🎯 采用 grid-cols-4 固定单行四格，彻底解决边框裁剪问题 */
                                    <div className="grid grid-cols-4 gap-2.5">
                                        {displayedBadges.map((badge) => (
                                            <MedalItem key={badge.id} badge={badge} />
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-xs text-muted-foreground/70 italic py-1 text-center">
                                        该用户暂未获得任何勋章
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                )}
            </HoverCardContent>
        </HoverCard>
    );
}
