import React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Skeleton } from '@/components/ui/skeleton';

export function WalletTransactionsSkeleton() {
    const skeletonRows = Array.from({ length: 10 });

    return (
        <Card className="w-full rounded-2xl border-border/70 bg-card/60 shadow-xs">
            <CardHeader className="pb-3 pt-6 px-6">
                <CardTitle className="text-base sm:text-lg font-bold text-foreground">收支流水账单</CardTitle>
                <CardDescription className="text-sm text-muted-foreground">
                    展示最近账户发生的充值、消费扣款、签到奖励及社区互动变动明细。
                </CardDescription>
            </CardHeader>
            <CardContent className="px-6 pb-6 space-y-4">
                <Table>
                    <TableHeader>
                        <TableRow className="border-border/60">
                            <TableHead className="w-[180px] text-xs font-semibold">交易单号</TableHead>
                            <TableHead className="w-[100px] text-xs font-semibold">资产类型</TableHead>
                            <TableHead className="w-[110px] text-xs font-semibold">业务类型</TableHead>
                            <TableHead className="w-[140px] text-xs font-semibold">变动额度</TableHead>
                            <TableHead className="w-[140px] text-xs font-semibold">变动后结余</TableHead>
                            <TableHead className="text-xs font-semibold">业务说明</TableHead>
                            <TableHead className="w-[170px] text-right text-xs font-semibold">发生时间</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {skeletonRows.map((_, index) => (
                            <TableRow key={index} className="h-[53px] border-border/40">
                                <TableCell>
                                    <Skeleton className="h-4 w-28 rounded-md" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-5 w-14 rounded-md" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-5 w-16 rounded-full" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-4 w-20 rounded-md" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-4 w-20 rounded-md" />
                                </TableCell>
                                <TableCell>
                                    <Skeleton className="h-4 w-3/4 max-w-[260px] rounded-md" />
                                </TableCell>
                                <TableCell className="text-right flex justify-end items-center h-[53px]">
                                    <Skeleton className="h-4 w-28 rounded-md" />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>

                {/* 分页条骨架 */}
                <div className="flex justify-center pt-4 border-t border-border/40">
                    <div className="flex items-center gap-2">
                        <Skeleton className="h-10 w-24 rounded-xl" />
                        <Skeleton className="h-10 w-10 rounded-xl" />
                        <Skeleton className="h-10 w-10 rounded-xl" />
                        <Skeleton className="h-10 w-24 rounded-xl" />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
