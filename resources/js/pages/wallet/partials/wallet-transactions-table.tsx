import React, { useState } from 'react';
import { PaginatedData, WalletTransactionItem } from '@/types/wallet';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { VideoPagination } from '@/components/VideoPagination';
import { cn } from '@/lib/utils';
import { Inbox, Coins, Wallet as WalletIcon, Copy, Check } from 'lucide-react';

interface Props {
    transactions: PaginatedData<WalletTransactionItem>;
}

/**
 * 🌟 表现层金额格式化：将后端传来的分安全转换为元展示
 * 纯前端处理，避免后端浮点精度漂移
 */
function formatCentsToYuan(cents: number | string | undefined | null): string {
    const numeric = Number(cents) || 0;
    return (numeric / 100).toFixed(2);
}

/**
 * 🌟 交易单号单元格组件：单行完整展示并提供点击复制
 */
function TrxNoCell({ trxNo, id }: { trxNo?: string | null; id: number }) {
    const [copied, setCopied] = useState(false);
    const displayText = trxNo || `#${id}`;

    const handleCopy = (e: React.MouseEvent) => {
        e.stopPropagation();
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(displayText);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div
            className="group inline-flex items-center gap-1.5 cursor-pointer py-0.5 select-text"
            title="点击复制完整交易单号"
            onClick={handleCopy}
        >
            <span className="font-mono text-xs text-muted-foreground group-hover:text-foreground transition-colors">
                {displayText}
            </span>
            <button
                type="button"
                className="opacity-0 group-hover:opacity-100 transition-opacity text-muted-foreground hover:text-foreground p-0.5 rounded"
                aria-label="复制交易单号"
            >
                {copied ? (
                    <Check className="w-3.5 h-3.5 text-emerald-500" />
                ) : (
                    <Copy className="w-3.5 h-3.5" />
                )}
            </button>
        </div>
    );
}

export function WalletTransactionsTable({ transactions }: Props) {
    return (
        <Card className="w-full rounded-2xl border-border/70 bg-card/60 shadow-xs">
            <CardHeader className="flex flex-col sm:flex-row sm:items-center justify-between pb-3 pt-6 px-6 gap-2">
                <div>
                    <CardTitle className="text-base sm:text-lg font-bold text-foreground">收支流水账单</CardTitle>
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
                {/* 🌟 方案 3 核心：外层水平滚动容器 + 内部 min-w 保障 */}
                <div className="overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-muted-foreground/20 hover:scrollbar-thumb-muted-foreground/40">
                    <Table className="min-w-[1080px] w-full">
                        <TableHeader>
                            <TableRow className="border-border/60">
                                <TableHead className="w-[210px] min-w-[210px] text-xs font-semibold whitespace-nowrap">
                                    交易单号
                                </TableHead>
                                <TableHead className="w-[95px] min-w-[95px] text-xs font-semibold whitespace-nowrap">
                                    资产类型
                                </TableHead>
                                <TableHead className="w-[105px] min-w-[105px] text-xs font-semibold whitespace-nowrap">
                                    业务类型
                                </TableHead>
                                <TableHead className="w-[125px] min-w-[125px] text-xs font-semibold whitespace-nowrap">
                                    变动额度
                                </TableHead>
                                <TableHead className="w-[125px] min-w-[125px] text-xs font-semibold whitespace-nowrap">
                                    变动后结余
                                </TableHead>
                                {/* 🌟 业务说明：给予充分的最小宽度，允许其横向自然铺开 */}
                                <TableHead className="min-w-[320px] text-xs font-semibold whitespace-nowrap">
                                    业务说明
                                </TableHead>
                                <TableHead className="w-[160px] min-w-[160px] text-right text-xs font-semibold whitespace-nowrap">
                                    发生时间
                                </TableHead>
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

                                    // 取绝对值进行显示
                                    const rawAmount = Math.abs(Number(tx.amount));
                                    const rawAfter = Number(tx.balance_after);

                                    return (
                                        <TableRow
                                            key={tx.id}
                                            className="h-[53px] border-border/40 hover:bg-muted/40 transition-colors"
                                        >
                                            {/* 交易单号：纯单行完整展示 */}
                                            <TableCell className="w-[210px] min-w-[210px] whitespace-nowrap">
                                                <TrxNoCell trxNo={tx.trx_no} id={tx.id} />
                                            </TableCell>

                                            {/* 资产类型 */}
                                            <TableCell className="w-[95px] min-w-[95px] whitespace-nowrap">
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
                                            <TableCell className="w-[105px] min-w-[105px] whitespace-nowrap">
                                                <Badge variant={isIncome ? 'default' : 'secondary'} className="text-xs px-2 py-0.5 font-medium rounded-md">
                                                    {tx.type_label}
                                                </Badge>
                                            </TableCell>

                                            {/* 变动金额 */}
                                            <TableCell className={cn(
                                                'w-[125px] min-w-[125px] font-mono font-bold text-sm tabular-nums whitespace-nowrap',
                                                isIncome ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'
                                            )}>
                                                {isIncome ? '+' : '-'}
                                                {isCoins
                                                    ? `${rawAmount} 币`
                                                    : `¥${formatCentsToYuan(rawAmount)}`}
                                            </TableCell>

                                            {/* 变动后结余 */}
                                            <TableCell className="w-[125px] min-w-[125px] font-mono text-sm font-semibold text-foreground tabular-nums whitespace-nowrap">
                                                {isCoins
                                                    ? `${rawAfter} 币`
                                                    : `¥${formatCentsToYuan(rawAfter)}`}
                                            </TableCell>

                                            {/* 🌟 业务描述：完全单行展现，不截断、不折行 */}
                                            <TableCell className="min-w-[320px] text-muted-foreground text-sm whitespace-nowrap">
                                                {tx.description || '-'}
                                            </TableCell>

                                            {/* 发生时间 */}
                                            <TableCell className="w-[160px] min-w-[160px] text-right text-xs font-mono text-muted-foreground whitespace-nowrap">
                                                {tx.created_at}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })
                            )}
                        </TableBody>
                    </Table>
                </div>

                {/* 分页组件：保持 Inertia 局部只重载 transactions 配置 */}
                <div className="pt-4 border-t border-border/40 flex justify-center">
                    <VideoPagination
                        links={transactions.links}
                        only={['transactions']}
                    />
                </div>
            </CardContent>
        </Card>
    );
}
