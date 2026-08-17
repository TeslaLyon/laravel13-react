import React from 'react';
import { usePage, Deferred, Link } from '@inertiajs/react';
import CategoryCard from "@/components/category/Card";
import { Compass, ChevronLeft, ChevronRight } from "lucide-react";
import { PaginatedCategories, BreadcrumbItem } from "@/types/category";
import { Button } from "@/components/ui/button";
import { VideoPagination } from '@/components/VideoPagination';

interface PageProps {
    breadcrumbs?: BreadcrumbItem[];
    channels?: PaginatedCategories;
    [key: string]: any;
}

/**
 * 🎯 1:1 精密骨架屏组件
 */
function CategorySkeleton() {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-8 max-w-[1600px] mx-auto w-full">
            {Array.from({ length: 8 }).map((_, index) => (
                <div key={index} className="w-full mt-3">
                    <div className="w-full aspect-[4/3] sm:aspect-video bg-muted animate-pulse rounded-2xl" />
                </div>
            ))}
        </div>
    );
}

export default function CategoryListPage() {
    // 从 Inertia 页面属性中读取 channels (现为 PaginatedCategories 分页对象)
    const { channels } = usePage<PageProps>().props;

    return (
        <div className="w-full p-4 md:p-8 bg-background min-h-screen">

            {/* 1. 页面头部 */}
            <div className="flex flex-col items-center text-center mb-10 mt-4">
                <div className="inline-flex items-center justify-center p-3 bg-muted rounded-full mb-4">
                    <Compass className="w-6 h-6 text-primary" />
                </div>
                <h1 className="text-3xl font-bold tracking-tight text-foreground mb-2">
                    探索频道
                </h1>
                <p className="text-sm text-muted-foreground max-w-md mx-auto">
                    找到属于你的兴趣圈子，发现更多优质内容与创作者。
                </p>
            </div>

            {/* 2. 核心网格与分页处理 */}
            <Deferred data="channels" fallback={<CategorySkeleton />}>
                {/* 频道卡片列表 */}
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-3 gap-y-6 max-w-[1600px] mx-auto">
                    {channels?.data?.map((category) => (
                        <CategoryCard key={category.id} category={category} />
                    ))}
                </div>

                <div className="flex flex-col items-center justify-center mt-10 mb-12 gap-2">
                    <VideoPagination links={channels?.links} />
                </div>
            </Deferred>

        </div>
    );
}
