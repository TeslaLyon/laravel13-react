// 文件位置: src/app/showcase/page.tsx
import React from "react";
import ShowcaseCard, { ShowcaseItem } from "@/components/PictureCard";
import { VideoPagination } from '@/components/VideoPagination';

// 模拟数据：标签颜色全面适配 Dark Mode
const mockItems: ShowcaseItem[] = [
    {
        id: "1",
        title: "在东京街头寻找最佳的赛博朋克摄影机位，夜景实拍解析",
        imageUrl: "https://images.pexels.com/photos/2506923/pexels-photo-2506923.jpeg?auto=compress&cs=tinysrgb&w=800",
        views: "12.5万",
        tags: [
            { id: "t1", label: "摄影", colorClass: "bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400" },
            { id: "t2", label: "Vlog", colorClass: "bg-purple-100 text-purple-700 dark:bg-purple-500/10 dark:text-purple-400" }
        ]
    },
    {
        id: "2",
        title: "只需要 5 分钟的居家燃脂 HIIT，不需要任何器械！",
        imageUrl: "https://images.pexels.com/photos/4498542/pexels-photo-4498542.jpeg?auto=compress&cs=tinysrgb&w=800",
        views: "230万",
        tags: [
            { id: "t3", label: "健身", colorClass: "bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400" },
            { id: "t4", label: "热门", colorClass: "bg-red-100 text-red-700 dark:bg-red-500/10 dark:text-red-400" }
        ]
    },
    {
        id: "3",
        title: "如何用一台旧手机打造智能家居控制中心？超简单教程",
        imageUrl: "https://images.pexels.com/photos/4144222/pexels-photo-4144222.jpeg?auto=compress&cs=tinysrgb&w=800",
        views: "8.9万",
        tags: [
            { id: "t5", label: "科技", colorClass: "bg-slate-100 text-slate-700 dark:bg-slate-500/20 dark:text-slate-300" }
        ]
    },
    {
        id: "4",
        title: "探店隐藏在深巷里的百年拉面馆，醇厚猪骨汤底的秘密",
        imageUrl: "https://images.pexels.com/photos/3338681/pexels-photo-3338681.jpeg?auto=compress&cs=tinysrgb&w=800",
        views: "45万",
        tags: [
            { id: "t6", label: "美食", colorClass: "bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400" },
            { id: "t7", label: "探店", colorClass: "bg-amber-100 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400" }
        ]
    }
];

export default function ShowcaseListPage() {
    return (
        // 外层容器：浅色白底，深色采用近乎纯黑的 #0f0f0f (YouTube经典暗黑)
        <div className="max-w-[1600px] mx-auto p-4 sm:p-6 lg:p-8 bg-white dark:bg-[#0f0f0f] min-h-screen">

            {/* 页面标题：适配深色 */}
            <h1 className="text-2xl font-bold text-gray-900 dark:text-white mb-6 px-2">发现精选</h1>

            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-4 gap-x-3 gap-y-8">
                {mockItems.map((item) => (
                    <ShowcaseCard key={item.id} item={item} />
                ))}
            </div>
            <div className="flex flex-col items-center justify-center mt-16 mb-12 gap-2">
                <VideoPagination />
            </div>
        </div>
    );
}
