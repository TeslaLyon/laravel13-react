import React from 'react';
import { Skeleton } from '@/components/ui/skeleton';

export function ProductCardSkeleton() {
    return (
        <div className="flex flex-col gap-1 z-0">
            {/* 封面占位 (严格 16:9 比例) */}
            <div className="relative w-full aspect-video rounded-xl overflow-hidden bg-muted border border-border/50">
                <Skeleton className="w-full h-full" />
            </div>

            {/* 信息区域占位 */}
            <div className="flex gap-3 px-1 mt-2">
                {/* 头像占位 */}
                <Skeleton className="h-9 w-9 rounded-full shrink-0 mt-0.5" />

                <div className="flex flex-col flex-1 min-w-0 space-y-2">
                    {/* 两行标题占位 */}
                    <div className="space-y-1 pt-1">
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-3/4" />
                    </div>

                    {/* 标签组占位 */}
                    <div className="flex gap-1.5 pt-0.5">
                        <Skeleton className="h-4 w-12 rounded" />
                        <Skeleton className="h-4 w-14 rounded" />
                    </div>

                    {/* 作者与观看数据占位 */}
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-3 w-32" />

                    {/* 价格与购买按钮占位 */}
                    <div className="flex items-center justify-between pt-2.5 border-t border-border/40">
                        <div className="space-y-1">
                            <Skeleton className="h-5 w-16" />
                        </div>
                        <Skeleton className="h-8 w-8 rounded-full" />
                    </div>
                </div>
            </div>
        </div>
    );
}

export function StoreGridSkeleton({ count = 8 }: { count?: number }) {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-6">
            {Array.from({ length: count }).map((_, i) => (
                <ProductCardSkeleton key={i} />
            ))}
        </div>
    );
}
