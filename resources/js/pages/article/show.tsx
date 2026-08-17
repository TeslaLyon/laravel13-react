import React, { useState } from 'react';
import { usePage, Link, Deferred } from '@inertiajs/react';
import ArticleCard from "@/components/article/Card";
import { Button } from "@/components/ui/button";
import {
    ArrowLeft,
    Clock,
    Calendar,
    Share2,
    Bookmark,
    Check,
    Twitter,
    Linkedin,
    Link as LinkIcon
} from "lucide-react";
import { ArticleDetailData } from "@/types/article";

interface PageProps {
    article?: ArticleDetailData;
    [key: string]: any;
}

/**
 * 🎯 精密 1:1 尺寸骨架屏：高度、宽度、边距与真实 DOM 节点绝对一致，消除切换抖动
 */
function ArticleShowSkeleton() {
    return (
        <div className="w-full bg-background min-h-screen pb-20 animate-pulse">
            {/* 1. 顶部吸顶导航栏骨架 */}
            <div className="sticky top-0 z-10 backdrop-blur-md bg-background/80 border-b border-border/40 px-4 md:px-8 py-3">
                <div className="max-w-5xl mx-auto flex items-center justify-between">
                    <div className="w-28 h-8 bg-muted rounded-md" />
                    <div className="flex items-center gap-2">
                        <div className="w-9 h-9 bg-muted rounded-full" />
                        <div className="w-9 h-9 bg-muted rounded-full" />
                    </div>
                </div>
            </div>

            {/* 2. 文章主体容器 (与真实文章主体 max-w-3xl, px-4 md:px-6 完全对齐) */}
            <article className="max-w-3xl mx-auto px-4 md:px-6 pt-8 md:pt-12">
                {/* 分类胶囊标签骨架 */}
                <div className="mb-4 flex gap-2">
                    <div className="w-20 h-6 bg-muted rounded-full" />
                </div>

                {/* 标题骨架 (模拟 2 行主标题高度) */}
                <div className="space-y-3 mb-4">
                    <div className="w-full h-9 md:h-12 bg-muted rounded-lg" />
                    <div className="w-3/4 h-9 md:h-12 bg-muted rounded-lg" />
                </div>

                {/* 摘要骨架 (模拟 2 行摘要高度) */}
                <div className="space-y-2 mb-8">
                    <div className="w-full h-5 md:h-6 bg-muted rounded" />
                    <div className="w-4/5 h-5 md:h-6 bg-muted rounded" />
                </div>

                {/* 作者与发布信息骨架 */}
                <div className="flex items-center justify-between py-4 border-y border-border/60 mb-8">
                    <div className="flex items-center gap-3">
                        <div className="w-11 h-11 rounded-full bg-muted shrink-0" />
                        <div className="space-y-2">
                            <div className="w-28 h-4 bg-muted rounded" />
                            <div className="w-36 h-3 bg-muted rounded" />
                        </div>
                    </div>
                    <div className="hidden sm:flex items-center gap-1">
                        <div className="w-8 h-8 bg-muted rounded-md" />
                        <div className="w-8 h-8 bg-muted rounded-md" />
                        <div className="w-8 h-8 bg-muted rounded-md" />
                    </div>
                </div>

                {/* 封面图骨架 (与真实图最高 500px 及 aspect-video 完全匹配) */}
                <div className="w-full aspect-video max-h-[500px] bg-muted rounded-2xl mb-10" />

                {/* 正文模拟骨架 (模拟段落与标题排版) */}
                <div className="space-y-6 mb-12">
                    <div className="w-full h-4 bg-muted rounded" />
                    <div className="w-11/12 h-4 bg-muted rounded" />
                    <div className="w-4/5 h-4 bg-muted rounded" />

                    {/* 小标题骨架 */}
                    <div className="w-1/2 h-7 bg-muted rounded mt-8 mb-4" />
                    <div className="w-full h-4 bg-muted rounded" />
                    <div className="w-full h-4 bg-muted rounded" />
                    <div className="w-2/3 h-4 bg-muted rounded" />

                    {/* 引用块骨架 */}
                    <div className="w-full h-20 bg-muted/60 border-l-4 border-muted rounded-r-lg my-6" />
                </div>

                {/* 底部标签区骨架 */}
                <div className="flex items-center gap-2 pt-6 border-t border-border">
                    <div className="w-16 h-4 bg-muted rounded" />
                    <div className="w-16 h-6 bg-muted rounded-md" />
                    <div className="w-20 h-6 bg-muted rounded-md" />
                    <div className="w-14 h-6 bg-muted rounded-md" />
                </div>
            </article>

            {/* 3. 延伸阅读骨架 */}
            <section className="max-w-5xl mx-auto px-4 md:px-8 mt-20 pt-12 border-t border-border">
                <div className="w-32 h-8 bg-muted rounded mb-8" />
                <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    {/* 复刻 Card 组件结构 */}
                    {[1, 2].map((i) => (
                        <div key={i} className="flex flex-col md:flex-row gap-5 p-1 w-full">
                            <div className="w-full md:w-65 lg:w-75 aspect-video bg-muted rounded-xl shrink-0" />
                            <div className="flex flex-col flex-1 gap-3 py-1">
                                <div className="w-full h-6 bg-muted rounded" />
                                <div className="w-full h-12 bg-muted rounded" />
                                <div className="w-1/2 h-4 bg-muted rounded mt-auto" />
                            </div>
                        </div>
                    ))}
                </div>
            </section>
        </div>
    );
}

