import React, { useState, useEffect } from 'react';
import { fetchVideos } from '@/api/api'; // 引入上面定义的模拟接口
import { VideoSkeleton } from "@/components/video/VideoSkeleton";
import { VideoCard } from "@/components/video/Card";
import { VideoPagination } from '@/components/VideoPagination';
import { Button } from "@/components/ui/button";
import { SlidersHorizontal } from "lucide-react";
import { Link } from '@inertiajs/react';
import { show } from '@/routes/videos';
import { Deferred } from "@inertiajs/react";
import { Video, PaginatedResponse } from "@/types/video";

const CATEGORIES = ["全部", "前端开发", "开源项目", "科技数码", "生活Vlog", "影视飓风", "大耳朵TV"];

export default function YoutubeVideoGrid({ videos }: { videos: PaginatedResponse<Video> }) {
    const [activeCategory, setActiveCategory] = useState("全部");


    return (
        <div className="w-full p-4 md:p-8 bg-background min-h-screen">

            {/* 🎯 1. 页面头部区域：标题 + 筛选操作 */}
            <div className="flex items-center justify-between mb-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-primary">推荐视频</h1>
                    <p className="text-sm text-muted-foreground mt-1">根据你的喜好实时更新</p>
                </div>

                {/* 一个精致的侧边筛选按钮（备用，提升专业感） */}
                <Button variant="outline" size="sm" className="gap-2 rounded-xl hidden sm:flex">
                    <SlidersHorizontal className="w-4 h-4" />
                    高级筛选
                </Button>
            </div>

            {/* 🎯 2. 胶囊标签滚动区（完美复刻 YouTube 顶盘） */}
            {/* no-scrollbar 确保横向滚动时隐藏丑陋的滚动条 */}
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

            {/* 🎯 3. 核心网格区：gap-x-6 (左右呼吸) gap-y-12 (上下断句) */}
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-8">

                <Deferred data="videos" fallback={
                    Array.from({ length: 10 }).map((_, index) => (
                        <VideoSkeleton key={index} />
                    ))
                }>
                    {videos?.data?.map((video) => (
                        <Link href={show({ video: video.id, slug: video.slug })} key={video.id}>
                            <VideoCard key={video.id} video={video} />
                        </Link>
                    ))}
                </Deferred>
            </div>

            {/* 🎯 4. 底部加载区域：使用带有呼吸感的大留白和幽灵按钮 */}
            <div className="flex flex-col items-center justify-center mt-16 mb-12 gap-2">
                <VideoPagination />
            </div>

        </div>
    );
}
