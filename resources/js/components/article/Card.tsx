import React from "react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Clock, CalendarDays } from "lucide-react";

// 1. 定义文章数据类型
export interface ArticleItem {
    id: string;
    title: string;
    excerpt: string;
    coverImage: string;
    category: string;
    authorName: string;
    authorAvatar: string;
    date: string;
    readTime: string;
}

// 2. 引入彩色光晕池 (保持全局交互的一致性)
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

export default function ArticleCard({ article }: { article: ArticleItem }) {
    const colorIndex = String(article.id).charCodeAt(0) % hoverColorPool.length;
    const hoverBgStyle = hoverColorPool[colorIndex];

    return (
        // 🎯 外层容器：响应式布局 (手机 flex-col, 桌面 flex-row)
        <div className="group relative flex flex-col md:flex-row gap-5 cursor-pointer z-0 w-full mt-3 items-start p-1">

            {/* 🎯 核心光晕层：向外扩张的绝对定位背景框 */}
            <div className={`absolute -inset-4 rounded-2xl border border-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 左侧封面图：桌面端固定宽度，采用更显沉稳的 4:3 比例 (或者 16:10) */}
            <div className="relative w-full md:w-65 lg:w-75 shrink-0 aspect-video md:aspect-video rounded-xl overflow-hidden bg-muted">
                <img
                    src={article.coverImage}
                    alt={article.title}
                    className="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500 ease-out"
                />
                {/* 悬浮在图片左上角的精致分类标签 */}
                <span className="absolute top-3 left-3 bg-black/60 backdrop-blur-md text-white text-[11px] px-2.5 py-1 rounded-full font-medium tracking-wide">
                    {article.category}
                </span>
            </div>

            {/* 右侧内容区：标题、摘要、作者信息 */}
            <div className="flex flex-col flex-1 min-w-0 justify-between h-full py-1">

                <div>
                    {/* 🎯 标题交互：悬停时文字颜色变深/亮，并且有极其轻微的向右位移，体现高级感 */}
                    <h3 className="text-lg md:text-xl font-bold leading-snug text-foreground group-hover:text-primary transition-all duration-300 group-hover:translate-x-1 line-clamp-2 mb-2">
                        {article.title}
                    </h3>

                    {/* 文章摘要：限制两行 */}
                    <p className="text-sm text-muted-foreground leading-relaxed line-clamp-2 md:line-clamp-3 mb-4">
                        {article.excerpt}
                    </p>
                </div>

                {/* 底部元数据：作者、日期、阅读时长 */}
                <div className="flex items-center gap-3 mt-auto pt-2">
                    {/* 作者头像 */}
                    <Avatar className="h-8 w-8 border border-border">
                        <AvatarImage src={article.authorAvatar} alt={article.authorName} />
                        <AvatarFallback>{article.authorName.charAt(0)}</AvatarFallback>
                    </Avatar>

                    <div className="flex flex-col sm:flex-row sm:items-center text-xs text-muted-foreground gap-1 sm:gap-3">
                        <span className="font-medium text-foreground">{article.authorName}</span>

                        {/* 隐藏在极小屏幕下的分隔符 */}
                        <span className="hidden sm:inline text-muted-foreground/50">•</span>

                        <div className="flex items-center gap-3">
                            <span className="flex items-center gap-1.5">
                                <CalendarDays className="w-3.5 h-3.5" />
                                {article.date}
                            </span>
                            <span className="flex items-center gap-1.5">
                                <Clock className="w-3.5 h-3.5" />
                                {article.readTime}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    );
}
