import React, { useState, useEffect } from 'react';
import { fetchVideos } from '@/api/api'; // 引入上面定义的模拟接口
import { VideoSkeleton } from "@/components/video/VideoSkeleton";
import { Video } from "@/types/video";
import { VideoCard } from "@/components/video/Card";
import { VideoPagination } from '@/components/VideoPagination';
import { Button } from "@/components/ui/button";
import { SlidersHorizontal } from "lucide-react";

const CATEGORIES = ["全部", "前端开发", "开源项目", "科技数码", "生活Vlog", "影视飓风", "大耳朵TV"];

export default function YoutubeVideoGrid() {
    const [videos, setVideos] = useState<Video[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true); // boolean 通常会自动推断，加上也可以
    const [activeCategory, setActiveCategory] = useState("全部");

    useEffect(() => {
        // 定义获取数据的异步函数
        const loadVideos = async () => {
            setIsLoading(true);
            try {
                const data = await fetchVideos(); // 真实项目中这里换成 fetch('/api/videos') 或 axios
                setVideos(data);
            } catch (error) {
                console.error("Failed to fetch videos:", error);
            } finally {
                setIsLoading(false);
            }
        };

        loadVideos();
    }, []); // 空依赖数组表示组件挂载时执行一次

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
                {isLoading
                    ? Array.from({ length: 10 }).map((_, index) => (
                        <VideoSkeleton key={index} />
                    ))
                    : videos.map((video) => (
                        <VideoCard key={video.id} video={video} />
                    ))}
            </div>

            {/* 🎯 4. 底部加载区域：使用带有呼吸感的大留白和幽灵按钮 */}
            {!isLoading && (
                <div className="flex flex-col items-center justify-center mt-16 mb-12 gap-2">
                    <VideoPagination />
                </div>
            )}

        </div>
    );
}
