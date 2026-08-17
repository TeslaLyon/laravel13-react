import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';

// 引入 Shadcn 相关的组件和工具
import {
    Pagination as ShadcnPagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
} from "@/components/ui/pagination";
import { buttonVariants } from "@/components/ui/button";
import { cn } from "@/lib/utils";

interface PaginationProps {
    currentPage: number;
    lastPage: number;
    /**
     * 用于生成 Link 跳转链接的函数
     * 例如: (page) => `?page=${page}`
     */
    buildUrl: (page: number) => string;
}

export function Pagination({ currentPage, lastPage, buildUrl }: PaginationProps) {
    // 如果总页数小于等于1，则不显示分页器
    if (lastPage <= 1) return null;

    /**
     * 智能生成页码数组的逻辑
     * 规则：始终显示第1页，以及当前页的前后1页。不再强制显示最后一页。
     */
    const getPageNumbers = () => {
        const pages: number[] = [];
        for (let i = 1; i <= lastPage; i++) {
            if (
                i === 1 || // 始终保留首页
                (i >= currentPage - 1 && i <= currentPage + 1) // 当前页及紧邻的前后页
            ) {
                pages.push(i);
            }
        }

        // 插入省略号
        const withEllipsis: (number | string)[] = [];
        let prev = 0;
        for (const page of pages) {
            if (prev && page - prev > 1) {
                withEllipsis.push('...');
            }
            withEllipsis.push(page);
            prev = page;
        }

        return withEllipsis;
    };

    return (
        <ShadcnPagination className="mt-10 pb-6">
            <PaginationContent>
                {/* ================= 上一页 ================= */}
                <PaginationItem>
                    {currentPage > 1 ? (
                        <Link
                            href={buildUrl(currentPage - 1)}
                            preserveScroll
                            preserveState
                            className={cn(buttonVariants({ variant: "ghost", size: "default" }), "gap-1 pl-2.5")}
                        >
                            <ChevronLeft className="h-4 w-4" />
                            <span>上一页</span>
                        </Link>
                    ) : (
                        // 禁用状态下使用 span 替代 Link，并调整透明度
                        <span className={cn(buttonVariants({ variant: "ghost", size: "default" }), "gap-1 pl-2.5 opacity-50 pointer-events-none")}>
                            <ChevronLeft className="h-4 w-4" />
                            <span>上一页</span>
                        </span>
                    )}
                </PaginationItem>

                {/* ================= 动态页码 ================= */}
                {getPageNumbers().map((page, index) => (
                    <PaginationItem key={index}>
                        {page === '...' ? (
                            <PaginationEllipsis />
                        ) : (
                            <Link
                                href={buildUrl(page as number)}
                                preserveScroll
                                preserveState
                                aria-current={page === currentPage ? "page" : undefined}
                                className={cn(
                                    buttonVariants({
                                        // 当前页使用 outline 样式高亮，其余使用 ghost
                                        variant: page === currentPage ? "outline" : "ghost",
                                        size: "icon",
                                    })
                                )}
                            >
                                {page}
                            </Link>
                        )}
                    </PaginationItem>
                ))}

                {/* ================= 下一页 ================= */}
                <PaginationItem>
                    {currentPage < lastPage ? (
                        <Link
                            href={buildUrl(currentPage + 1)}
                            preserveScroll
                            preserveState
                            className={cn(buttonVariants({ variant: "ghost", size: "default" }), "gap-1 pr-2.5")}
                        >
                            <span>下一页</span>
                            <ChevronRight className="h-4 w-4" />
                        </Link>
                    ) : (
                        <span className={cn(buttonVariants({ variant: "ghost", size: "default" }), "gap-1 pr-2.5 opacity-50 pointer-events-none")}>
                            <span>下一页</span>
                            <ChevronRight className="h-4 w-4" />
                        </span>
                    )}
                </PaginationItem>
            </PaginationContent>
        </ShadcnPagination>
    );
}
