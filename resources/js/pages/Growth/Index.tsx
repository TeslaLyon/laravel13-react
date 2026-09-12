import React, { useState } from 'react';
import { Head, Link, Deferred } from '@inertiajs/react';
import {
    ShieldCheck,
    CheckCircle2,
    ChevronRight,
    Trophy,
    Flame,
    Zap,
    Search,
    Type,
    Upload,
    FileText,
    Sparkles,
    Check,
    Lock,
    Calculator,
    History,
    Inbox,
} from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

// ==================== 类型定义 ====================

export interface UserGroupItem {
    id: number;
    name: string;
    title: string;
    level: number;
    credits_min: string | number;
    credits_max: string | number | null;
    read_permission_level: number;
    allow_search: boolean;
    allow_custom_title: boolean;
    allow_upload_attachment: boolean;
    daily_post_limit: number;
    banner_bg_color?: string;
    banner_text_color?: string;
}

export interface MetricBreakdownItem {
    key: string;
    title: string;
    count: number;
    weight: number;
    subtotal: number;
    unit: string;
    is_deduction: boolean;
}

export interface GrowthTaskItem {
    id: string;
    title: string;
    description: string;
    reward_desc: string;
    is_completed: boolean;
    action_url: string;
    action_text: string;
}

export interface CreditLogItem {
    id: number;
    action: string;
    field: string;
    change_amount: string | number;
    after_value: string | number;
    current_total_credits: string | number;
    remark?: string | null;
    created_at: string;
}

export interface GrowthCenterData {
    user: {
        id: number;
        name: string;
        avatar?: string;
        custom_title?: string;
        cached_title?: string;
        display_title: string;
        total_credits: number;
        needed_credits: number;
        progress_percent: number;
    };
    current_group: UserGroupItem;
    next_group: UserGroupItem | null;
    all_groups: UserGroupItem[];
    metrics_breakdown: MetricBreakdownItem[];
    tasks: GrowthTaskItem[];
    credit_logs: CreditLogItem[];
}

interface Props {
    breadcrumbs: BreadcrumbItemType[];
    growthData?: GrowthCenterData;
}

// ==================== 1:1 响应式骨架屏 ====================

