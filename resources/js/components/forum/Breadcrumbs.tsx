import React from 'react';
import { Link } from '@inertiajs/react';
import { ChevronRight, Home } from 'lucide-react';
import { BreadcrumbItem } from '@/types/forum';

interface BreadcrumbsProps {
    breadcrumbs?: BreadcrumbItem[];
    className?: string;
}

export default function Breadcrumbs({ breadcrumbs = [], className = '' }: BreadcrumbsProps) {
    if (!breadcrumbs || breadcrumbs.length === 0) {
        return null;
    }

    return (
        <nav aria-label="Breadcrumb" className={`flex items-center text-xs md:text-sm text-muted-foreground ${className}`}>
            <ol className="flex items-center gap-1.5 flex-wrap">
                {breadcrumbs.map((item, index) => {
                    const isLast = index === breadcrumbs.length - 1;
                    const isHome = index === 0 && item.title === '首页';
                    const isClickable = Boolean(item.href) && !isLast;

                    return (
                        <li key={`${item.title}-${index}`} className="inline-flex items-center gap-1.5 min-w-0">
                            {/* 分隔箭头 */}
                            {index > 0 && (
                                <ChevronRight
                                    className="w-3.5 h-3.5 text-muted-foreground/50 shrink-0 select-none"
                                    aria-hidden="true"
                                />
                            )}

                            {/* 可点击链接 */}
                            {isClickable ? (
                                <Link
                                    href={item.href!}
                                    className="inline-flex items-center gap-1 font-medium hover:text-foreground hover:underline hover:underline-offset-2 transition-colors truncate max-w-[150px] md:max-w-[240px]"
                                    title={item.title}
                                >
                                    {isHome && <Home className="w-3.5 h-3.5 shrink-0 mb-0.5" />}
                                    <span className="truncate">{item.title}</span>
                                </Link>
                            ) : (
                                /* 当前页/不可点击项 */
                                <span
                                    className={`inline-flex items-center gap-1 truncate max-w-[180px] md:max-w-[300px] ${
                                        isLast
                                            ? 'font-semibold text-foreground'
                                            : 'font-medium text-muted-foreground'
                                    }`}
                                    aria-current={isLast ? 'page' : undefined}
                                    title={item.title}
                                >
                                    {isHome && <Home className="w-3.5 h-3.5 shrink-0 mb-0.5" />}
                                    <span className="truncate">{item.title}</span>
                                </span>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
