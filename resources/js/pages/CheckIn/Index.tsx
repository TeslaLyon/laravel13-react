import React, { useState, useMemo, useEffect } from 'react';
import { Head, Link, Deferred, useHttp } from '@inertiajs/react';
import {
    Calendar as CalendarIcon,
    Flame,
    Sparkles,
    CheckCircle2,
    Coins,
    HelpCircle,
    PartyPopper,
    Ticket,
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    AlertCircle,
    Wallet,
    Lock,
    Check,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

// ==================== 类型定义 ====================

export interface MilestoneItem {
    coins: number;
    cards?: number;
    title: string;
    icon: string;
    is_full_month?: boolean;
}

export interface CheckInData {
    year_month: string;
    signed_dates: string[];
    is_checked_today: boolean;
    continuous_days: number;
    total_days: number;
    longest_continuous_days: number;
    make_up_cards: number;
    total_make_up_cards_earned?: number;
    month_make_up_count?: number;
    max_month_make_up?: number;
    remaining_month_make_up?: number;
    wallet_coins?: number;
}

interface Props {
    breadcrumbs: BreadcrumbItemType[];
    checkInData?: CheckInData;
    milestonesConfig: Record<number, MilestoneItem>;
    baseCoins: number;
    allowedMonths: string[];
}

// ==================== 1:1 响应式骨架屏 ====================

function CheckInSkeleton({ milestoneCount }: { milestoneCount: number }) {
    return (
        <div className="space-y-6 animate-pulse">
            <div className="rounded-3xl border border-border/70 bg-card p-5 sm:p-8 space-y-6 shadow-xs">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/50 pb-5">
                    <div className="space-y-2">
                        <Skeleton className="h-6 w-36 rounded-full" />
                        <Skeleton className="h-9 w-56 sm:w-64 rounded-xl" />
                    </div>
                    <div className="flex items-center gap-3">
                        <Skeleton className="h-12 w-28 rounded-2xl" />
                        <Skeleton className="h-12 w-28 rounded-2xl" />
                    </div>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                    {Array.from({ length: milestoneCount }).map((_, i) => (
                        <div key={i} className="h-32 rounded-2xl border border-border/50 bg-muted/20 p-4 flex flex-col justify-between">
                            <div className="flex justify-between items-center">
                                <Skeleton className="size-8 rounded-xl" />
                                <Skeleton className="h-4 w-10 rounded-md" />
                            </div>
                            <div className="space-y-2">
                                <Skeleton className="h-4 w-16 rounded-md" />
                                <Skeleton className="h-3 w-20 rounded-md" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div className="lg:col-span-8 rounded-3xl border border-border/70 bg-card p-5 sm:p-6 space-y-6 shadow-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-border/60 pb-4">
                        <Skeleton className="h-7 w-44 rounded-lg" />
                        <Skeleton className="h-5 w-32 rounded-md" />
                    </div>
                    <div className="grid grid-cols-7 gap-1.5 sm:gap-2">
                        {Array.from({ length: 35 }).map((_, i) => (
                            <Skeleton key={i} className="h-14 sm:h-16 rounded-xl sm:rounded-2xl" />
                        ))}
                    </div>
                </div>

                <div className="lg:col-span-4 space-y-6">
                    <div className="rounded-3xl border border-border/70 bg-card p-6 space-y-5 shadow-xs">
                        <Skeleton className="h-12 w-32 mx-auto rounded-xl" />
                        <Skeleton className="h-13 w-full rounded-2xl" />
                    </div>
                    <div className="rounded-3xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
                        <Skeleton className="h-6 w-36 rounded-md" />
                        <Skeleton className="h-20 w-full rounded-2xl" />
                    </div>
                </div>
            </div>
        </div>
    );
}

// ==================== 真实打卡内容组件 ====================

function CheckInContent({
    checkInData,
    milestonesConfig,
    baseCoins,
    allowedMonths,
}: {
    checkInData: CheckInData;
    milestonesConfig: Record<number, MilestoneItem>;
    baseCoins: number;
    allowedMonths: string[];
}) {
    const [data, setData] = useState<CheckInData>(checkInData);
    const [rewardModal, setRewardModal] = useState<any>(null);
    const [makeUpDate, setMakeUpDate] = useState<string | null>(null);

    // 分别管理今日打卡与补签的 HTTP 实例
    const checkInHttp = useHttp({});
    const makeUpHttp = useHttp<{ date: string }>({
        date: '',
    });

    useEffect(() => {
        setData(checkInData);
    }, [checkInData]);

    // 资产与额度数据衍生
    const walletCoins = data.wallet_coins ?? 0;
    const makeUpCards = data.make_up_cards ?? 0;
    const totalCardsEarned = data.total_make_up_cards_earned ?? makeUpCards;
    const monthUsedCount = data.month_make_up_count ?? 0;
    const maxMonthMakeUp = data.max_month_make_up ?? 3;
    const remainingMonthMakeUp = data.remaining_month_make_up ?? Math.max(0, maxMonthMakeUp - monthUsedCount);

    const milestoneDays = useMemo(() => {
        return Object.keys(milestonesConfig || {})
            .map(Number)
            .sort((a, b) => a - b);
    }, [milestonesConfig]);

    const currentYearMonth = data.year_month || new Date().toISOString().slice(0, 7);
    const [year, month] = currentYearMonth.split('-').map(Number);
    const daysInMonth = new Date(year, month, 0).getDate();
    const firstDayWeekIndex = new Date(year, month - 1, 1).getDay();
    const todayDateString = new Date().toISOString().split('T')[0];
    const currentMonthString = todayDateString.slice(0, 7);

    const prevMonthStr = useMemo(() => {
        const d = new Date(year, month - 2, 1);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }, [year, month]);

    const nextMonthStr = useMemo(() => {
        const d = new Date(year, month, 1);
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    }, [year, month]);

    const canGoPrev = allowedMonths.includes(prevMonthStr);
    const canGoNext = allowedMonths.includes(nextMonthStr);

    /**
     * 选中补签日期时，同步绑定表单数据
     */
    const handleSelectMakeUpDate = (dateStr: string) => {
        setMakeUpDate(dateStr);
        makeUpHttp.setData('date', dateStr);
    };

    /**
     * 统一就地原子更新前端数据
     */
    const handleSuccessUpdate = (resData: any, isMakeUp: boolean, targetDateStr: string, customMessage?: string) => {
        const totalEarnedCoins = Number(resData.total_earned_coins ?? resData.reward_coins ?? 0);
        const newContinuousDays = Number(resData.continuous_days ?? data.continuous_days);
        const newTotalDays = Number(resData.total_days ?? (data.total_days + 1));
        const newCurrentCards = Number(resData.current_cards ?? data.make_up_cards);

        setData((prev) => {
            const nextSignedDates = prev.signed_dates.includes(targetDateStr)
                ? prev.signed_dates
                : [...prev.signed_dates, targetDateStr];

            const nextMonthCount = isMakeUp ? (prev.month_make_up_count ?? 0) + 1 : (prev.month_make_up_count ?? 0);
            const nextMax = prev.max_month_make_up ?? 3;
            const isToday = targetDateStr === todayDateString;

            return {
                ...prev,
                signed_dates: nextSignedDates,
                is_checked_today: isToday ? true : prev.is_checked_today,
                continuous_days: newContinuousDays,
                total_days: newTotalDays,
                longest_continuous_days: Math.max(prev.longest_continuous_days, newContinuousDays),
                make_up_cards: newCurrentCards,
                total_make_up_cards_earned: (prev.total_make_up_cards_earned ?? prev.make_up_cards) + (resData.earned_cards ?? 0),
                month_make_up_count: nextMonthCount,
                remaining_month_make_up: Math.max(0, nextMax - nextMonthCount),
                wallet_coins: (prev.wallet_coins ?? 0) + totalEarnedCoins,
            };
        });

        // 🌟 核心改进：把接口返回的 message、is_make_up 和目标日期一并保存到弹窗状态
        setRewardModal({
            ...resData,
            is_make_up: isMakeUp,
            message: customMessage || (isMakeUp ? '补签成功！' : '打卡成功！'),
            target_date: targetDateStr,
        });
    };

    /**
     * 安全提取后端 JSON 载荷工具
     */
    const extractResponseBody = (response: any) => {
        if (!response) return null;
        if (response.data && typeof response.data === 'object' && 'success' in response.data) {
            return response.data;
        }
        return response;
    };

    // 今日打卡请求
    const handleCheckIn = () => {
        if (checkInHttp.processing) return;

        checkInHttp.post('/checkin', {
            onSuccess: (response: any) => {
                const body = extractResponseBody(response);
                if (body && body.success) {
                    handleSuccessUpdate(body.data, false, todayDateString, body.message);
                    toast.success(body.message || '今日打卡成功，金币已自动入账！');
                }
            },
            onError: (errors: any) => {
                const msg = typeof errors === 'string' ? errors : (errors?.message || Object.values(errors || {})[0] || '打卡遇到异常，请稍后重试。');
                toast.error(String(msg));
            },
            onNetworkError: () => {
                toast.error('网络连接异常，打卡请求未送达，请检查网络后重试。');
            },
        });
    };

    // 补签打卡请求
    const handleConfirmMakeUp = () => {
        if (!makeUpDate || makeUpHttp.processing) return;

        if (remainingMonthMakeUp <= 0) {
            toast.error(`本月补签次数已达上限 (${maxMonthMakeUp}次)，无法继续补签。`);
            return;
        }

        if (makeUpCards < 1) {
            toast.error('当前持有的补签卡数量不足。');
            return;
        }

        const targetDate = makeUpDate;

        makeUpHttp.post('/checkin', {
            onSuccess: (response: any) => {
                const body = extractResponseBody(response);
                if (body && body.success) {
                    setMakeUpDate(null);
                    handleSuccessUpdate(body.data, true, targetDate, body.message);
                    toast.success(body.message || `已成功补签 ${targetDate} 的打卡！`);
                }
            },
            onError: (errors: any) => {
                const msg = typeof errors === 'string' ? errors : (errors?.message || Object.values(errors || {})[0] || '补签失败，请确认补签条件。');
                toast.error(String(msg));
            },
            onNetworkError: () => {
                toast.error('网络连接超时或中断，补签未完成，请重试。');
            },
        });
    };

    return (
        <div className="space-y-6">
            {/* 1. 顶部连签关卡成长面板 */}
            <div className="relative overflow-hidden rounded-3xl border border-rose-500/20 bg-gradient-to-b from-card via-card to-rose-500/[0.04] p-5 sm:p-8 shadow-sm">
                <div className="relative z-10 space-y-6">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-border/50 pb-5">
                        <div className="space-y-1.5">
                            <div className="flex items-center gap-2">
                                <Badge className="bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 gap-1.5 px-3 py-0.5 text-xs font-bold rounded-full">
                                    <Flame className="w-3.5 h-3.5 fill-rose-500 text-rose-500 animate-pulse" />
                                    <span>本月成长赛季</span>
                                </Badge>
                                <span className="text-xs text-muted-foreground">
                                    累计打卡 <b className="text-foreground font-mono">{data.total_days}</b> 天
                                </span>
                            </div>

                            <div className="flex items-baseline gap-2">
                                <h1 className="text-2xl sm:text-3xl font-black tracking-tight text-foreground">
                                    已连续打卡
                                </h1>
                                <span className="text-4xl sm:text-5xl font-black font-mono bg-gradient-to-r from-rose-500 to-red-600 bg-clip-text text-transparent">
                                    {data.continuous_days}
                                </span>
                                <span className="text-sm sm:text-base font-bold text-muted-foreground">天</span>
                            </div>
                        </div>

                        {/* 资产胶囊看板 */}
                        <div className="flex items-center gap-2.5">
                            <div className="flex items-center gap-2.5 px-3.5 py-2.5 rounded-2xl bg-muted/40 border border-border/80 shadow-2xs">
                                <Coins className="w-4 h-4 text-rose-500" />
                                <div className="text-left">
                                    <p className="text-xs text-muted-foreground leading-none">金币总额</p>
                                    <p className="text-sm font-black font-mono text-foreground leading-tight mt-1">{walletCoins}</p>
                                </div>
                            </div>

                            <div className="flex items-center gap-2.5 px-3.5 py-2.5 rounded-2xl bg-muted/40 border border-border/80 shadow-2xs">
                                <Ticket className="w-4 h-4 text-primary" />
                                <div className="text-left">
                                    <p className="text-xs text-muted-foreground leading-none">可用补签卡</p>
                                    <p className="text-sm font-black font-mono text-foreground leading-tight mt-1">{makeUpCards} <span className="text-xs font-normal text-muted-foreground">张</span></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* 阶梯里程碑关卡网格 */}
                    <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
                        {milestoneDays.map((day) => {
                            const config = milestonesConfig[day];
                            const isReached = data.continuous_days >= day;
                            const isNextTarget = !isReached && data.continuous_days < day && (
                                milestoneDays.find(d => data.continuous_days < d) === day
                            );

                            return (
                                <div
                                    key={day}
                                    className={cn(
                                        "relative group flex flex-col justify-between p-3.5 sm:p-4 rounded-2xl border transition-all duration-300",
                                        isReached
                                            ? "bg-gradient-to-b from-rose-500/10 to-rose-500/[0.02] border-rose-500/40 shadow-sm"
                                            : isNextTarget
                                                ? "bg-card border-rose-500/60 shadow-md ring-2 ring-rose-500/15"
                                                : "bg-muted/20 border-border/50 opacity-70 hover:opacity-100"
                                    )}
                                >
                                    <div className="flex items-start justify-between">
                                        <div className="text-2xl sm:text-3xl select-none leading-none flex items-center justify-center">
                                            {config?.icon || '🎁'}
                                        </div>

                                        {isReached ? (
                                            <span className="flex items-center gap-1 text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-500/15 px-2 py-0.5 rounded-full border border-rose-500/20 font-mono">
                                                <Check className="w-3 h-3" /> 已领
                                            </span>
                                        ) : isNextTarget ? (
                                            <span className="text-xs font-bold text-rose-600 dark:text-rose-400 bg-rose-500/15 px-2 py-0.5 rounded-full border border-rose-500/30 animate-pulse">
                                                进行中
                                            </span>
                                        ) : (
                                            <span className="flex items-center gap-1 text-xs text-muted-foreground/70 bg-muted/60 px-2 py-0.5 rounded-md">
                                                <Lock className="w-3 h-3" /> 锁定
                                            </span>
                                        )}
                                    </div>

                                    <div className="mt-3 space-y-1.5">
                                        <div className="flex items-baseline justify-between">
                                            <span className="text-xs font-bold text-foreground">
                                                {config?.is_full_month ? '满月全勤' : '连签目标'}
                                            </span>
                                            <span className="text-sm font-black font-mono text-foreground">{day} 天</span>
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1 pt-0.5">
                                            <span className="inline-flex items-center gap-0.5 text-xs font-mono font-bold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded-md">
                                                <Coins className="w-3 h-3" />+{config?.coins}
                                            </span>
                                            {(config?.cards ?? 0) > 0 && (
                                                <span className="inline-flex items-center gap-0.5 text-xs font-mono font-bold text-primary bg-primary/10 px-1.5 py-0.5 rounded-md">
                                                    <Ticket className="w-3 h-3" />+{config?.cards}
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            {/* 2. 主体日历与操作栏 */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div className="lg:col-span-8 rounded-3xl border border-border/80 bg-card p-4 sm:p-6 space-y-5 sm:space-y-6 shadow-xs">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-4 border-b border-border/60 pb-4">
                        <div className="flex items-center justify-between sm:justify-start gap-2">
                            <div className="flex items-center gap-2">
                                <CalendarIcon className="w-4 h-4 sm:w-5 sm:h-5 text-rose-500 shrink-0" />
                                <h2 className="text-sm sm:text-base md:text-lg font-bold text-foreground">
                                    {year} 年 {month} 月
                                </h2>
                            </div>

                            <div className="flex items-center gap-1 ml-1 sm:ml-2">
                                <Button
                                    asChild={canGoPrev}
                                    disabled={!canGoPrev}
                                    variant="ghost"
                                    size="icon"
                                    className="h-7 w-7 sm:h-8 sm:w-8 rounded-lg disabled:opacity-30"
                                >
                                    {canGoPrev ? (
                                        <Link
                                            href="/checkin"
                                            data={{ month: prevMonthStr }}
                                            preserveState
                                            preserveScroll
                                            only={['checkInData']}
                                            title="上一月"
                                        >
                                            <ChevronLeft className="w-4 h-4" />
                                        </Link>
                                    ) : (
                                        <span>
                                            <ChevronLeft className="w-4 h-4" />
                                        </span>
                                    )}
                                </Button>

                                <Button
                                    asChild={canGoNext}
                                    disabled={!canGoNext}
                                    variant="ghost"
                                    size="icon"
                                    className="h-7 w-7 sm:h-8 sm:w-8 rounded-lg disabled:opacity-30"
                                >
                                    {canGoNext ? (
                                        <Link
                                            href="/checkin"
                                            data={{ month: nextMonthStr }}
                                            preserveState
                                            preserveScroll
                                            only={['checkInData']}
                                            title="下一月"
                                        >
                                            <ChevronRight className="w-4 h-4" />
                                        </Link>
                                    ) : (
                                        <span>
                                            <ChevronRight className="w-4 h-4" />
                                        </span>
                                    )}
                                </Button>
                            </div>
                        </div>

                        <div className="flex items-center justify-between sm:justify-end text-xs sm:text-sm text-muted-foreground">
                            <span>
                                本月已打卡 <b className="text-foreground font-mono font-bold text-sm">{data.signed_dates.length}</b> / {daysInMonth} 天
                            </span>
                        </div>
                    </div>

                    <div className="grid grid-cols-7 gap-1.5 sm:gap-2 text-center text-xs sm:text-sm font-semibold text-muted-foreground">
                        {['日', '一', '二', '三', '四', '五', '六'].map((d, i) => (
                            <div key={i} className="py-0.5 sm:py-1">周{d}</div>
                        ))}
                    </div>

                    {/* 日历网格 */}
                    <div className="grid grid-cols-7 gap-1.5 sm:gap-2">
                        {Array.from({ length: firstDayWeekIndex }).map((_, i) => (
                            <div key={`empty-${i}`} className="min-h-[3.25rem] sm:min-h-[3.75rem] rounded-xl sm:rounded-2xl bg-muted/5 border border-transparent" />
                        ))}

                        {Array.from({ length: daysInMonth }).map((_, i) => {
                            const dayNum = i + 1;
                            const formattedMonth = String(month).padStart(2, '0');
                            const formattedDay = String(dayNum).padStart(2, '0');
                            const dateStr = `${year}-${formattedMonth}-${formattedDay}`;

                            const isSigned = data.signed_dates.some((signedDate) => {
                                if (!signedDate) return false;
                                return signedDate === dateStr || signedDate.startsWith(dateStr);
                            });

                            const isToday = dateStr === todayDateString;
                            const isPast = dateStr < todayDateString;

                            const isCurrentViewingMonth = currentYearMonth === currentMonthString;
                            const isMissed = isPast && !isSigned;
                            const isEligibleForMakeUp = isMissed && isCurrentViewingMonth;
                            const isExpired = isMissed && !isCurrentViewingMonth;

                            return (
                                <div
                                    key={dateStr}
                                    onClick={() => isEligibleForMakeUp && handleSelectMakeUpDate(dateStr)}
                                    className={cn(
                                        "relative min-h-[3.25rem] sm:min-h-[3.75rem] rounded-xl sm:rounded-2xl border flex flex-col items-center justify-center gap-0.5 sm:gap-1 p-1 transition-all select-none text-center",
                                        isSigned
                                            ? "bg-rose-500/10 border-rose-500/30 text-rose-700 dark:text-rose-300 font-bold"
                                            : isToday
                                                ? "border-rose-500/60 bg-rose-500/5 text-rose-600 dark:text-rose-400 font-bold ring-2 ring-rose-500/20"
                                                : isEligibleForMakeUp
                                                    ? "bg-muted/15 border-dashed border-border/70 hover:border-rose-500/60 hover:bg-rose-500/5 cursor-pointer group"
                                                    : isExpired
                                                        ? "bg-muted/5 border-border/20 text-muted-foreground/30 cursor-not-allowed"
                                                        : "bg-muted/10 border-border/40 text-muted-foreground/60"
                                    )}
                                >
                                    <span className="font-mono text-xs sm:text-sm font-medium leading-none text-center">
                                        {dayNum}
                                    </span>

                                    {isSigned ? (
                                        <div className="flex items-center justify-center gap-0.5 text-xs text-rose-600 dark:text-rose-400 font-mono font-bold leading-none w-full">
                                            <CheckCircle2 className="w-3 h-3 text-rose-500 shrink-0 hidden sm:inline-block" />
                                            <span>+{baseCoins}</span>
                                        </div>
                                    ) : isToday ? (
                                        <span className="text-xs font-semibold text-rose-600 dark:text-rose-400 leading-none w-full text-center">
                                            今天
                                        </span>
                                    ) : isEligibleForMakeUp ? (
                                        <span className="text-xs text-muted-foreground group-hover:text-rose-600 dark:group-hover:text-rose-400 font-semibold leading-none w-full text-center">
                                            补签
                                        </span>
                                    ) : isExpired ? (
                                        <span className="text-xs text-muted-foreground/40 leading-none w-full text-center">
                                            逾期
                                        </span>
                                    ) : null}
                                </div>
                            );
                        })}
                    </div>
                </div>

                {/* 右侧动作栏 */}
                <div className="lg:col-span-4 space-y-6">
                    <div className="rounded-3xl border border-border/80 bg-card p-6 text-center space-y-5 shadow-xs">
                        <div className="space-y-1">
                            <p className="text-xs sm:text-sm text-muted-foreground font-medium">今日打卡奖励</p>
                            <div className="flex items-center justify-center gap-2 text-2xl sm:text-3xl font-black text-rose-500 font-mono">
                                <Coins className="w-7 h-7 text-rose-500" />
                                <span>+{baseCoins} 金币</span>
                            </div>
                            <div className="flex items-center justify-center gap-1.5 text-xs text-muted-foreground pt-1">
                                <Wallet className="w-3.5 h-3.5 text-muted-foreground/80" />
                                <span>当前拥有:</span>
                                <span className="font-mono font-bold text-foreground text-sm">{walletCoins}</span>
                                <span>金币</span>
                            </div>
                        </div>

                        {data.is_checked_today ? (
                            <Button
                                disabled
                                className="w-full h-13 rounded-2xl bg-emerald-600 text-white font-bold text-base cursor-default opacity-90 shadow-md gap-2"
                            >
                                <CheckCircle2 className="w-5 h-5" /> 今日已完成打卡
                            </Button>
                        ) : (
                            <Button
                                onClick={handleCheckIn}
                                disabled={checkInHttp.processing}
                                className="w-full h-13 rounded-2xl bg-gradient-to-r from-rose-500 to-red-600 hover:from-rose-600 hover:to-red-700 text-white font-bold text-base shadow-lg shadow-rose-500/25 active:scale-98 transition-transform gap-2"
                            >
                                <Sparkles className={cn("w-5 h-5", checkInHttp.processing && "animate-spin")} />
                                {checkInHttp.processing ? '正在提交打卡...' : '立即打卡领取'}
                            </Button>
                        )}

                        <p className="text-xs text-muted-foreground">
                            每日凌晨 00:00 自动重置打卡状态
                        </p>
                    </div>

                    <div className="rounded-3xl border border-rose-500/30 bg-rose-500/5 dark:bg-rose-500/10 p-5 space-y-4 shadow-xs">
                        <div className="flex items-center justify-between border-b border-rose-500/20 pb-3">
                            <span className="font-bold text-foreground text-sm sm:text-base flex items-center gap-2">
                                <Ticket className="w-4 h-4 text-rose-500" /> 补签卡资产中心
                            </span>
                            <Badge variant="outline" className={cn(
                                "text-xs font-mono font-medium px-2 py-0.5",
                                remainingMonthMakeUp <= 0
                                    ? "bg-destructive/10 text-destructive border-destructive/20"
                                    : "bg-background/80"
                            )}>
                                本月已用 {monthUsedCount}/{maxMonthMakeUp}
                            </Badge>
                        </div>

                        <div className="flex items-center justify-between p-4 rounded-2xl bg-background border border-rose-500/20 shadow-2xs">
                            <div className="space-y-1">
                                <span className="text-xs sm:text-sm font-semibold text-muted-foreground block">当前可用补签卡</span>
                                <span className="text-xs text-muted-foreground/80 block">累计已获得 {totalCardsEarned} 张</span>
                            </div>
                            <div className="text-right">
                                <span className="text-3xl sm:text-4xl font-black font-mono text-rose-500">
                                    {makeUpCards}
                                </span>
                                <span className="text-xs sm:text-sm font-bold text-muted-foreground ml-1">张</span>
                            </div>
                        </div>

                        <p className="text-xs text-muted-foreground leading-relaxed">
                            仅支持补签当月的漏签日期，每次消耗 1 张补签卡即可修复断签。
                        </p>
                    </div>

                    <div className="rounded-3xl border border-border/80 bg-card p-5 space-y-3 shadow-xs text-xs sm:text-sm">
                        <h3 className="font-bold text-foreground flex items-center gap-1.5 text-sm">
                            <HelpCircle className="w-4 h-4 text-rose-500" /> 打卡与连签规则
                        </h3>
                        <ul className="space-y-2 text-muted-foreground leading-relaxed list-disc list-inside text-xs sm:text-sm">
                            <li>每日签到基础获得 <b className="text-foreground">{baseCoins}</b> 金币。</li>
                            {milestoneDays.map((day) => {
                                const config = milestonesConfig[day];
                                return (
                                    <li key={day}>
                                        {config?.is_full_month ? (
                                            <>当月满月全勤满 <b className="text-foreground">{day}</b> 天：赠送{' '}</>
                                        ) : (
                                            <>连续打卡满 <b className="text-foreground">{day}</b> 天：赠送{' '}</>
                                        )}
                                        <b className="text-rose-500 font-bold">+{config.coins}</b> 金币
                                        {(config?.cards ?? 0) > 0 && (
                                            <> 及 <b className="text-primary font-bold">+{config.cards}</b> 张补签卡</>
                                        )}。
                                    </li>
                                );
                            })}
                            <li>补签仅限当月，每月最多允许补签 <b className="text-foreground">{maxMonthMakeUp}</b> 次。</li>
                        </ul>
                    </div>
                </div>
            </div>

            {/* 3. 补签确认弹窗 */}
            <Dialog open={!!makeUpDate} onOpenChange={(open) => !open && setMakeUpDate(null)}>
                <DialogContent className="max-w-sm text-center p-6 rounded-3xl bg-background/95 backdrop-blur-xl border border-border shadow-2xl">
                    <DialogHeader className="space-y-2">
                        <div className="size-14 rounded-full bg-rose-500/10 text-rose-500 mx-auto flex items-center justify-center">
                            <RotateCcw className="w-7 h-7" />
                        </div>
                        <DialogTitle className="text-lg font-bold text-foreground">确认补签</DialogTitle>
                        <DialogDescription className="text-xs sm:text-sm text-muted-foreground">
                            补签所属日期：<b className="text-foreground font-mono text-sm">{makeUpDate}</b>
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-3 text-xs sm:text-sm space-y-2 text-left bg-muted/30 p-4 rounded-2xl border border-border/50">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">当前持有补签卡</span>
                            <span className="font-bold font-mono text-foreground">{makeUpCards} 张</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">本月剩余可用额度</span>
                            <span className={cn("font-bold font-mono", remainingMonthMakeUp <= 0 ? "text-destructive" : "text-foreground")}>
                                {remainingMonthMakeUp} 次
                            </span>
                        </div>
                        <div className="flex justify-between border-t border-border/40 pt-2 font-medium">
                            <span className="text-muted-foreground">本次消耗</span>
                            <span className="font-bold font-mono text-rose-500">1 张补签卡</span>
                        </div>
                    </div>

                    {remainingMonthMakeUp <= 0 ? (
                        <div className="flex items-center gap-2 text-xs sm:text-sm text-destructive p-3 bg-destructive/10 rounded-2xl text-left">
                            <AlertCircle className="w-4 h-4 shrink-0" />
                            <span>本月补签次数已达上限 ({maxMonthMakeUp}/{maxMonthMakeUp})，下月 1 日重置。</span>
                        </div>
                    ) : makeUpCards < 1 ? (
                        <div className="flex items-center gap-2 text-xs sm:text-sm text-destructive p-3 bg-destructive/10 rounded-2xl text-left">
                            <AlertCircle className="w-4 h-4 shrink-0" />
                            <span>补签卡不足，完成连续打卡任务即可获得！</span>
                        </div>
                    ) : null}

                    <div className="flex gap-2 pt-2">
                        <Button
                            variant="outline"
                            onClick={() => setMakeUpDate(null)}
                            className="flex-1 rounded-2xl text-xs sm:text-sm"
                        >
                            取消
                        </Button>
                        <Button
                            onClick={handleConfirmMakeUp}
                            disabled={makeUpHttp.processing || makeUpCards < 1 || remainingMonthMakeUp <= 0}
                            className="flex-1 rounded-2xl text-xs sm:text-sm font-bold bg-rose-600 hover:bg-rose-700 text-white"
                        >
                            {makeUpHttp.processing
                                ? '正在补签...'
                                : remainingMonthMakeUp <= 0
                                    ? '本月额度已满'
                                    : makeUpCards < 1
                                        ? '补签卡不足'
                                        : '确认补签'}
                        </Button>
                    </div>
                </DialogContent>
            </Dialog>

            {/* 🌟 4. 成功弹窗（标题和说明动态适配） */}
            <Dialog open={!!rewardModal} onOpenChange={() => setRewardModal(null)}>
                <DialogContent className="max-w-sm text-center p-6 rounded-3xl bg-background/95 backdrop-blur-xl border border-border shadow-2xl">
                    <DialogHeader className="space-y-2">
                        <div className="size-16 rounded-full bg-rose-500/10 text-rose-500 mx-auto flex items-center justify-center">
                            <PartyPopper className="w-8 h-8 animate-bounce" />
                        </div>
                        {/* 🌟 动态显示：补签成功！或 打卡成功！ */}
                        <DialogTitle className="text-xl font-black text-foreground">
                            {rewardModal?.message || (rewardModal?.is_make_up ? '补签成功！' : '打卡成功！')}
                        </DialogTitle>
                        <DialogDescription className="text-xs sm:text-sm text-muted-foreground">
                            {rewardModal?.is_make_up && rewardModal?.target_date ? (
                                <>
                                    已补齐 <b className="font-mono text-foreground">{rewardModal.target_date}</b> 的打卡，连签天数已更新为 <b className="font-mono text-foreground">{rewardModal?.continuous_days}</b> 天
                                </>
                            ) : (
                                <>
                                    连续签到天数已更新为 <b className="font-mono text-foreground">{rewardModal?.continuous_days}</b> 天
                                </>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-4 space-y-3">
                        <div className="p-4 rounded-2xl bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400">
                            <p className="text-xs sm:text-sm font-semibold">金币奖励</p>
                            <p className="text-3xl font-black font-mono mt-0.5">+{rewardModal?.total_earned_coins}</p>
                            {rewardModal?.bonus_coins > 0 && (
                                <p className="text-xs text-rose-600/80 mt-1 font-medium">
                                    (含里程碑额外奖励 +{rewardModal?.bonus_coins} 金币)
                                </p>
                            )}
                        </div>

                        {rewardModal?.earned_cards > 0 && (
                            <div className="p-3 rounded-2xl bg-primary/10 border border-primary/20 text-primary flex items-center justify-between text-xs sm:text-sm">
                                <span className="font-semibold flex items-center gap-1.5">
                                    <Ticket className="w-4 h-4" /> 阶梯里程碑额外赠送
                                </span>
                                <span className="font-mono font-bold text-sm">
                                    +{rewardModal.earned_cards} 张补签卡
                                </span>
                            </div>
                        )}
                    </div>

                    <Button onClick={() => setRewardModal(null)} className="w-full rounded-2xl text-sm font-bold bg-rose-600 hover:bg-rose-700 text-white">
                        开心收下
                    </Button>
                </DialogContent>
            </Dialog>
        </div>
    );
}

// ==================== 主页面入口组件 ====================

export default function CheckInIndexPage({
    breadcrumbs,
    checkInData,
    milestonesConfig,
    baseCoins,
    allowedMonths,
}: Props) {
    const milestoneCount = useMemo(() => {
        return Object.keys(milestonesConfig || {}).length || 5;
    }, [milestonesConfig]);

    return (
        <>
            <Head title="每日签到" />

            <div className="min-h-screen bg-background/60 py-4 sm:py-6 pb-24">
                <div className="max-w-5xl mx-auto px-3 sm:px-6 space-y-5 sm:space-y-6">

                    <Deferred
                        data="checkInData"
                        fallback={<CheckInSkeleton milestoneCount={milestoneCount} />}
                    >
                        {checkInData && (
                            <CheckInContent
                                checkInData={checkInData}
                                milestonesConfig={milestonesConfig}
                                baseCoins={baseCoins}
                                allowedMonths={allowedMonths}
                            />
                        )}
                    </Deferred>
                </div>
            </div>
        </>
    );
}