function GrowthSkeleton() {
    return (
        <div className="space-y-6 animate-pulse">
            {/* 1. 顶部 Hero 卡片骨架 */}
            <div className="rounded-3xl border border-border/70 bg-card p-6 sm:p-8 space-y-6 shadow-xs">
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="flex items-center gap-3.5">
                        <Skeleton className="size-14 sm:size-16 rounded-2xl" />
                        <div className="space-y-2">
                            <div className="flex items-center gap-2">
                                <Skeleton className="h-7 w-32 rounded-lg" />
                                <Skeleton className="h-5 w-20 rounded-full" />
                            </div>
                            <Skeleton className="h-4 w-40 rounded-md" />
                        </div>
                    </div>

                    <div className="flex items-center gap-4 p-4 rounded-2xl border border-border/50 bg-muted/20">
                        <div className="space-y-1.5">
                            <Skeleton className="h-3 w-16 rounded-md" />
                            <Skeleton className="h-8 w-24 rounded-lg" />
                        </div>
                        <div className="h-8 w-px bg-border/40 mx-1" />
                        <div className="space-y-1.5">
                            <Skeleton className="h-3 w-20 rounded-md" />
                            <Skeleton className="h-8 w-24 rounded-lg" />
                        </div>
                    </div>
                </div>

                <div className="mt-6 space-y-2 pt-4 border-t border-border/50">
                    <div className="flex justify-between items-center">
                        <Skeleton className="h-4 w-32 rounded-md" />
                        <Skeleton className="h-4 w-40 rounded-md" />
                    </div>
                    <Skeleton className="h-3 w-full rounded-full" />
                </div>
            </div>

            {/* 2. 积分公式拆解卡片骨架 */}
            <div className="rounded-3xl border border-border/70 bg-card p-5 sm:p-6 space-y-5 shadow-xs">
                <div className="flex justify-between items-center border-b border-border/50 pb-4">
                    <div className="space-y-1.5">
                        <Skeleton className="h-6 w-48 rounded-lg" />
                        <Skeleton className="h-3.5 w-72 rounded-md" />
                    </div>
                    <Skeleton className="h-6 w-28 rounded-full" />
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
                    {Array.from({ length: 7 }).map((_, i) => (
                        <div key={i} className="p-3 rounded-2xl border border-border/50 bg-muted/10 h-24 flex flex-col justify-between items-center">
                            <Skeleton className="h-3 w-14 rounded-md" />
                            <Skeleton className="h-6 w-16 rounded-md" />
                            <Skeleton className="h-3 w-12 rounded-md" />
                        </div>
                    ))}
                </div>
            </div>

            {/* 3. 用户组等级阶梯与权限骨架 */}
            <div className="rounded-3xl border border-border/70 bg-card p-5 sm:p-6 space-y-6 shadow-xs">
                <div className="border-b border-border/50 pb-4 space-y-1.5">
                    <Skeleton className="h-6 w-44 rounded-lg" />
                    <Skeleton className="h-3.5 w-64 rounded-md" />
                </div>

                <div className="grid grid-cols-4 sm:grid-cols-8 gap-2">
                    {Array.from({ length: 8 }).map((_, i) => (
                        <Skeleton key={i} className="h-16 rounded-2xl" />
                    ))}
                </div>

                <div className="p-5 rounded-2xl border border-border/50 bg-muted/10 space-y-4">
                    <div className="flex justify-between items-center border-b border-border/40 pb-3">
                        <Skeleton className="h-6 w-48 rounded-lg" />
                        <Skeleton className="h-5 w-28 rounded-md" />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                        {Array.from({ length: 4 }).map((_, i) => (
                            <Skeleton key={i} className="h-16 rounded-xl" />
                        ))}
                    </div>
                </div>
            </div>

            {/* 4. 双栏布局骨架 */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <div className="lg:col-span-5 rounded-3xl border border-border/70 bg-card p-5 sm:p-6 space-y-4 shadow-xs">
                    <div className="border-b border-border/50 pb-3 space-y-1">
                        <Skeleton className="h-5 w-36 rounded-md" />
                        <Skeleton className="h-3 w-48 rounded-md" />
                    </div>
                    <div className="space-y-2.5">
                        {Array.from({ length: 3 }).map((_, i) => (
                            <Skeleton key={i} className="h-24 rounded-2xl" />
                        ))}
                    </div>
                </div>

                <div className="lg:col-span-7 rounded-3xl border border-border/70 bg-card p-5 sm:p-6 space-y-4 shadow-xs">
                    <div className="border-b border-border/50 pb-3 space-y-1">
                        <Skeleton className="h-5 w-36 rounded-md" />
                        <Skeleton className="h-3 w-48 rounded-md" />
                    </div>
                    <div className="space-y-2.5">
                        {Array.from({ length: 4 }).map((_, i) => (
                            <Skeleton key={i} className="h-14 rounded-2xl" />
                        ))}
                    </div>
                </div>
            </div>
        </div>
    );
}

// ==================== 真实内容组件 ====================

function GrowthContent({ growthData }: { growthData: GrowthCenterData }) {
    const { user, current_group, next_group, all_groups, metrics_breakdown, tasks, credit_logs } = growthData;
    const [selectedLevel, setSelectedLevel] = useState<number>(current_group?.level ?? 1);
    const activeGroupDetail = all_groups.find((g) => g.level === selectedLevel) || current_group;

    return (
        <div className="space-y-6">
            {/* 🌟 1. 顶部 Hero 成长卡片 */}
            <div className="relative overflow-hidden rounded-3xl border border-rose-500/20 bg-gradient-to-br from-card via-card to-rose-500/[0.04] p-6 sm:p-8 shadow-sm">
                <div className="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-6">

                    {/* 用户信息 */}
                    <div className="space-y-3">
                        <div className="flex items-center gap-3.5">
                            <div className="size-14 sm:size-16 rounded-2xl bg-gradient-to-tr from-rose-500 to-amber-500 p-0.5 shadow-md shrink-0">
                                <div className="size-full rounded-[14px] bg-card flex items-center justify-center font-bold text-lg text-foreground overflow-hidden">
                                    {user.avatar ? (
                                        <img src={user.avatar} alt={user.name} className="size-full object-cover" />
                                    ) : (
                                        user.name.slice(0, 2).toUpperCase()
                                    )}
                                </div>
                            </div>

                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-xl sm:text-2xl font-black text-foreground tracking-tight">
                                        {user.name}
                                    </h1>
                                    <Badge className="bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 font-mono font-black text-xs px-2.5 py-0.5 rounded-full">
                                        {current_group?.title || `Lv.${current_group?.level}`}
                                    </Badge>
                                </div>
                                <p className="text-xs sm:text-sm text-muted-foreground mt-1">
                                    头衔：<span className="font-semibold text-foreground">{user.display_title}</span>
                                    {user.custom_title && <span className="text-[11px] text-rose-500 ml-1.5">(自定义)</span>}
                                </p>
                            </div>
                        </div>
                    </div>

                    {/* 积分看板 */}
                    <div className="flex items-center gap-4 bg-muted/30 border border-border/80 p-4 rounded-2xl">
                        <div className="text-left space-y-1">
                            <span className="text-xs text-muted-foreground flex items-center gap-1 font-medium">
                                <Flame className="w-3.5 h-3.5 text-rose-500 fill-rose-500" /> 当前总积分
                            </span>
                            <p className="text-2xl font-black font-mono text-foreground">
                                {user.total_credits} <span className="text-xs font-normal text-muted-foreground">Pts</span>
                            </p>
                        </div>
                        <div className="h-8 w-px bg-border/60 mx-1" />
                        <div className="text-left space-y-1">
                            <span className="text-xs text-muted-foreground flex items-center gap-1 font-medium">
                                <Zap className="w-3.5 h-3.5 text-amber-500 fill-amber-500" /> 距离升级还需
                            </span>
                            <p className="text-2xl font-black font-mono text-amber-500">
                                {next_group ? user.needed_credits : 0} <span className="text-xs font-normal text-muted-foreground">Pts</span>
                            </p>
                        </div>
                    </div>
                </div>

                {/* 等级晋升进度条 */}
                <div className="mt-6 space-y-2 pt-4 border-t border-border/50">
                    <div className="flex items-center justify-between text-xs font-mono font-semibold">
                        <span className="text-foreground flex items-center gap-1">
                            <Trophy className="w-3.5 h-3.5 text-rose-500" /> 当前等级：{current_group?.title}
                        </span>
                        <span className="text-muted-foreground">
                            {next_group ? `下一等级：${next_group.title} (${user.progress_percent}%)` : '已达成最高荣耀等级'}
                        </span>
                    </div>
                    <div className="h-3 w-full rounded-full bg-muted/60 overflow-hidden p-0.5 border border-border/50">
                        <div
                            className="h-full rounded-full bg-gradient-to-r from-rose-500 via-red-500 to-amber-500 transition-all duration-700 ease-out shadow-xs"
                            style={{ width: `${user.progress_percent}%` }}
                        />
                    </div>
                </div>
            </div>

            {/* 🌟 2. 积分公式拆解与贡献明细 */}
            <div className="rounded-3xl border border-border/80 bg-card p-5 sm:p-6 space-y-5 shadow-xs">
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/60 pb-4">
                    <div className="space-y-0.5">
                        <h2 className="text-base sm:text-lg font-bold text-foreground flex items-center gap-2">
                            <Calculator className="w-5 h-5 text-rose-500" /> 总积分构成与公式拆解
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            总积分 = 发帖×0.1 + 热心×1.2 + 悬赏×1.5 + 贡献×1.5 + 威望×20 + 精华×100 - 违规×20
                        </p>
                    </div>
                    <Badge variant="outline" className="font-mono text-xs font-bold text-rose-600 dark:text-rose-400 border-rose-500/30 w-fit">
                        累计计算值: {user.total_credits} Pts
                    </Badge>
                </div>

                <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2.5">
                    {metrics_breakdown.map((item) => (
                        <div
                            key={item.key}
                            className={cn(
                                "p-3 rounded-2xl border flex flex-col justify-between space-y-2 text-center",
                                item.is_deduction
                                    ? "bg-destructive/5 border-destructive/20 text-destructive"
                                    : "bg-muted/15 border-border/60"
                            )}
                        >
                            <div>
                                <span className="text-xs font-semibold text-muted-foreground block truncate">
                                    {item.title}
                                </span>
                                <span className="text-lg font-black font-mono text-foreground mt-0.5 block">
                                    {item.count} <span className="text-[10px] font-normal text-muted-foreground">{item.unit}</span>
                                </span>
                            </div>
                            <div className="border-t border-border/40 pt-1.5 font-mono text-xs font-bold">
                                <span className={item.is_deduction ? "text-destructive" : "text-rose-600 dark:text-rose-400"}>
                                    {item.subtotal >= 0 ? `+${item.subtotal}` : item.subtotal}
                                </span>
                                <span className="text-[10px] text-muted-foreground block font-normal">
                                    权重 {item.weight > 0 ? `×${item.weight}` : `×(${item.weight})`}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* 🌟 3. 用户组等级阶梯与权限对比矩阵 */}
            <div className="rounded-3xl border border-border/80 bg-card p-5 sm:p-6 space-y-6 shadow-xs">
                <div className="flex items-center justify-between border-b border-border/60 pb-4">
                    <div className="space-y-0.5">
                        <h2 className="text-base sm:text-lg font-bold text-foreground flex items-center gap-2">
                            <ShieldCheck className="w-5 h-5 text-rose-500" /> 用户组权限与等级阶梯
                        </h2>
                        <p className="text-xs text-muted-foreground">积分达到指定阈值将自动晋升并解锁对应的社区权限</p>
                    </div>
                </div>

                {/* 等级横向标签栏 */}
                <div className="grid grid-cols-4 sm:grid-cols-8 gap-2">
                    {all_groups.map((group) => {
                        const isCurrent = group.level === current_group?.level;
                        const isSelected = group.level === selectedLevel;
                        const isUnlocked = (current_group?.level ?? 1) >= group.level;

                        return (
                            <button
                                key={group.id}
                                type="button"
                                onClick={() => setSelectedLevel(group.level)}
                                className={cn(
                                    "relative flex flex-col items-center justify-center p-2.5 rounded-2xl border transition-all select-none cursor-pointer",
                                    isSelected
                                        ? "border-rose-500 bg-rose-500/10 ring-2 ring-rose-500/20 shadow-xs"
                                        : isUnlocked
                                            ? "border-border/80 bg-card hover:border-border"
                                            : "border-border/40 bg-muted/10 opacity-60 hover:opacity-100"
                                )}
                            >
                                <span className={cn(
                                    "font-mono font-black text-sm",
                                    isSelected ? "text-rose-600 dark:text-rose-400" : isUnlocked ? "text-foreground" : "text-muted-foreground"
                                )}>
                                    Lv.{group.level}
                                </span>
                                <span className="text-[11px] text-muted-foreground mt-0.5 truncate w-full text-center">
                                    {group.title.replace(/Lv\.\d+\s*/, '')}
                                </span>

                                {isCurrent && (
                                    <span className="absolute -top-1.5 -right-1.5 size-3 bg-rose-500 rounded-full border-2 border-background animate-pulse" />
                                )}
                            </button>
                        );
                    })}
                </div>

                {/* 选中等级详情与权限卡片 */}
                {activeGroupDetail && (
                    <div className="p-4 sm:p-5 rounded-2xl bg-muted/20 border border-border/60 space-y-4">
                        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-border/40 pb-3">
                            <div className="flex items-center gap-2">
                                <Badge className="bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30 font-mono font-bold text-xs">
                                    Lv.{activeGroupDetail.level}
                                </Badge>
                                <h3 className="font-bold text-foreground text-sm sm:text-base">
                                    {activeGroupDetail.title}
                                </h3>
                                <span className="text-xs text-muted-foreground font-mono">
                                    (需总积分: {activeGroupDetail.credits_min} ~ {activeGroupDetail.credits_max ?? '无上限'} Pts)
                                </span>
                            </div>

                            <span className={cn(
                                "text-xs font-bold font-mono px-2 py-0.5 rounded-md w-fit flex items-center gap-1",
                                (current_group?.level ?? 1) >= activeGroupDetail.level
                                    ? "bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
                                    : "bg-muted text-muted-foreground"
                            )}>
                                {(current_group?.level ?? 1) >= activeGroupDetail.level ? (
                                    <><Check className="w-3.5 h-3.5" /> 当前已解锁该组权限</>
                                ) : (
                                    <><Lock className="w-3.5 h-3.5" /> 积分达标后自动解锁</>
                                )}
                            </span>
                        </div>

                        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                            <div className="p-3 rounded-xl bg-card border border-border/50 flex items-center gap-3">
                                <div className="size-8 rounded-lg bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                                    <FileText className="w-4 h-4" />
                                </div>
                                <div className="space-y-0.5">
                                    <p className="text-xs text-muted-foreground">阅读权限门槛</p>
                                    <p className="text-sm font-black font-mono text-foreground">
                                        ≥ {activeGroupDetail.read_permission_level} 级
                                    </p>
                                </div>
                            </div>

                            <div className="p-3 rounded-xl bg-card border border-border/50 flex items-center gap-3">
                                <div className="size-8 rounded-lg bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                                    <Search className="w-4 h-4" />
                                </div>
                                <div className="space-y-0.5">
                                    <p className="text-xs text-muted-foreground">站内搜索功能</p>
                                    <p className="text-sm font-bold text-foreground">
                                        {activeGroupDetail.allow_search ? '✓ 允许使用' : '✕ 禁止'}
                                    </p>
                                </div>
                            </div>

                            <div className="p-3 rounded-xl bg-card border border-border/50 flex items-center gap-3">
                                <div className="size-8 rounded-lg bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                                    <Type className="w-4 h-4" />
                                </div>
                                <div className="space-y-0.5">
                                    <p className="text-xs text-muted-foreground">个性自定义头衔</p>
                                    <p className="text-sm font-bold text-foreground">
                                        {activeGroupDetail.allow_custom_title ? '✓ 支持自定义' : '✕ 暂未解锁'}
                                    </p>
                                </div>
                            </div>

                            <div className="p-3 rounded-xl bg-card border border-border/50 flex items-center gap-3">
                                <div className="size-8 rounded-lg bg-rose-500/10 text-rose-600 flex items-center justify-center shrink-0">
                                    <Upload className="w-4 h-4" />
                                </div>
                                <div className="space-y-0.5">
                                    <p className="text-xs text-muted-foreground">上传附件特权</p>
                                    <p className="text-sm font-bold text-foreground">
                                        {activeGroupDetail.allow_upload_attachment ? '✓ 支持上传' : '✕ 暂未解锁'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                )}
            </div>

            {/* 🌟 4. 双栏布局：成长任务通道 + 积分流水明细 */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">

                {/* 左侧：积分任务 */}
                <div className="lg:col-span-5 rounded-3xl border border-border/80 bg-card p-5 sm:p-6 space-y-4 shadow-xs">
                    <div className="flex items-center justify-between border-b border-border/60 pb-3">
                        <div className="space-y-0.5">
                            <h2 className="text-base font-bold text-foreground flex items-center gap-2">
                                <Sparkles className="w-4 h-4 text-amber-500" /> 积分提升互动通道
                            </h2>
                            <p className="text-xs text-muted-foreground">完成日常活跃快速获取总积分</p>
                        </div>
                    </div>

                    <div className="space-y-2.5">
                        {tasks.map((task) => (
                            <div
                                key={task.id}
                                className="p-3.5 rounded-2xl border border-border/60 bg-muted/10 space-y-2.5"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <h3 className="font-bold text-xs sm:text-sm text-foreground">{task.title}</h3>
                                    <span className="text-[11px] font-mono font-bold text-rose-600 dark:text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded-md shrink-0">
                                        {task.reward_desc}
                                    </span>
                                </div>
                                <p className="text-xs text-muted-foreground leading-relaxed">
                                    {task.description}
                                </p>
                                <div>
                                    {task.is_completed ? (
                                        <Button
                                            disabled
                                            size="sm"
                                            className="w-full rounded-xl text-xs h-7 bg-emerald-600/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 gap-1 opacity-100"
                                        >
                                            <CheckCircle2 className="w-3.5 h-3.5" /> 已完成
                                        </Button>
                                    ) : (
                                        <Button
                                            asChild
                                            size="sm"
                                            className="w-full rounded-xl text-xs h-7 bg-rose-600 hover:bg-rose-700 text-white font-bold gap-1 shadow-sm"
                                        >
                                            <Link href={task.action_url}>
                                                {task.action_text} <ChevronRight className="w-3.5 h-3.5" />
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </div>

                {/* 右侧：积分变动流水日志 */}
                <div className="lg:col-span-7 rounded-3xl border border-border/80 bg-card p-5 sm:p-6 space-y-4 shadow-xs">
                    <div className="flex items-center justify-between border-b border-border/60 pb-3">
                        <div className="space-y-0.5">
                            <h2 className="text-base font-bold text-foreground flex items-center gap-2">
                                <History className="w-4 h-4 text-rose-500" /> 积分成长历程日志
                            </h2>
                            <p className="text-xs text-muted-foreground">记录最近的指标变动明细与积分结余</p>
                        </div>
                    </div>

                    {credit_logs.length === 0 ? (
                        <div className="py-12 text-center text-xs text-muted-foreground space-y-2">
                            <Inbox className="w-8 h-8 text-muted-foreground/40 mx-auto" />
                            <p>暂无积分变动日志，参与发帖或互动即可产生记录！</p>
                        </div>
                    ) : (
                        <div className="space-y-2.5 max-h-[360px] overflow-y-auto pr-1">
                            {credit_logs.map((log) => {
                                const changeAmountNum = Number(log.change_amount);
                                const isPositive = changeAmountNum >= 0;

                                return (
                                    <div
                                        key={log.id}
                                        className="flex items-center justify-between p-3 rounded-2xl bg-muted/15 border border-border/50 text-xs hover:bg-muted/25 transition-colors"
                                    >
                                        <div className="space-y-0.5">
                                            <div className="flex items-center gap-2">
                                                <span className="font-bold text-foreground text-xs sm:text-sm">
                                                    {log.remark || log.action}
                                                </span>
                                                <Badge variant="outline" className="text-[10px] px-1.5 py-0 font-mono text-muted-foreground">
                                                    {log.field}: {isPositive ? `+${log.change_amount}` : log.change_amount}
                                                </Badge>
                                            </div>
                                            <p className="text-[11px] text-muted-foreground font-mono">
                                                {new Date(log.created_at).toLocaleString('zh-CN', { hour12: false })}
                                            </p>
                                        </div>

                                        <div className="text-right font-mono shrink-0 pl-3">
                                            <span className="text-xs font-bold text-rose-600 dark:text-rose-400 block">
                                                总积分快照
                                            </span>
                                            <span className="text-xs font-black text-foreground">
                                                {log.current_total_credits} Pts
                                            </span>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

            </div>
        </div>
    );
}

// ==================== 主页面入口（集成 Deferred） ====================

export default function GrowthIndexPage({ breadcrumbs, growthData }: Props) {
    return (
        <>
            <Head title="成长等级中心 - 社区积分体系" />

            <div className="min-h-screen bg-background/80 py-5 sm:py-8 pb-28">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 space-y-6">

                    {/* 🌟 使用 Deferred 组件处理异步渲染与骨架屏回退 */}
                    <Deferred
                        data="growthData"
                        fallback={<GrowthSkeleton />}
                    >
                        {growthData && (
                            <GrowthContent growthData={growthData} />
                        )}
                    </Deferred>

                </div>
            </div>
        </>
    );
}
