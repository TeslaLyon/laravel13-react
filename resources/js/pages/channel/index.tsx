import React, { useState, useEffect } from 'react';
import StudioCard, { StudioItem } from "@/components/channel/Card";
import { Button } from "@/components/ui/button";
import { SlidersHorizontal } from "lucide-react";
import { Deferred, Link } from "@inertiajs/react";
import { Channel, PaginatedResponse } from "@/types/channel";
import { show } from '@/actions/App/Http/Controllers/ChannelController';

// 顶部分类胶囊标签
const CATEGORIES = ["全部片商", "北美区", "欧洲区", "亚洲区", "独立制作", "VR专区"];

// 模拟数据：使用正方形特征的占位图
export default function StudioListPage({ channels }: { channels: PaginatedResponse<Channel> }) {
    // const [studios, setStudios] = useState<StudioItem[]>([]);
    // const [isLoading, setIsLoading] = useState<boolean>(true);
    const [activeCategory, setActiveCategory] = useState("全部片商");

    return (
        <div className="w-full p-4 md:p-8 bg-background min-h-screen">
            {/* 1. 页面头部区域 */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-foreground">片商</h1>
                    <p className="text-sm text-muted-foreground mt-1">按发行商浏览最新内容</p>
                </div>
                <Button variant="outline" size="sm" className="gap-2 rounded-xl hidden sm:flex">
                    <SlidersHorizontal className="w-4 h-4" />
                    片商筛选
                </Button>
            </div>

            {/* 2. 胶囊标签滚动区 */}
            <div className="flex items-center gap-2 overflow-x-auto pb-4 mb-6 scrollbar-none snap-x">
                {CATEGORIES.map((category) => (
                    <button
                        key={category}
                        onClick={() => setActiveCategory(category)}
                        className={`px-4 py-1.5 rounded-xl text-sm font-medium transition-all shrink-0 snap-center
              ${activeCategory === category
                                ? 'bg-primary text-primary-foreground shadow-sm'
                                : 'bg-muted hover:bg-muted/80 text-muted-foreground hover:text-primary'
                            }`}
                    >
                        {category}
                    </button>
                ))}
            </div>

            {/* 3. 核心网格区：正方形卡片较宽，采用 2-5 列布局，gap-x-6 留出高亮光晕的空间 */}
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-x-4 gap-y-8">
                <Deferred data="channels" fallback={
                    Array.from({ length: 10 }).map((_, index) => (
                        <div key={index} className="w-full aspect-square bg-muted animate-pulse rounded-2xl" />
                    ))
                }>
                    {channels?.data?.map((channel) => (
                        <Link href={show({ channel: channel.id, slug: channel.slug })} key={channel.id}>
                            <StudioCard key={channel.id} studio={channel} />
                        </Link>
                    ))}
                </Deferred>
            </div>

            {/* 4. 底部加载区域 */}
            {/* {!isLoading && (
                <div className="flex flex-col items-center justify-center mt-16 mb-12 gap-2">
                    <Button variant="ghost" className="text-muted-foreground hover:bg-muted rounded-full px-8">
                        加载更多
                    </Button>
                </div>
            )} */}
        </div>
    );
}
