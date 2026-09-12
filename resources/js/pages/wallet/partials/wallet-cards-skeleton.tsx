import React from 'react';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';

export function WalletCardsSkeleton() {
    return (
        <div className="grid gap-5 md:grid-cols-3">
            {/* 1. 现金余额主卡骨架 */}
            <Card className="rounded-2xl border-border/70 bg-card/60 flex flex-col justify-between">
                <CardHeader className="flex flex-row items-center justify-between pb-3 pt-6 px-6">
                    <Skeleton className="h-5 w-24 rounded-md" />
                    <Skeleton className="h-8 w-8 rounded-full" />
                </CardHeader>
                <CardContent className="px-6 pb-6 space-y-5">
                    <div className="py-0.5">
                        <Skeleton className="h-10 w-44 rounded-lg" />
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Skeleton className="h-6 w-32 rounded-md" />
                        <Skeleton className="h-6 w-16 rounded-full" />
                    </div>
                    {/* 单个全宽按钮骨架 */}
                    <div className="pt-1">
                        <Skeleton className="h-10 w-full rounded-xl" />
                    </div>
                </CardContent>
            </Card>

            {/* 2. 社区金币资产卡骨架 */}
            <Card className="rounded-2xl border-border/70 bg-card/60 flex flex-col justify-between">
                <CardHeader className="flex flex-row items-center justify-between pb-3 pt-6 px-6">
                    <Skeleton className="h-5 w-24 rounded-md" />
                    <Skeleton className="h-8 w-8 rounded-full" />
                </CardHeader>
                <CardContent className="px-6 pb-6 space-y-5">
                    <div className="py-0.5">
                        <Skeleton className="h-10 w-40 rounded-lg" />
                    </div>
                    <div className="flex items-center gap-2.5">
                        <Skeleton className="h-6 w-28 rounded-md" />
                        <Skeleton className="h-4 w-28 rounded-md" />
                    </div>
                    <div className="pt-1">
                        <Skeleton className="h-10 w-full rounded-xl" />
                    </div>
                </CardContent>
            </Card>

            {/* 3. 统计汇总概览卡骨架 */}
            <Card className="rounded-2xl border-border/70 bg-card/60 flex flex-col justify-between">
                <CardHeader className="pb-3 pt-6 px-6">
                    <Skeleton className="h-5 w-24 rounded-md" />
                </CardHeader>
                <CardContent className="px-6 pb-6 space-y-3.5">
                    {/* 充值、消费共 2 行 */}
                    {Array.from({ length: 2 }).map((_, i) => (
                        <div key={i} className="flex items-center justify-between py-0.5">
                            <div className="flex items-center gap-2">
                                <Skeleton className="h-4 w-4 rounded-full" />
                                <Skeleton className="h-4 w-24 rounded-md" />
                            </div>
                            <Skeleton className="h-4 w-20 rounded-md" />
                        </div>
                    ))}
                    {/* 分割线下方的金币收益行 */}
                    <div className="border-t border-border/50 pt-3 mt-1 flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            <Skeleton className="h-4 w-4 rounded-full" />
                            <Skeleton className="h-4 w-24 rounded-md" />
                        </div>
                        <Skeleton className="h-4 w-24 rounded-md" />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
