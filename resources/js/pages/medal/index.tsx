import React, { useState, useMemo, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import {
    Award,
    Sparkles,
    Lock,
    CheckCircle2,
    Search,
    Milestone,
    Flame,
    Star,
    Compass,
    Calendar,
    Filter,
    ArrowRight,
    Trophy,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export interface MedalItem {
    id: number;
    code: string;
    title: string;
    description: string;
    conditionText: string;
    iconUrl: string;
    rarity: 'common' | 'rare' | 'epic' | 'legendary';
    trophyPoints: number;
    isUnlocked: boolean;
    unlockedAt?: string | null;
    unlockedRate: number;
}

export interface CategoryGroup {
    id: number;
    name: string;
    slug: string;
    description?: string;
    medals: MedalItem[];
}

interface Props {
    breadcrumbs: BreadcrumbItemType[];
    stats: {
        isLoggedIn: boolean;
        totalCount: number;
        unlockedCount: number;
        completionRate: number;
        totalTrophyPoints: number;
    };
    categories: CategoryGroup[];
}

// 稀有度视觉映射
const RARITY_MAP = {
    common: { label: '普通', className: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20' },
    rare: { label: '稀有', className: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20' },
    epic: { label: '史诗', className: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20' },
    legendary: { label: '传说', className: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' },
};

export default function MedalsIndexPage({ breadcrumbs, stats, categories }: Props) {
    const [searchQuery, setSearchQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<'all' | 'unlocked' | 'locked'>('all');
    const [activeCategoryId, setActiveCategoryId] = useState<number>(categories[0]?.id ?? 0);
    const [selectedMedal, setSelectedMedal] = useState<MedalItem | null>(null);

    // 监听页面滚动自动高亮当前时间线节点
    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        const catId = Number(entry.target.getAttribute('data-category-id'));
                        if (catId) setActiveCategoryId(catId);
                    }
                });
            },
            { rootMargin: '-15% 0px -70% 0px', threshold: 0.1 }
        );

        categories.forEach((cat) => {
            const el = document.getElementById(`category-${cat.id}`);
            if (el) observer.observe(el);
        });

        return () => observer.disconnect();
    }, [categories]);

    // 点击时间线定位到指定分类
    const scrollToCategory = (categoryId: number) => {
        setActiveCategoryId(categoryId);
        const element = document.getElementById(`category-${categoryId}`);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    // 过滤勋章列表（搜索 + 状态过滤）
    const filteredCategories = useMemo(() => {
        return categories
            .map((cat) => {
                const matchedMedals = cat.medals.filter((medal) => {
                    if (statusFilter === 'unlocked' && !medal.isUnlocked) return false;
                    if (statusFilter === 'locked' && medal.isUnlocked) return false;

                    if (searchQuery.trim()) {
                        const query = searchQuery.toLowerCase();
                        return (
                            medal.title.toLowerCase().includes(query) ||
                            medal.description.toLowerCase().includes(query) ||
                            medal.conditionText.toLowerCase().includes(query)
                        );
                    }
                    return true;
                });

                return {
                    ...cat,
                    medals: matchedMedals,
                };
            })
            .filter((cat) => cat.medals.length > 0);
    }, [categories, searchQuery, statusFilter]);

    return (
        <>
            <Head title="全站成就勋章中心 - 探索社区荣誉" />

            <div className="min-h-screen bg-background/50 py-6 pb-24">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                    {/* 1. 顶部面包屑导航 */}
                    {/* <div className="flex items-center justify-between">
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    </div> */}

                    {/* 2. 顶部荣誉看板 Header */}
                    <div className="relative overflow-hidden rounded-3xl border border-border/70 bg-card p-6 sm:p-10 shadow-xs">
                        <div className="absolute top-0 right-0 -mt-8 -mr-8 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl pointer-events-none" />

                        <div className="relative flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                            <div className="space-y-2">
                                <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 text-xs font-semibold">
                                    <Sparkles className="w-3.5 h-3.5" /> 荣誉与成就殿堂
                                </div>
                                <h1 className="text-2xl sm:text-3xl lg:text-4xl font-black tracking-tight text-foreground">
                                    全站成就勋章中心
                                </h1>
                                <p className="text-xs sm:text-sm text-muted-foreground max-w-xl leading-relaxed">
                                    探索全站所有荣誉徽章与成长里程碑。通过日常活跃、知识分享、优质创作与社区互动，点亮专属成就。
                                </p>
                            </div>

                            {/* 登录用户的个人进度统计卡 */}
                            {stats.isLoggedIn ? (
                                <div className="w-full md:w-80 p-5 rounded-2xl bg-muted/40 border border-border/50 space-y-3 shrink-0">
                                    <div className="flex items-center justify-between text-xs sm:text-sm font-bold">
                                        <span className="text-foreground flex items-center gap-1.5">
                                            <Trophy className="w-4 h-4 text-amber-500" /> 我的收集度
                                        </span>
                                        <span className="text-amber-600 dark:text-amber-400 font-mono">
                                            {stats.unlockedCount} / {stats.totalCount} ({stats.completionRate}%)
                                        </span>
                                    </div>
                                    <Progress value={stats.completionRate} className="h-2 bg-muted" />
                                    <div className="flex justify-between items-center text-[11px] text-muted-foreground pt-1 border-t border-border/40">
                                        <span>累计成就点数</span>
                                        <span className="font-bold text-amber-500 font-mono">+{stats.totalTrophyPoints} 点</span>
                                    </div>
                                </div>
                            ) : (
                                <div className="w-full md:w-72 p-5 rounded-2xl bg-amber-500/5 border border-amber-500/20 text-center space-y-3 shrink-0">
                                    <Award className="w-8 h-8 text-amber-500 mx-auto" />
                                    <div className="space-y-1">
                                        <p className="text-xs font-bold text-foreground">登录点亮个人勋章</p>
                                        <p className="text-[11px] text-muted-foreground">登录后可追踪成就进度与点数</p>
                                    </div>
                                    <Button size="sm" asChild className="w-full rounded-full text-xs">
                                        <Link href="/login">立即登录</Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* 3. 搜索与状态过滤器 */}
                    <div className="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                        <div className="relative flex-1 max-w-md">
                            <Search className="w-4 h-4 text-muted-foreground absolute left-3.5 top-1/2 -translate-y-1/2" />
                            <Input
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="搜索勋章名称、获取条件描述..."
                                className="pl-9 h-10 rounded-2xl text-xs bg-card"
                            />
                        </div>

                        {stats.isLoggedIn && (
                            <div className="flex rounded-2xl border border-border/70 p-1 bg-muted/30 self-start sm:self-auto">
                                <button
                                    onClick={() => setStatusFilter('all')}
                                    className={cn("px-3.5 py-1.5 text-xs rounded-xl font-medium transition-colors", statusFilter === 'all' ? "bg-background text-foreground shadow-2xs font-bold" : "text-muted-foreground hover:text-foreground")}
                                >
                                    全部 ({stats.totalCount})
                                </button>
                                <button
                                    onClick={() => setStatusFilter('unlocked')}
                                    className={cn("px-3.5 py-1.5 text-xs rounded-xl font-medium transition-colors", statusFilter === 'unlocked' ? "bg-background text-emerald-600 dark:text-emerald-400 shadow-2xs font-bold" : "text-muted-foreground hover:text-foreground")}
                                >
                                    已点亮 ({stats.unlockedCount})
                                </button>
                                <button
                                    onClick={() => setStatusFilter('locked')}
                                    className={cn("px-3.5 py-1.5 text-xs rounded-xl font-medium transition-colors", statusFilter === 'locked' ? "bg-background text-foreground shadow-2xs font-bold" : "text-muted-foreground hover:text-foreground")}
                                >
                                    未解锁 ({stats.totalCount - stats.unlockedCount})
                                </button>
                            </div>
                        )}
                    </div>

                    {/* 4. 吸顶时间线分类快速导航条 */}
                    <div className="sticky top-4 z-30 p-2 rounded-2xl border border-border/70 bg-background/85 backdrop-blur-md shadow-md">
                        <div className="flex items-center gap-2 overflow-x-auto [scrollbar-width:none] [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden">
                            <span className="text-xs font-bold text-muted-foreground flex items-center gap-1.5 px-3 py-1 shrink-0 border-r border-border/60">
                                <Milestone className="w-4 h-4 text-amber-500" />
                                <span>分类导航</span>
                            </span>

                            {categories.map((category, index) => {
                                const total = category.medals.length;
                                const unlocked = category.medals.filter((m) => m.isUnlocked).length;
                                const isActive = activeCategoryId === category.id;

                                return (
                                    <button
                                        key={category.id}
                                        type="button"
                                        onClick={() => scrollToCategory(category.id)}
                                        className={cn(
                                            "group flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold transition-all duration-200 shrink-0 cursor-pointer",
                                            isActive
                                                ? "bg-amber-500 text-white shadow-sm ring-2 ring-amber-500/20"
                                                : "bg-muted/50 hover:bg-muted text-muted-foreground hover:text-foreground border border-transparent hover:border-border/60"
                                        )}
                                    >
                                        <span className={cn(
                                            "flex items-center justify-center size-4 rounded-full text-[10px] font-bold font-mono",
                                            isActive ? "bg-white/25 text-white" : "bg-muted-foreground/20 text-muted-foreground"
                                        )}>
                                            {index + 1}
                                        </span>

                                        <span>{category.name}</span>

                                        {stats.isLoggedIn && (
                                            <span className={cn(
                                                "text-[10px] font-mono px-1.5 py-0.2 rounded-full",
                                                isActive ? "bg-black/20 text-white" : "bg-background/80 text-muted-foreground border border-border/40"
                                            )}>
                                                {unlocked}/{total}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                    </div>

                    {/* 5. 时间线主干结构与勋章网格 */}
                    {filteredCategories.length > 0 ? (
                        <div className="relative border-l-2 border-border/70 ml-4 sm:ml-6 pl-6 sm:pl-10 space-y-12">
                            {filteredCategories.map((category, index) => {
                                const isActive = activeCategoryId === category.id;
                                const unlockedCount = category.medals.filter((m) => m.isUnlocked).length;

                                return (
                                    <div
                                        key={category.id}
                                        id={`category-${category.id}`}
                                        data-category-id={category.id}
                                        className="relative scroll-mt-24 space-y-6"
                                    >
                                        {/* 时间线节点编号 */}
                                        <div className={cn(
                                            "absolute -left-[31px] sm:-left-[47px] top-0 size-8 sm:size-9 rounded-full border-2 flex items-center justify-center transition-all duration-300 bg-background shadow-xs",
                                            isActive
                                                ? "border-amber-500 text-amber-500 shadow-[0_0_12px_rgba(245,158,11,0.35)] scale-110"
                                                : "border-border text-muted-foreground"
                                        )}>
                                            <span className="text-xs font-bold font-mono">{index + 1}</span>
                                        </div>

                                        {/* 分类标题与说明 */}
                                        <div className="border-b border-border/60 pb-3 flex flex-col sm:flex-row sm:items-baseline justify-between gap-2">
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <h2 className="text-lg font-bold text-foreground flex items-center gap-2">
                                                        <Sparkles className="w-4 h-4 text-amber-500" />
                                                        {category.name}
                                                    </h2>
                                                    {stats.isLoggedIn && (
                                                        <Badge variant="secondary" className="text-[10px] font-mono px-2 py-0.5">
                                                            已解锁 {unlockedCount} / {category.medals.length}
                                                        </Badge>
                                                    )}
                                                </div>
                                                {category.description && (
                                                    <p className="text-xs text-muted-foreground">{category.description}</p>
                                                )}
                                            </div>
                                        </div>

                                        {/* 勋章卡片网格 */}
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                            {category.medals.map((medal) => {
                                                const rarity = RARITY_MAP[medal.rarity] || RARITY_MAP.common;

                                                return (
                                                    <div
                                                        key={medal.id}
                                                        onClick={() => setSelectedMedal(medal)}
                                                        className={cn(
                                                            "group relative flex flex-col justify-between rounded-2xl border p-5 transition-all duration-200 bg-card cursor-pointer",
                                                            medal.isUnlocked
                                                                ? "border-border/70 hover:border-amber-500/40 hover:shadow-md hover:-translate-y-0.5"
                                                                : "border-border/40 bg-muted/10 opacity-75 hover:opacity-100"
                                                        )}
                                                    >
                                                        <div>
                                                            <div className="flex items-center justify-between mb-3">
                                                                <Badge variant="outline" className={cn("text-[10px] font-bold rounded-full", rarity.className)}>
                                                                    {rarity.label}
                                                                </Badge>
                                                                <span className="text-[10px] text-muted-foreground font-mono">
                                                                    +{medal.trophyPoints} 点数
                                                                </span>
                                                            </div>

                                                            <div className="flex items-start gap-3.5">
                                                                <div className="relative w-14 h-14 shrink-0 flex items-center justify-center">
                                                                    <img
                                                                        src={medal.iconUrl}
                                                                        alt={medal.title}
                                                                        className={cn(
                                                                            "w-full h-full object-contain transition-transform group-hover:scale-105",
                                                                            medal.isUnlocked
                                                                                ? "drop-shadow-sm group-hover:drop-shadow-[0_6px_12px_rgba(245,158,11,0.3)]"
                                                                                : "grayscale opacity-40 contrast-75"
                                                                        )}
                                                                    />
                                                                    {!medal.isUnlocked && (
                                                                        <div className="absolute inset-0 m-auto w-6 h-6 rounded-full bg-background/80 flex items-center justify-center text-muted-foreground shadow-xs">
                                                                            <Lock className="w-3 h-3" />
                                                                        </div>
                                                                    )}
                                                                </div>

                                                                <div className="flex-1 min-w-0 space-y-1">
                                                                    <h3 className={cn("text-sm font-bold truncate", medal.isUnlocked ? "text-foreground" : "text-muted-foreground")}>
                                                                        {medal.title}
                                                                    </h3>
                                                                    <p className="text-xs text-muted-foreground line-clamp-2 leading-snug">
                                                                        {medal.description}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {/* 卡片底栏 */}
                                                        <div className="mt-4 pt-3 border-t border-border/50 flex items-center justify-between text-[11px]">
                                                            {medal.isUnlocked ? (
                                                                <span className="flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400 truncate">
                                                                    <CheckCircle2 className="w-3.5 h-3.5 shrink-0" />
                                                                    <span>已于 {medal.unlockedAt} 解锁</span>
                                                                </span>
                                                            ) : (
                                                                <span className="text-muted-foreground truncate max-w-[190px]" title={medal.conditionText}>
                                                                    条件: {medal.conditionText}
                                                                </span>
                                                            )}

                                                            <span className="text-[10px] text-muted-foreground font-mono shrink-0">
                                                                全站 {medal.unlockedRate}%
                                                            </span>
                                                        </div>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        /* 空结果提示 */
                        <div className="py-16 text-center space-y-3 rounded-3xl border border-dashed border-border bg-card/40">
                            <Filter className="w-10 h-10 text-muted-foreground mx-auto stroke-[1.5]" />
                            <h3 className="text-base font-semibold text-foreground">未找到匹配的勋章</h3>
                            <p className="text-xs text-muted-foreground max-w-sm mx-auto">
                                没有找到与关键词“{searchQuery}”相符合的成就勋章，请调整搜索词或重置筛选。
                            </p>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    setSearchQuery('');
                                    setStatusFilter('all');
                                }}
                                className="rounded-full text-xs"
                            >
                                重置搜索条件
                            </Button>
                        </div>
                    )}

                </div>
            </div>

            {/* 6. 单个勋章详情弹窗 Dialog */}
            <Dialog open={!!selectedMedal} onOpenChange={(open) => !open && setSelectedMedal(null)}>
                <DialogContent className="max-w-md p-6 rounded-3xl bg-background/95 backdrop-blur-xl border-border/80 shadow-2xl">
                    {selectedMedal && (
                        <div className="space-y-6 text-center">
                            <DialogHeader className="space-y-1">
                                <div className="mx-auto w-24 h-24 p-2 relative flex items-center justify-center">
                                    <img
                                        src={selectedMedal.iconUrl}
                                        alt={selectedMedal.title}
                                        className={cn(
                                            "w-full h-full object-contain",
                                            selectedMedal.isUnlocked
                                                ? "drop-shadow-[0_8px_20px_rgba(245,158,11,0.35)]"
                                                : "grayscale opacity-40"
                                        )}
                                    />
                                </div>

                                <DialogTitle className="text-xl font-black text-foreground pt-2">
                                    {selectedMedal.title}
                                </DialogTitle>

                                <DialogDescription className="text-xs text-muted-foreground">
                                    {selectedMedal.description}
                                </DialogDescription>
                            </DialogHeader>

                            {/* 核心属性栏 */}
                            <div className="grid grid-cols-3 gap-2 p-3 rounded-2xl bg-muted/40 border border-border/50 text-xs">
                                <div>
                                    <span className="text-[10px] text-muted-foreground block">稀有度</span>
                                    <span className="font-bold text-foreground">
                                        {RARITY_MAP[selectedMedal.rarity]?.label || '普通'}
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-muted-foreground block">成就点数</span>
                                    <span className="font-bold text-amber-500 font-mono">
                                        +{selectedMedal.trophyPoints} 点
                                    </span>
                                </div>
                                <div>
                                    <span className="text-[10px] text-muted-foreground block">全站解锁</span>
                                    <span className="font-bold text-foreground font-mono">
                                        {selectedMedal.unlockedRate}%
                                    </span>
                                </div>
                            </div>

                            {/* 解锁状态与达成说明 */}
                            <div className="text-xs space-y-2 text-left p-4 rounded-2xl border bg-card">
                                <span className="text-muted-foreground block text-[11px] font-semibold">获取规则与途径</span>
                                <p className="text-foreground font-medium">{selectedMedal.conditionText}</p>

                                {selectedMedal.isUnlocked ? (
                                    <div className="pt-2 border-t border-border/40 flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400 font-semibold text-[11px]">
                                        <CheckCircle2 className="w-4 h-4" /> 您已于 {selectedMedal.unlockedAt} 点亮此成就
                                    </div>
                                ) : (
                                    <div className="pt-2 border-t border-border/40 flex items-center gap-1.5 text-muted-foreground text-[11px]">
                                        <Lock className="w-3.5 h-3.5" /> 尚未达成条件
                                    </div>
                                )}
                            </div>

                            <Button
                                variant="outline"
                                onClick={() => setSelectedMedal(null)}
                                className="w-full rounded-full text-xs"
                            >
                                关闭
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
