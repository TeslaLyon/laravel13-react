import React from "react";
import { Link } from "@inertiajs/react";
import { ChevronLeft, ChevronRight } from "lucide-react";

// 定义单个分页链接数据结构
export interface PaginationLink {
    url: string | null;
    label: string;
    page?: number | null;
    active: boolean;
}

interface VideoPaginationProps {
    links?: PaginationLink[];
    /** 是否隐藏末尾几页的页码按钮，默认为 true */
    hideEndPages?: boolean;
    /** 在当前页之后最多显示的页码数量，默认为 2 */
    maxPagesAfterCurrent?: number;
}

export function VideoPagination({
    links,
    hideEndPages = true,
    maxPagesAfterCurrent = 2
}: VideoPaginationProps) {
    // 🎯 1. 边界防御：无数据或只有 1 页时直接隐藏
    if (!links || links.length <= 3) {
        return null;
    }

    // 获取当前激活的页码数字
    const activeLink = links.find(l => l.active);
    const currentPage = activeLink ? parseInt(activeLink.label, 10) || 1 : 1;

    // 🎯 2. 对 links 数组进行过滤处理
    const processedLinks = links.filter((link, index) => {
        const isFirst = index === 0; // 上一页
        const isLast = index === links.length - 1; // 下一页

        // 上一页和下一页按钮必须保留
        if (isFirst || isLast) {
            return true;
        }

        // 如果关闭了“隐藏末尾页码”功能，展示全部
        if (!hideEndPages) {
            return true;
        }

        // 解析当前页码项的数字
        const pageNum = parseInt(link.label, 10);

        // 如果是数字页码
        if (!isNaN(pageNum)) {
            // 过滤条件：只保留“小于等于当前页”的页码，以及“当前页往后延伸 maxPagesAfterCurrent 页”以内的页码
            // 比如当前第 2 页，maxPagesAfterCurrent=2，则保留 1, 2, 3, 4，大于 4 的（即最后几页）全部隐藏
            return pageNum <= currentPage + maxPagesAfterCurrent;
        }

        // 对于省略号 "..."：如果是位于当前页之后的省略号，直接隐藏
        if (link.label === "...") {
            // 前面的省略号可以保留，后面的省略号过滤掉
            const isAfterCurrent = index > links.findIndex(l => l.active);
            return !isAfterCurrent;
        }

        return true;
    });

    return (
        <div className="flex items-center justify-center gap-2">
            {processedLinks.map((link, index) => {
                const isFirst = index === 0;
                const isLast = index === processedLinks.length - 1;
                const isEllipsis = link.label === "...";

                // 🎯 3. 渲染“上一页”按钮
                if (isFirst) {
                    return link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className="flex items-center gap-1 px-3 py-2 text-[15px] font-medium rounded-xl hover:bg-muted transition-colors text-primary"
                        >
                            <ChevronLeft className="w-4 h-4" />
                            <span>Previous</span>
                        </Link>
                    ) : (
                        <span
                            key={index}
                            className="flex items-center gap-1 px-3 py-2 text-[15px] font-medium rounded-xl text-muted-foreground/40 cursor-not-allowed select-none"
                        >
                            <ChevronLeft className="w-4 h-4" />
                            <span>Previous</span>
                        </span>
                    );
                }

                // 🎯 4. 渲染“下一页”按钮
                if (isLast) {
                    return link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            preserveScroll
                            preserveState
                            className="flex items-center gap-1 px-3 py-2 text-[15px] font-medium rounded-xl hover:bg-muted transition-colors text-primary"
                        >
                            <span>Next</span>
                            <ChevronRight className="w-4 h-4" />
                        </Link>
                    ) : (
                        <span
                            key={index}
                            className="flex items-center gap-1 px-3 py-2 text-[15px] font-medium rounded-xl text-muted-foreground/40 cursor-not-allowed select-none"
                        >
                            <span>Next</span>
                            <ChevronRight className="w-4 h-4" />
                        </span>
                    );
                }

                // 🎯 5. 渲染省略号 (...)
                if (isEllipsis) {
                    return (
                        <div
                            key={index}
                            className="w-10 h-10 flex items-center justify-center text-[15px] font-medium text-primary tracking-widest select-none"
                        >
                            ...
                        </div>
                    );
                }

                // 🎯 6. 渲染当前激活页码
                if (link.active) {
                    return (
                        <span
                            key={index}
                            className="w-10 h-10 flex items-center justify-center text-[15px] font-medium rounded-xl border border-slate-200 dark:border-slate-800 transition-colors text-primary select-none shadow-xs"
                        >
                            {link.label}
                        </span>
                    );
                }

                // 🎯 7. 渲染普通页码
                return link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        preserveState
                        className="w-10 h-10 flex items-center justify-center text-[15px] font-medium rounded-xl hover:bg-muted transition-colors text-primary"
                    >
                        {link.label}
                    </Link>
                ) : null;
            })}
        </div>
    );
}
