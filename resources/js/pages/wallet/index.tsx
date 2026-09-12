import React from 'react';
import { Head, Deferred } from '@inertiajs/react';
import { WalletData, PaginatedData, WalletTransactionItem } from '@/types/wallet';
import { WalletCardsSkeleton } from './partials/wallet-cards-skeleton';
import { WalletCards } from './partials/wallet-cards';
import { WalletTransactionsSkeleton } from './partials/wallet-transactions-skeleton';
import { WalletTransactionsTable } from './partials/wallet-transactions-table';

interface Props {
    wallet?: WalletData;                                 // 延迟加载资产属性
    transactions?: PaginatedData<WalletTransactionItem>; // 延迟加载流水属性
}

export default function WalletIndex({ wallet, transactions }: Props) {
    return (
        <div className="container mx-auto max-w-6xl py-8 px-4 space-y-6 pb-24">
            <Head title="资金钱包" />

            {/* 页面静态标头 */}
            <div className="flex flex-col gap-1">
                <h1 className="text-2xl font-bold tracking-tight text-foreground">我的资产钱包</h1>
                <p className="text-sm text-muted-foreground">
                    统一管理账户现金余额、社区虚拟金币、快速充值及全量财务流水。
                </p>
            </div>

            {/* 1. 资产卡片区：延迟加载并展示 1:1 骨架屏 */}
            <Deferred
                data="wallet"
                fallback={<WalletCardsSkeleton />}
            >
                <WalletCards wallet={wallet!} />
            </Deferred>

            {/* 2. 流水明细表格区：延迟加载并展示 10 行等高骨架屏 */}
            <Deferred
                data="transactions"
                fallback={<WalletTransactionsSkeleton />}
            >
                <WalletTransactionsTable transactions={transactions!} />
            </Deferred>
        </div>
    );
}
