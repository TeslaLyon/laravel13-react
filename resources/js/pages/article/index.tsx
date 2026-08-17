import React, { useState } from 'react';
import { usePage, Deferred, Link } from '@inertiajs/react';
import ArticleCard from "@/components/article/Card";
import { Button } from "@/components/ui/button";
import { BookOpenText } from "lucide-react";

// 从统一类型文件导入接口
import { PaginatedArticles, BreadcrumbItem } from "@/types/article";

// Inertia 页面传递的 Props 接口
interface PageProps {
    breadcrumbs?: BreadcrumbItem[];
    articles?: PaginatedArticles;
    [key: string]: any;
}

const CATEGORIES = ["推荐", "深度解析", "幕后故事", "器材评测", "行业动态", "创作者访谈"];

// 列表数据加载中的骨架屏占位组件
function ArticleSkeleton() {
    return (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-x-3 gap-y-12 mx-auto w-full">
            {Array.from({ length: 4 }).map((_, index) => (
                <div key={index} className="flex flex-col md:flex-row gap-5 w-full">
                    <div className="w-full md:w-[260px] lg:w-[300px] aspect-video md:aspect-[4/3] bg-muted animate-pulse rounded-xl" />
                    <div className="flex flex-col flex-1 gap-3 py-2">
                        <div className="w-3/4 h-6 bg-muted animate-pulse rounded-md" />
                        <div className="w-full h-16 bg-muted animate-pulse rounded-md mt-2" />
                        <div className="w-1/2 h-4 bg-muted animate-pulse rounded-md mt-auto" />
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function ArticleListPage() {
    const { articles } = usePage<PageProps>().props;
    const [activeCategory, setActiveCategory] = useState("推荐");

    return (
        <div className="w-full p-4 md:p-8 bg-background min-h-screen">
            {/* 1. 页面头部 */}
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-3">
                        <BookOpenText className="w-7 h-7 text-primary" />
                        阅读专区
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1.5">沉下心来，探索深度优质内容</p>
                </div>
            </div>

            {/* 2. 胶囊标签筛选区 */}
            <div className="flex items-center gap-2 overflow-x-auto pb-4 mb-8 scrollbar-none snap-x">
                {CATEGORIES.map((category) => (
                    <button
                        key={category}
                        onClick={() => setActiveCategory(category)}
                        className={`px-4 py-1.5 rounded-full text-sm font-medium transition-all shrink-0 snap-center ${activeCategory === category
                                ? 'bg-primary text-primary-foreground shadow-md'
                                : 'bg-muted hover:bg-muted/80 text-muted-foreground hover:text-primary'
                            }`}
                    >
                        {category}
                    </button>
                ))}
            </div>

            {/* 3. 核心文章列表：使用 Inertia Deferred 处理异步渲染 */}
            <Deferred data="articles" fallback={<ArticleSkeleton />}>
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-x-3 gap-y-12 mx-auto">
                    {articles?.data?.map((article) => (
                        <ArticleCard key={article.id} article={article} />
                    ))}
                </div>

                {/* 4. 分页按钮 */}
                {articles?.next_page_url && (
                    <div className="flex flex-col items-center justify-center mt-20 mb-12 gap-2">
                        <Link href={articles.next_page_url} preserveScroll>
                            <Button variant="outline" className="text-foreground hover:bg-muted rounded-full px-10 h-10 border-border">
                                浏览更多文章
                            </Button>
                        </Link>
                    </div>
                )}
            </Deferred>
        </div>
    );
}
