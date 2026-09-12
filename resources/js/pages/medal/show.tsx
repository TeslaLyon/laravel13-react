import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import {
    Award,
    Sparkles,
    Lock,
    CheckCircle2,
    ArrowLeft,
    Shield,
    Check,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { cn } from '@/lib/utils';
import { toggleWear } from '@/actions/App/Http/Controllers/MedalController';

export interface MedalDetail {
    id: number;
    code: string;
    title: string;
    description: string;
    conditionText: string;
    iconUrl: string;
    rarity: 'common' | 'rare' | 'epic' | 'legendary';
    trophyPoints: number;
    isUnlocked: boolean;
    isWorn: boolean;
    wearSlot?: number | null;
    unlockedAt?: string | null;
    awardReason?: string | null;
}

export interface CategoryGroup {
    id: number;
    name: string;
    description?: string;
    medals: MedalDetail[];
}

interface Props {
    targetUser: {
        id: number;
        name: string;
        nickname: string;
        avatar: string;
        isSelf: boolean;
    };
    stats: {
        totalCount: number;
        unlockedCount: number;
        completionRate: number;
        totalTrophyPoints: number;
    };
    categories: CategoryGroup[];
}

const RARITY_MAP = {
    common: { label: '普通', className: 'bg-slate-500/10 text-slate-600 dark:text-slate-400 border-slate-500/20' },
    rare: { label: '稀有', className: 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20' },
    epic: { label: '史诗', className: 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20' },
    legendary: { label: '传说', className: 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20' },
};

export default function UserMedalsPage({ targetUser, stats, categories }: Props) {
    const [togglingId, setTogglingId] = useState<number | null>(null);

    // 扁平提取所有已佩戴的勋章
    const wornMedals = categories
        .flatMap((cat) => cat.medals)
        .filter((m) => m.isWorn)
        .sort((a, b) => (a.wearSlot || 0) - (b.wearSlot || 0));

    const handleToggleWear = (medalId: number) => {
        setTogglingId(medalId);
        router.post(
            toggleWear.url({ medal: medalId }),
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingId(null),
            }
        );
    };

    return (
        <>
            <Head title={`${targetUser.nickname} 的勋章荣誉馆`} />

            <div className="min-h-screen bg-background/50 py-6 pb-24">
                <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">

                    {/* 1. 顶部面包屑与快捷返回 */}
                    <div className="flex items-center justify-between">
                        
                        <Button variant="ghost" size="sm" asChild className="rounded-full gap-1 text-xs">
                            <Link href={`/@${targetUser.name}`}>
                                <ArrowLeft className="w-3.5 h-3.5" /> 返回个人主页
                            </Link>
                        </Button>
                    </div>

                    {/* 2. 荣誉总览卡片 */}
                    <div className="relative overflow-hidden rounded-3xl border border-border/70 bg-card p-6 sm:p-8 shadow-xs">
                        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-6">
                            <div className="flex items-center gap-4">
                                <Avatar className="w-16 h-16 sm:w-20 sm:h-20 border-2 border-background shadow-md">
                                    <AvatarImage src={targetUser.avatar} alt={targetUser.nickname} />
                                    <AvatarFallback className="text-xl font-bold">
                                        {targetUser.nickname.slice(0, 2).toUpperCase()}
                                    </AvatarFallback>
                                </Avatar>

                                <div className="space-y-1">
                                    <div className="flex items-center gap-2">
                                        <h1 className="text-xl sm:text-2xl font-black text-foreground">
                                            {targetUser.nickname} 的勋章成就馆
                                        </h1>
                                        {targetUser.isSelf && (
                                            <Badge variant="secondary" className="text-[10px] bg-primary/10 text-primary">
                                                我的空间
                                            </Badge>
                                        )}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        累计获得 <span className="font-bold text-amber-500">{stats.totalTrophyPoints}</span> 成就点数
                                    </p>
                                </div>
                            </div>

                            {/* 收集进度 */}
                            <div className="w-full sm:w-64 space-y-2 rounded-2xl bg-muted/40 p-4 border border-border/50">
                                <div className="flex justify-between text-xs font-bold">
                                    <span>勋章收集度</span>
                                    <span className="text-amber-500 font-mono">
                                        {stats.unlockedCount} / {stats.totalCount} ({stats.completionRate}%)
                                    </span>
                                </div>
                                <Progress value={stats.completionRate} className="h-2 bg-muted" />
                            </div>
                        </div>
                    </div>

                    {/* 3. 置顶佩戴展示槽 (最多 4 枚) */}
                    <div className="rounded-3xl border border-border/70 bg-card p-6 space-y-4 shadow-xs">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Shield className="w-4 h-4 text-amber-500" />
                                <h2 className="text-sm font-bold text-foreground">主页佩戴展示栏</h2>
                                <span className="text-xs text-muted-foreground">({wornMedals.length}/4)</span>
                            </div>
                            {targetUser.isSelf && (
                                <span className="text-[11px] text-muted-foreground">
                                    在下方成就列表中点击“佩戴 / 卸下”即可更换展示
                                </span>
                            )}
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                            {[0, 1, 2, 3].map((index) => {
                                const medal = wornMedals[index];

                                return (
                                    <div
                                        key={index}
                                        className={cn(
                                            "relative rounded-2xl border flex flex-col items-center justify-center p-4 min-h-[140px] text-center transition-all",
                                            medal
                                                ? "bg-amber-500/5 border-amber-500/30 shadow-2xs"
                                                : "bg-muted/10 border-dashed border-border/60"
                                        )}
                                    >
                                        {medal ? (
                                            <>
                                                <div className="w-14 h-14 p-1">
                                                    <img
                                                        src={medal.iconUrl}
                                                        alt={medal.title}
                                                        className="w-full h-full object-contain filter drop-shadow-md"
                                                    />
                                                </div>
                                                <span className="text-xs font-bold text-foreground mt-2 truncate max-w-full">
                                                    {medal.title}
                                                </span>
                                                <span className="text-[10px] text-muted-foreground font-mono">
                                                    +{medal.trophyPoints} 点
                                                </span>
                                            </>
                                        ) : (
                                            <div className="flex flex-col items-center gap-1 text-muted-foreground/50">
                                                <Award className="w-6 h-6 stroke-[1.5]" />
                                                <span className="text-[11px]">未佩戴</span>
                                            </div>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    {/* 4. 按分类陈列的全部勋章 */}
                    <div className="space-y-8">
                        {categories.map((category) => (
                            <div key={category.id} className="space-y-4">
                                <div className="border-b border-border/60 pb-2 flex items-baseline justify-between">
                                    <div>
                                        <h3 className="text-base font-bold text-foreground flex items-center gap-2">
                                            <Sparkles className="w-4 h-4 text-amber-500" />
                                            {category.name}
                                        </h3>
                                        {category.description && (
                                            <p className="text-xs text-muted-foreground mt-0.5">{category.description}</p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                    {category.medals.map((medal) => {
                                        const rarity = RARITY_MAP[medal.rarity] || RARITY_MAP.common;

                                        return (
                                            <div
                                                key={medal.id}
                                                className={cn(
                                                    "group relative flex flex-col justify-between rounded-2xl border p-5 transition-all duration-200 bg-card",
                                                    medal.isUnlocked
                                                        ? "border-border/70 hover:border-amber-500/40 hover:shadow-md"
                                                        : "border-border/40 bg-muted/10 opacity-75"
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
                                                            <h4 className={cn("text-sm font-bold truncate", medal.isUnlocked ? "text-foreground" : "text-muted-foreground")}>
                                                                {medal.title}
                                                            </h4>
                                                            <p className="text-xs text-muted-foreground line-clamp-2 leading-snug">
                                                                {medal.description}
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div className="mt-4 pt-3 border-t border-border/50 flex items-center justify-between text-[11px]">
                                                    {medal.isUnlocked ? (
                                                        <div className="flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400">
                                                            <CheckCircle2 className="w-3.5 h-3.5" />
                                                            <span>已于 {medal.unlockedAt} 解锁</span>
                                                        </div>
                                                    ) : (
                                                        <div className="text-muted-foreground truncate max-w-[170px]" title={medal.conditionText}>
                                                            途径: {medal.conditionText}
                                                        </div>
                                                    )}

                                                    {/* 本人佩戴/卸下按钮 */}
                                                    {targetUser.isSelf && medal.isUnlocked && (
                                                        <Button
                                                            size="sm"
                                                            variant={medal.isWorn ? "secondary" : "outline"}
                                                            disabled={togglingId === medal.id}
                                                            onClick={() => handleToggleWear(medal.id)}
                                                            className="h-7 px-2.5 rounded-full text-xs font-semibold"
                                                        >
                                                            {medal.isWorn ? (
                                                                <span className="flex items-center gap-1 text-amber-600 dark:text-amber-400">
                                                                    <Check className="w-3 h-3" /> 佩戴中
                                                                </span>
                                                            ) : (
                                                                '佩戴'
                                                            )}
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>

                </div>
            </div>
        </>
    );
}