/**
 * 文章详情真实视图组件
 */
function ArticleDetailContent({ article }: { article: ArticleDetailData }) {
    const [copied, setCopied] = useState<boolean>(false);
    const [isBookmarked, setIsBookmarked] = useState<boolean>(false);

    const handleCopyLink = () => {
        navigator.clipboard.writeText(window.location.href);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="w-full bg-background min-h-screen pb-20">
            {/* 1. 顶部吸顶导航栏 */}
            <div className="sticky top-0 z-10 backdrop-blur-md bg-background/80 border-b border-border/40 px-4 md:px-8 py-3">
                <div className="max-w-5xl mx-auto flex items-center justify-between">
                    <Link href="/articles">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="flex items-center gap-2 text-muted-foreground hover:text-foreground -ml-2"
                        >
                            <ArrowLeft className="w-4 h-4" />
                            <span>返回文章列表</span>
                        </Button>
                    </Link>

                    <div className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            size="icon"
                            className="rounded-full h-9 w-9 text-muted-foreground hover:text-foreground"
                            onClick={() => setIsBookmarked(!isBookmarked)}
                        >
                            <Bookmark className={`w-4 h-4 ${isBookmarked ? "fill-primary text-primary" : ""}`} />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon"
                            className="rounded-full h-9 w-9 text-muted-foreground hover:text-foreground"
                            onClick={handleCopyLink}
                        >
                            {copied ? <Check className="w-4 h-4 text-green-500" /> : <Share2 className="w-4 h-4" />}
                        </Button>
                    </div>
                </div>
            </div>

            {/* 2. 文章主体 */}
            <article className="max-w-3xl mx-auto px-4 md:px-6 pt-8 md:pt-12">
                <div className="mb-4 flex flex-wrap gap-2">
                    {article.categories?.map((category) => (
                        <span key={category.id} className="inline-block px-3 py-1 rounded-full text-xs font-semibold bg-primary/10 text-primary">
                            {category.name}
                        </span>
                    ))}
                </div>

                <h1 className="text-3xl md:text-5xl font-extrabold tracking-tight text-foreground leading-[1.15] mb-4">
                    {article.title}
                </h1>
                <p className="text-lg md:text-xl text-muted-foreground leading-relaxed mb-8">
                    {article.excerpt}
                </p>

                <div className="flex items-center justify-between py-4 border-y border-border/60 mb-8">
                    <div className="flex items-center gap-3">
                        <img
                            src={article.author?.avatar}
                            alt={article.author?.name}
                            className="w-11 h-11 rounded-full object-cover ring-2 ring-background"
                        />
                        <div>
                            <div className="font-semibold text-sm text-foreground">{article.author?.name}</div>
                            <div className="flex items-center gap-3 text-xs text-muted-foreground mt-0.5">
                                <span className="flex items-center gap-1">
                                    <Calendar className="w-3.5 h-3.5" />
                                    {article.published_at || article.date}
                                </span>
                                <span>•</span>
                                <span className="flex items-center gap-1">
                                    <Clock className="w-3.5 h-3.5" />
                                    {article.read_time}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div className="hidden sm:flex items-center gap-1">
                        <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground">
                            <Twitter className="w-4 h-4" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground">
                            <Linkedin className="w-4 h-4" />
                        </Button>
                        <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground" onClick={handleCopyLink}>
                            <LinkIcon className="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                {article.cover_image && (
                    <div className="mb-10 rounded-2xl overflow-hidden shadow-lg bg-muted">
                        <img
                            src={article.cover_image}
                            alt={article.title}
                            className="w-full max-h-[500px] object-cover"
                        />
                    </div>
                )}

                <div
                    className="article-content prose dark:prose-invert max-w-none mb-12"
                    dangerouslySetInnerHTML={{ __html: article.detail?.content || '' }}
                />

                {article.tags && article.tags.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 pt-6 border-t border-border">
                        <span className="text-xs font-medium text-muted-foreground mr-2">相关标签：</span>
                        {article.tags.map((tag) => (
                            <span key={tag.id} className="px-3 py-1 rounded-md bg-muted text-xs font-medium text-muted-foreground hover:text-foreground transition-colors cursor-pointer">
                                #{tag.name}
                            </span>
                        ))}
                    </div>
                )}
            </article>

            {/* 3. 延伸阅读推荐 */}
            {article.related_articles && article.related_articles.length > 0 && (
                <section className="max-w-5xl mx-auto px-4 md:px-8 mt-20 pt-12 border-t border-border">
                    <div className="flex items-center justify-between mb-8">
                        <h3 className="text-2xl font-bold text-foreground tracking-tight">延伸阅读</h3>
                        <span className="text-sm text-muted-foreground">探索更多精彩观点</span>
                    </div>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                        {article.related_articles.map((relArticle) => (
                            <ArticleCard key={relArticle.id} article={relArticle} />
                        ))}
                    </div>
                </section>
            )}
        </div>
    );
}

export default function ArticleDetailPage() {
    const { article } = usePage<PageProps>().props;

    return (
        <Deferred data="article" fallback={<ArticleShowSkeleton />}>
            {article && <ArticleDetailContent article={article} />}
        </Deferred>
    );
}
