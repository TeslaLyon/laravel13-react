import React from 'react';
import { PaginatedData, WalletTransactionItem } from '@/types/wallet';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { VideoPagination } from '@/components/VideoPagination';
import { cn } from '@/lib/utils';
import { Inbox, Coins, Wallet as WalletIcon } from 'lucide-react';

interface Props {
    transactions: PaginatedData<WalletTransactionItem>;
}

export function WalletTransactionsTable({ transactions }: Props) {
    return (
        <Card className="w-full rounded-2xl border-border/70 bg-card/60 shadow-xs">
            <CardHeader className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 pt-6 px-6 gap-2">
                <div>
                    <CardTitle className="text-base sm:text-lg font-bold text-foreground">收支流水账单</CardTitle>
                    {/* 🌟 文案调整：明确聚焦于充值、消费及社区互动 */}
                    <CardDescription className="text-sm text-muted-foreground mt-1">
                        展示最近账户发生的充值、消费扣款、签到奖励及社区互动变动明细。
                    </CardDescription>
                </div>
                {transactions.total > 0 && (
                    <span className="text-xs font-mono text-muted-foreground bg-muted/50 px-2.5 py-1 rounded-md">
                        共 <strong className="text-foreground">{transactions.total}</strong> 条记录 (第 {transactions.current_page} / {transactions.last_page} 页)
                    </span>
                )}
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
                        {transactions.data.length === 0 ? (
                            <TableRow>
                                <TableCell colSpan={7} className="h-36 text-center text-muted-foreground space-y-2">
                                    <Inbox className="w-9 h-9 text-muted-foreground/40 mx-auto" />
                                    <p className="text-sm">暂无资金或金币变动明细记录</p>
                                </TableCell>
                            </TableRow>
                        ) : (
                            transactions.data.map((tx) => {
                                const isIncome = tx.direction === 1 || Number(tx.amount) > 0;
                                const isCoins = tx.currency_type === 'coins';
                                const amountNum = Math.abs(Number(tx.amount));
                                const afterNum = Number(tx.balance_after);

                                return (
                                    <TableRow key={tx.id} className="h-[53px] border-border/40 hover:bg-muted/40 transition-colors">
                                        {/* 交易单号 */}
                                        <TableCell className="font-mono text-xs text-muted-foreground truncate max-w-[180px]">
                                            {tx.trx_no || `#${tx.id}`}
                                        </TableCell>

                                        {/* 资产类型 */}
                                        <TableCell>
                                            {isCoins ? (
                                                <Badge variant="outline" className="bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20 text-xs px-2 py-0.5 gap-1 font-medium rounded-md">
                                                    <Coins className="w-3 h-3" /> 金币
                                                </Badge>
                                            ) : (
                                                <Badge variant="outline" className="bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-500/20 text-xs px-2 py-0.5 gap-1 font-medium rounded-md">
                                                    <WalletIcon className="w-3 h-3" /> 现金
                                                </Badge>
                                            )}
                                        </TableCell>

                                        {/* 业务类型 */}
                                        <TableCell>
                                            <Badge variant={isIncome ? 'default' : 'secondary'} className="text-xs px-2 py-0.5 font-medium rounded-md">
                                                {tx.type_label}
                                            </Badge>
                                        </TableCell>

                                        {/* 变动金额：Apple/Google 规范，采用 tabular-nums 避免对齐错位 */}
                                        <TableCell className={cn(
                                            'font-mono font-bold text-sm tabular-nums',
                                            isIncome ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'
                                        )}>
                                            {isIncome ? '+' : '-'}
                                            {isCoins
                                                ? `${amountNum} 币`
                                                : `¥${amountNum.toFixed(2)}`}
                                        </TableCell>

                                        {/* 变动后结余 */}
                                        <TableCell className="font-mono text-sm font-semibold text-foreground tabular-nums">
                                            {isCoins
                                                ? `${afterNum} 币`
                                                : `¥${afterNum.toFixed(2)}`}
                                        </TableCell>

                                        {/* 业务描述 */}
                                        <TableCell className="text-muted-foreground text-sm truncate max-w-[280px]" title={tx.description}>
                                            {tx.description}
                                        </TableCell>

                                        {/* 发生时间 */}
                                        <TableCell className="text-right text-xs font-mono text-muted-foreground">
                                            {tx.created_at}
                                        </TableCell>
                                    </TableRow>
                                );
                            })
                        )}
                    </TableBody>
                </Table>

                {/* 分页组件集成 */}
                <div className="pt-4 border-t border-border/40 flex justify-center">
                    <VideoPagination links={transactions.links} />
                </div>
            </CardContent>
        </Card>
    );
}
