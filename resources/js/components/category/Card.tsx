import React from "react";
import { ChevronRight } from "lucide-react";
import { getCardHoverColor } from "@/lib/utils";

// 1. 定义分类数据类型
export interface CategoryItem {
    id: number;
    name: string;
    description: string;
    coverImage: string;
    itemCount: string;
}



export default function CategoryCard({ category }: { category: CategoryItem }) {
    const hoverBgStyle = getCardHoverColor(category.id);

    return (
        // 🎯 外层容器
        <div className="group relative flex flex-col z-0 w-full mt-3 cursor-pointer">

            {/* 🎯 核心光晕层：为卡片提供向外发散的高光 */}
            <div className={`absolute -inset-3 rounded-[24px] border border-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 🎯 卡片主体：沉浸式图片背景 */}
            <div className="relative w-full aspect-[4/3] sm:aspect-video rounded-2xl overflow-hidden bg-muted shadow-sm">

                {/* 背景图片：悬停时缓慢放大，极其优雅 (duration-700 ease-out) */}
                <img
                    src={category.coverImage}
                    alt={category.name}
                    className="absolute inset-0 w-full h-full object-cover group-hover:scale-110 transition-transform duration-700 ease-out"
                />

                {/* 🎯 高级感的核心：暗色渐变遮罩 */}
                {/* 从底部的深黑渐变到顶部的透明，保证底部文字清晰 */}
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/40 to-black/5 group-hover:from-black/80 transition-colors duration-300"></div>

                {/* 顶部右上角的数量角标 */}
                <div className="absolute top-3 right-3 bg-white/20 backdrop-blur-md text-white text-[11px] px-2.5 py-1 rounded-full font-medium tracking-wide border border-white/10">
                    {category.itemCount} 内容
                </div>

                {/* 底部文本与交互区 */}
                <div className="absolute inset-0 p-4 sm:p-5 flex flex-col justify-end">
                    <div className="flex justify-between items-end">

                        {/* 标题与描述 */}
                        <div className="flex flex-col pr-4">
                            <h3 className="text-xl font-bold text-white mb-1.5 tracking-tight group-hover:text-white/90 transition-colors">
                                {category.name}
                            </h3>
                            <p className="text-sm text-white/70 line-clamp-1 font-light">
                                {category.description}
                            </p>
                        </div>

                        {/* 🎯 隐藏的指引箭头：悬停时向右滑入，增强点击欲望 */}
                        <div className="flex items-center justify-center w-8 h-8 rounded-full bg-white/20 backdrop-blur-md text-white opacity-0 -translate-x-4 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-300 shrink-0">
                            <ChevronRight className="w-4 h-4" />
                        </div>

                    </div>
                </div>

            </div>
        </div>
    );
}
