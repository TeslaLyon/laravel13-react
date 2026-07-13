// 文件位置: src/app/categories/page.tsx
import React, { useState, useEffect } from 'react';
import CategoryCard, { CategoryItem } from "@/components/category/Card";
import { Compass } from "lucide-react";

// 模拟数据：提取了你之前文件中提到的一些高质量分类
const fetchCategories = async (): Promise<CategoryItem[]> => {
    return new Promise((resolve) => setTimeout(() => resolve([
        {
            id: "c1",
            name: "前端开发",
            description: "探索最新的 React、Vue 及前沿 Web 技术。",
            coverImage: "https://images.pexels.com/photos/1181671/pexels-photo-1181671.jpeg?auto=compress&cs=tinysrgb&w=800",
            itemCount: "128"
        },
        {
            id: "c2",
            name: "科技数码",
            description: "最新的电子产品评测与极客桌面搭建指北。",
            coverImage: "https://images.pexels.com/photos/1779487/pexels-photo-1779487.jpeg?auto=compress&cs=tinysrgb&w=800",
            itemCount: "342"
        },
        {
            id: "c3",
            name: "生活 Vlog",
            description: "记录真实的生活碎片，感受世界的美好温度。",
            coverImage: "https://images.pexels.com/photos/347139/pexels-photo-347139.jpeg?auto=compress&cs=tinysrgb&w=800",
            itemCount: "85"
        },
        {
            id: "c4",
            name: "影视制作",
            description: "灯光、摄影、剪辑，全方位的影视幕后解析。",
            coverImage: "https://images.pexels.com/photos/2510428/pexels-photo-2510428.jpeg?auto=compress&cs=tinysrgb&w=800",
            itemCount: "64"
        },
        {
            id: "c5",
            name: "独立游戏",
            description: "发现小众佳作，支持独立游戏开发者的梦想。",
            coverImage: "https://images.pexels.com/photos/3165335/pexels-photo-3165335.jpeg?auto=compress&cs=tinysrgb&w=800",
            itemCount: "210"
        },
        {
            id: "c6",
            name: "开源项目",
            description: "汇集优秀的开源工具，共建更好的开发者生态。",
            coverImage: "https://images.pexels.com/photos/546819/pexels-photo-546819.jpeg?auto=compress&cs=tinysrgb&w=800",
            itemCount: "95"
        }
    ]), 600));
};

export default function CategoryListPage() {
    const [categories, setCategories] = useState<CategoryItem[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);

    useEffect(() => {
        const loadData = async () => {
            setIsLoading(true);
            try {
                const data = await fetchCategories();
                setCategories(data);
            } catch (error) {
                console.error("Failed to fetch:", error);
            } finally {
                setIsLoading(false);
            }
        };
        loadData();
    }, []);

    return (
        <div className="w-full p-4 md:p-8 bg-background min-h-screen">

            {/* 1. 页面头部区域 */}
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

            {/* 2. 核心网格区：分类卡片较宽，采用 1-4 列布局，gap-x-6 gap-y-8 */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-6 gap-y-8 max-w-[1600px] mx-auto">
                {isLoading
                    ? Array.from({ length: 8 }).map((_, index) => (
                        // 骨架屏：带有一点圆角的占位符
                        <div key={index} className="w-full aspect-[4/3] sm:aspect-video bg-muted animate-pulse rounded-2xl" />
                    ))
                    : categories.map((category) => (
                        <CategoryCard key={category.id} category={category} />
                    ))}
            </div>

        </div>
    );
}
