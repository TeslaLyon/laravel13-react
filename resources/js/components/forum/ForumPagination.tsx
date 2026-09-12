import React, { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    MoreHorizontal,
} from 'lucide-react';
import { buttonVariants } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { PaginatedData } from '@/types/forum';

interface ForumPaginationProps {
    pagination: PaginatedData<any>;
    baseUrl?: string;   // 可选：指定基础路径 (如 /forum/threads/31/my-slug)
    className?: string;
    showStats?: boolean;
}

type PageItem = number | 'dots-left' | 'dots-right';

export default function ForumPagination({
    pagination,
    baseUrl,
    className = '',
    showStats = false,
}: ForumPaginationProps) {
    const { current_page, last_page, total } = pagination;

    // 🎯 1. 智能计算展示的页码数字与省略号
    const pageNumbers = useMemo<PageItem[]>(() => {
        if (last_page <= 1) return [];

        const totalNumbers = 7;
        if (last_page <= totalNumbers) {
            return Array.from({ length: last_page }, (_, i) => i + 1);
        }

        const siblingCount = 1;
        const leftSiblingIndex = Math.max(current_page - siblingCount, 1);
        const rightSiblingIndex = Math.min(current_page + siblingCount, last_page);

        const shouldShowLeftDots = leftSiblingIndex > 2;
        const shouldShowRightDots = rightSiblingIndex < last_page - 1;

        if (!shouldShowLeftDots && shouldShowRightDots) {
            const leftItemCount = 3 + 2 * siblingCount;
            const leftRange = Array.from({ length: leftItemCount }, (_, i) => i + 1);
            return [...leftRange, 'dots-right', last_page];
        }

        if (shouldShowLeftDots && !shouldShowRightDots) {
            const rightItemCount = 3 + 2 * siblingCount;
            const rightRange = Array.from(
                { length: rightItemCount },
                (_, i) => last_page - rightItemCount + i + 1
            );
            return [1, 'dots-left', ...rightRange];
        }

        if (shouldShowLeftDots && shouldShowRightDots) {
            const middleRange = Array.from(
                { length: rightSiblingIndex - leftSiblingIndex + 1 },
                (_, i) => leftSiblingIndex + i
            );
            return [1, 'dots-left', ...middleRange, 'dots-right', last_page];
        }

        return [];
    }, [current_page, last_page]);

    // 🎯 2. 缓存纯净路径、保留参数与 Hash 锚点
    const { cleanBasePath, searchString, hashString } = useMemo(() => {
        let rawUrl = baseUrl;

        if (!rawUrl && typeof window !== 'undefined') {
            rawUrl = window.location.pathname + window.location.search + window.location.hash;
        }

        if (!rawUrl) {
            return { cleanBasePath: '/', searchString: '', hashString: '' };
        }

        try {
            const parsed = new URL(rawUrl, 'http://localhost');

            let pathname = parsed.pathname
                .replace(/\/+$/, '')
                .replace(/\/page-\d+$/, '');

            if (!pathname) {
                pathname = '/';
            }

            parsed.searchParams.delete('page');
            const preservedQuery = parsed.searchParams.toString();
            const search = preservedQuery ? `?${preservedQuery}` : '';
            const hash = parsed.hash || '';

            return {
                cleanBasePath: pathname,
                searchString: search,
                hashString: hash,
            };
        } catch {
            const fallbackPath = rawUrl.split('?')[0].split('#')[0].replace(/\/+$/, '').replace(/\/page-\d+$/, '') || '/';
            return { cleanBasePath: fallbackPath, searchString: '', hashString: '' };
        }
    }, [baseUrl]);

    // 🎯 3. 生成目标 URL
    const getPageUrl = (targetPage: number): string => {
        let targetPath: string;

        if (targetPage <= 1) {
            targetPath = cleanBasePath;
        } else {
            targetPath = cleanBasePath === '/' ? `/page-${targetPage}` : `${cleanBasePath}/page-${targetPage}`;
        }

        return `${targetPath}${searchString}${hashString}`;
    };

    // 总页数 <= 1 自动隐藏
    if (last_page <= 1) {
        return null;
    }

    return (
        <div className={cn('flex flex-col sm:flex-row sm:items-center justify-between gap-4 select-none', className)}>
            {/* 左侧：轻量统计 (可选) */}
            {showStats && (
                <div className="text-xs text-muted-foreground">
                    第 <span className="font-semibold text-foreground">{current_page}</span> / {last_page} 页，共{' '}
                    <span className="font-semibold text-foreground">{total}</span> 楼
                </div>
            )}

            {/* 右侧：Shadcn UI 风格分页导航 */}
            <nav
                role="navigation"
                aria-label="pagination"
                className="flex items-center ml-auto"
            >
                <ul className="flex flex-row items-center gap-1">
                    {/* 首页直达 */}
                    {last_page > 4 && (
                        <li>
                            <Link
                                href={getPageUrl(1)}
                                preserveScroll
                                aria-label="跳转到第一页"
                                className={cn(
                                    buttonVariants({ variant: 'ghost', size: 'icon' }),
                                    'h-8 w-8',
                                    current_page === 1 && 'pointer-events-none opacity-40'
                                )}
                            >
                                <ChevronsLeft className="h-4 w-4" />
                            </Link>
                        </li>
                    )}

                    {/* 上一页 */}
                    <li>
                        <Link
                            href={getPageUrl(Math.max(current_page - 1, 1))}
                            preserveScroll
                            aria-label="上一页"
                            className={cn(
                                buttonVariants({ variant: 'ghost', size: 'sm' }),
                                'h-8 gap-1 pl-2.5 pr-3 text-xs',
                                current_page === 1 && 'pointer-events-none opacity-40'
                            )}
                        >
                            <ChevronLeft className="h-4 w-4" />
                            <span className="hidden sm:inline">上一页</span>
                        </Link>
                    </li>

                    {/* 页码与省略号 */}
                    {pageNumbers.map((page, index) => {
                        if (page === 'dots-left' || page === 'dots-right') {
                            return (
                                <li key={`dots-${index}`}>
                                    <span
                                        aria-hidden="true"
                                        className="flex h-8 w-8 items-center justify-center text-muted-foreground"
                                    >
                                        <MoreHorizontal className="h-4 w-4" />
                                    </span>
                                </li>
                            );
                        }

                        const isActive = page === current_page;

                        return (
                            <li key={`page-${page}`}>
                                <Link
                                    href={getPageUrl(page)}
                                    preserveScroll
                                    aria-current={isActive ? 'page' : undefined}
                                    className={cn(
                                        buttonVariants({
                                            variant: isActive ? 'outline' : 'ghost',
                                            size: 'icon',
                                        }),
                                        'h-8 w-8 text-xs font-medium',
                                        isActive && 'border-primary/50 text-foreground font-semibold shadow-2xs pointer-events-none'
                                    )}
                                >
                                    {page}
                                </Link>
                            </li>
                        );
                    })}

                    {/* 下一页 */}
                    <li>
                        <Link
                            href={getPageUrl(Math.min(current_page + 1, last_page))}
                            preserveScroll
                            aria-label="下一页"
                            className={cn(
                                buttonVariants({ variant: 'ghost', size: 'sm' }),
                                'h-8 gap-1 pl-3 pr-2.5 text-xs',
                                current_page === last_page && 'pointer-events-none opacity-40'
                            )}
                        >
                            <span className="hidden sm:inline">下一页</span>
                            <ChevronRight className="h-4 w-4" />
                        </Link>
                    </li>

                    {/* 末页直达 */}
                    {last_page > 4 && (
                        <li>
                            <Link
                                href={getPageUrl(last_page)}
                                preserveScroll
                                aria-label="跳转到最后一页"
                                className={cn(
                                    buttonVariants({ variant: 'ghost', size: 'icon' }),
                                    'h-8 w-8',
                                    current_page === last_page && 'pointer-events-none opacity-40'
                                )}
                            >
                                <ChevronsRight className="h-4 w-4" />
                            </Link>
                        </li>
                    )}
                </ul>
            </nav>
        </div>
    );
}
