import React from "react";
import { MoreVertical } from "lucide-react";
import { Button } from "@/components/ui/button";

export interface Tag {
    id: string;
    label: string;
    colorClass: string;
}

export interface ShowcaseItem {
    id: string;
    title: string;
    imageUrl: string;
    views: string;
    tags: Tag[];
}

// 颜色池复用
const hoverColorPool = [
    "bg-red-500/10 dark:bg-red-500/20",
    "bg-blue-500/10 dark:bg-blue-500/20",
    "bg-green-500/10 dark:bg-green-500/20",
    "bg-yellow-500/10 dark:bg-yellow-500/20",
    "bg-purple-500/10 dark:bg-purple-500/20",
    "bg-pink-500/10 dark:bg-pink-500/20",
    "bg-indigo-500/10 dark:bg-indigo-500/20",
    "bg-orange-500/10 dark:bg-orange-500/20",
];

export default function ShowcaseCard({ item }: { item: ShowcaseItem }) {
    const colorIndex = String(item.id).charCodeAt(0) % hoverColorPool.length;
    const hoverBgStyle = hoverColorPool[colorIndex];

    return (
        // 采用你参考文件的外层逻辑结构
        <div className="group relative flex flex-col gap-1 cursor-pointer z-0 w-full mt-3">

            {/* 🎯 核心层：向外扩张的绝对定位背景框 */}
            <div className={`absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 顶部图片区域 */}
            <div className="relative w-full aspect-[9/16] rounded-xl overflow-hidden bg-muted">
                <img
                    src={item.imageUrl}
                    alt={item.title}
                    className="w-full h-full object-cover group-hover:scale-[1.02] transition-transform duration-300"
                />
            </div>

            {/* 底部信息区域：左右布局 */}
            <div className="flex gap-2 px-1 mt-1">

                {/* 左侧内容区：加上 flex-1 min-w-0 防止文字截断失效 */}
                <div className="flex flex-col flex-1 min-w-0">

                    {/* 第一行：标题与按钮的并排容器 */}
                    <div className="flex items-start justify-between gap-2">
                        <h3 className="text-[15px] font-semibold leading-tight line-clamp-2 text-foreground group-hover:text-primary transition-colors pt-1">
                            {item.title}
                        </h3>

                        {/* 菜单按钮容器：复刻你文件里的边距精细微调 */}
                        <div className="shrink-0 -mt-1 -mr-2 transition-opacity p-1 group-hover:bg-muted-foreground/10 rounded-full">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="text-muted-foreground hover:bg-transparent rounded-full shadow-none h-7 w-7"
                                onClick={(e) => {
                                    e.stopPropagation();
                                }}
                            >
                                <MoreVertical className="w-4 h-4" />
                            </Button>
                        </div>
                    </div>

                    {/* 彩色小标签区 */}
                    <div className="flex flex-wrap gap-1.5 mt-2 mb-1.5">
                        {item.tags.map((tag) => (
                            <span
                                key={tag.id}
                                className={`text-[10px] sm:text-[11px] px-2 py-0.5 rounded-md font-medium ${tag.colorClass}`}
                            >
                                {tag.label}
                            </span>
                        ))}
                    </div>

                    {/* 浏览量数据 */}
                    <p className="text-xs text-muted-foreground truncate">
                        {item.views} 次观看
                    </p>
                </div>
            </div>
        </div>
    );
}
