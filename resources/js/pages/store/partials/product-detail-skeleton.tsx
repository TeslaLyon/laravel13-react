import React from 'react';
import { Skeleton } from '@/components/ui/skeleton';

export function ProductDetailSkeleton() {
    return (
        <div className="space-y-10 animate-in fade-in duration-300">
            {/* 1. 顶部预览区骨架 (严格匹配 16:9 / 21:9 比例与圆角) */}
            <div className="relative w-full aspect-video md:aspect-[21/9] max-h-[560px] rounded-3xl overflow-hidden bg-muted border border-border/40">
                <Skeleton className="w-full h-full" />
            </div>

            {/* 2. 主体两栏栅格骨架 */}
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-10">

                {/* 左侧主要信息区 (占比 8/12) */}
                <div className="lg:col-span-8 space-y-8">
                    {/* 标签与标题占位 */}
                    <div className="space-y-3">
                        <div className="flex gap-2">
                            <Skeleton className="h-6 w-16 rounded-md" />
                            <Skeleton className="h-6 w-20 rounded-md" />
                        </div>
                        <Skeleton className="h-9 md:h-11 w-4/5" />
                        <Skeleton className="h-9 md:h-11 w-3/5" />
                    </div>

                    {/* 作者栏骨架 */}
                    <div className="flex items-center justify-between p-4 rounded-2xl bg-muted/30 border border-border/50">
                        <div className="flex items-center gap-3.5">
                            <Skeleton className="h-12 w-12 rounded-full shrink-0" />
                            <div className="space-y-2">
                                <div className="flex items-center gap-2">
                                    <Skeleton className="h-4 w-24" />
                                    <Skeleton className="h-4 w-16 rounded" />
                                </div>
                                <Skeleton className="h-3 w-48" />
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <Skeleton className="h-8 w-20 rounded-full" />
                            <Skeleton className="h-8 w-20 rounded-full" />
                        </div>
                    </div>

                    {/* 规格参数矩阵骨架 */}
                    <div className="rounded-2xl border border-border bg-card p-6 space-y-4 shadow-xs">
                        <Skeleton className="h-4 w-32" />
                        <div className="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-1">
                            {Array.from({ length: 3 }).map((_, i) => (
                                <div key={i} className="rounded-xl bg-muted/40 p-3.5 border border-border/40 space-y-2">
                                    <Skeleton className="h-3 w-16" />
                                    <Skeleton className="h-4 w-24" />
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* 详情介绍长文本骨架 */}
                    <div className="rounded-2xl border border-border bg-card p-6 md:p-8 space-y-4">
                        <div className="flex items-center gap-4 pb-4 border-b border-border/60">
                            <Skeleton className="h-4 w-20" />
                            <Skeleton className="h-4 w-24" />
                            <Skeleton className="h-4 w-16" />
                        </div>
                        <div className="space-y-3 pt-2">
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-full" />
                            <Skeleton className="h-4 w-5/6" />
                            <Skeleton className="h-4 w-2/3" />
                        </div>
                    </div>
                </div>

                {/* 右侧 Sticky 购买面板骨架 (占比 4/12) */}
                <div className="lg:col-span-4">
                    <div className="sticky top-8 rounded-3xl border border-border bg-card shadow-xl p-6 md:p-8 space-y-6">
                        {/* 价格占位 */}
                        <div className="space-y-2">
                            <Skeleton className="h-3 w-16" />
                            <Skeleton className="h-10 w-36" />
                        </div>

                        {/* 按钮占位 */}
                        <div className="space-y-3">
                            <Skeleton className="h-12 w-full rounded-xl" />
                            <Skeleton className="h-12 w-full rounded-xl" />
                        </div>

                        {/* 包含权益列表占位 */}
                        <div className="pt-6 border-t border-border/60 space-y-3">
                            <Skeleton className="h-4 w-28" />
                            <div className="space-y-2.5">
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-4 w-4/5" />
                                <Skeleton className="h-4 w-3/4" />
                            </div>
                        </div>

                        {/* 安全提示占位 */}
                        <div className="rounded-xl bg-muted/40 p-3.5 border border-border/40">
                            <Skeleton className="h-8 w-full" />
                        </div>
                    </div>
                </div>

            </div>
        </div>
    );
}
