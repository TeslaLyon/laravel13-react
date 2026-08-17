import React from "react";
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Clock, CalendarDays } from "lucide-react";
import { Link } from '@inertiajs/react';
import ArticleController from "@/actions/App/Http/Controllers/ArticleController";

// 从统一类型定义文件中导入 ArticleItem 接口
import { ArticleItem } from "@/types/article";
import { getCardHoverColor } from "@/lib/utils";

// 彩色光晕池 (保持全局交互的一致性)
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
    const hoverBgStyle = getCardHoverColor(article.id);

    // 提取主要分类名称 (多对多取第 1 个主分类，若无则显示未分类)
    const primaryCategory = article.categories && article.categories.length > 0
        ? article.categories[0].name
        : "未分类";

    // 安全获取作者信息与发布日期
    const authorName = article.author?.name || "匿名作者";
    const authorAvatar = article.author?.avatar || "";
    const publishDate = article.published_at || article.date || "";

    return (
        <div className="group relative flex flex-col md:flex-row gap-5 z-0 w-full mt-3 items-start p-1">

            {/* 🎯 核心光晕层：向外扩张的绝对定位背景框 */}
            <div className={`absolute -inset-4 rounded-2xl border border-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            {/* 左侧封面图 */}
            <Link href={ArticleController.show.url({ article: article.id, slug: article.slug })}>
                <div className="relative w-full md:w-65 lg:w-75 shrink-0 aspect-video md:aspect-video rounded-xl overflow-hidden bg-muted">
                    <img
                        src={article.cover_image}
                        alt={article.title}
                        className="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-500 ease-out"
                    />
                    {/* 悬浮分类标签：展示文章的首个主分类 */}
                    <span className="absolute top-3 left-3 bg-black/60 backdrop-blur-md text-white text-[11px] px-2.5 py-1 rounded-full font-medium tracking-wide">
                        {primaryCategory}
                    </span>
                </div>
            </Link>

            {/* 右侧内容区：标题、摘要、作者信息 */}
            <div className="flex flex-col flex-1 min-w-0 justify-between h-full py-1">

                <div>
                    <Link href={ArticleController.show.url({ article: article.id, slug: article.slug })}>
                        <h3 className="text-lg md:text-xl font-bold leading-snug text-foreground group-hover:text-primary transition-all duration-300 group-hover:translate-x-1 line-clamp-2 mb-2">
                            {article.title}
                        </h3>
                    </Link>

                    {/* 文章摘要 */}
                    <p className="text-sm text-muted-foreground leading-relaxed line-clamp-2 md:line-clamp-3 mb-4">
                        {article.excerpt}
                    </p>
                </div>

                {/* 底部元数据：作者、日期、阅读时长 */}
                <div className="flex items-center gap-3 mt-auto pt-2">
                    {/* 作者头像 */}
                    <Avatar className="h-8 w-8 border border-border">
                        <AvatarImage src={authorAvatar} alt={authorName} />
                        <AvatarFallback>{authorName.charAt(0)}</AvatarFallback>
                    </Avatar>

                    <div className="flex flex-col sm:flex-row sm:items-center text-xs text-muted-foreground gap-1 sm:gap-3">
                        <span className="font-medium text-foreground">{authorName}</span>

                        {/* 分隔符 */}
                        <span className="hidden sm:inline text-muted-foreground/50">•</span>

                        <div className="flex items-center gap-3">
                            <span className="flex items-center gap-1.5">
                                <CalendarDays className="w-3.5 h-3.5" />
                                {publishDate}
                            </span>
                            <span className="flex items-center gap-1.5">
                                <Clock className="w-3.5 h-3.5" />
                                {article.read_time}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    );
}
