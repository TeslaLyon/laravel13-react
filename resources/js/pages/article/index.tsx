import React, { useState, useEffect } from 'react';
import ArticleCard, { ArticleItem } from "@/components/article/Card";
import { Button } from "@/components/ui/button";
import { BookOpenText } from "lucide-react";

// 顶部分类胶囊标签
const CATEGORIES = ["推荐", "深度解析", "幕后故事", "器材评测", "行业动态", "创作者访谈"];

// 模拟数据：充满高级感的占位图和文本
const fetchArticles = async (): Promise<ArticleItem[]> => {
    return new Promise((resolve) => setTimeout(() => resolve([
        {
            id: "a1",
            category: "深度解析",
            title: "光影的艺术：如何在暗光环境下拍摄出电影级质感",
            excerpt: "夜间拍摄并不意味着妥协。通过合理运用环境光源、控制噪点以及后期的巧妙调色，即使是消费级相机也能呈现出令人惊叹的赛博朋克风格...",
            coverImage: "https://images.pexels.com/photos/3379934/pexels-photo-3379934.jpeg?auto=compress&cs=tinysrgb&w=800",
            authorName: "Roger Deak", authorAvatar: "https://i.pravatar.cc/150?img=11",
            date: "2026-06-25", readTime: "8 分钟阅读"
        },
        {
            id: "a2",
            category: "幕后故事",
            title: "从构思到杀青：一部独立微电影的诞生全记录",
            excerpt: "预算只有五千美元，我们要如何完成一部科幻短片？这篇文章详细记录了我们团队从剧本创作、选角、场地协调到最终特效渲染的每一个日夜。",
            coverImage: "https://images.pexels.com/photos/2510428/pexels-photo-2510428.jpeg?auto=compress&cs=tinysrgb&w=800",
            authorName: "Sarah Chen", authorAvatar: "https://i.pravatar.cc/150?img=5",
            date: "2026-06-28", readTime: "12 分钟阅读"
        },
        {
            id: "a3",
            category: "器材评测",
            title: "索尼 A7S III 长期使用报告：它依然是目前的视频机皇吗？",
            excerpt: "经过整整一年的高强度工作使用，带着它去了极寒的冰岛和潮湿的热带雨林，是时候来谈谈这台相机的真实表现、优缺点以及对购买者的最终建议了。",
            coverImage: "https://images.pexels.com/photos/12026027/pexels-photo-12026027.jpeg?auto=compress&cs=tinysrgb&w=800",
            authorName: "极客影志", authorAvatar: "https://i.pravatar.cc/150?img=15",
            date: "2026-06-29", readTime: "15 分钟阅读"
        },
        {
            id: "a4",
            category: "行业动态",
            title: "AI 生成视频技术的突破，将如何重塑内容创作者的未来生态",
            excerpt: "随着 Sora 和 Runway 的不断进化，传统的实拍流程正在经历一场前所未有的革命。作为创作者，我们应该如何拥抱变革而不是被淘汰？",
            coverImage: "https://images.pexels.com/photos/8386440/pexels-photo-8386440.jpeg?auto=compress&cs=tinysrgb&w=800",
            authorName: "TechWeekly", authorAvatar: "https://i.pravatar.cc/150?img=33",
            date: "2026-06-29", readTime: "6 分钟阅读"
        }
    ]), 600));
};

export default function ArticleListPage() {
    const [articles, setArticles] = useState<ArticleItem[]>([]);
    const [isLoading, setIsLoading] = useState<boolean>(true);
    const [activeCategory, setActiveCategory] = useState("推荐");

    useEffect(() => {
        const loadData = async () => {
            setIsLoading(true);
            try {
                const data = await fetchArticles();
                setArticles(data);
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
            <div className="flex items-center justify-between mb-8">
                <div>
                    <h1 className="text-2xl md:text-3xl font-bold tracking-tight text-foreground flex items-center gap-3">
                        <BookOpenText className="w-7 h-7 text-primary" />
                        阅读专区
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1.5">沉下心来，探索深度优质内容</p>
                </div>
            </div>

            {/* 2. 胶囊标签滚动区 */}
            <div className="flex items-center gap-2 overflow-x-auto pb-4 mb-8 scrollbar-none snap-x">
                {CATEGORIES.map((category) => (
                    <button
                        key={category}
                        onClick={() => setActiveCategory(category)}
                        className={`px-4 py-1.5 rounded-full text-sm font-medium transition-all shrink-0 snap-center
              ${activeCategory === category
                                ? 'bg-primary text-primary-foreground shadow-md'
                                : 'bg-muted hover:bg-muted/80 text-muted-foreground hover:text-primary'
                            }`}
                    >
                        {category}
                    </button>
                ))}
            </div>

            {/* 3. 核心网格区：对于横向宽卡片，手机端 1 列，宽屏桌面最多 2 列 */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-x-3 gap-y-12 mx-auto">
                {isLoading
                    ? Array.from({ length: 4 }).map((_, index) => (
                        // 骨架屏：模拟左图右文结构
                        <div key={index} className="flex flex-col md:flex-row gap-5 w-full">
                            <div className="w-full md:w-[260px] lg:w-[300px] aspect-video md:aspect-[4/3] bg-muted animate-pulse rounded-xl" />
                            <div className="flex flex-col flex-1 gap-3 py-2">
                                <div className="w-3/4 h-6 bg-muted animate-pulse rounded-md" />
                                <div className="w-full h-16 bg-muted animate-pulse rounded-md mt-2" />
                                <div className="w-1/2 h-4 bg-muted animate-pulse rounded-md mt-auto" />
                            </div>
                        </div>
                    ))
                    : articles.map((article) => (
                        <ArticleCard key={article.id} article={article} />
                    ))}
            </div>

            {/* 4. 底部加载区域 */}
            {!isLoading && (
                <div className="flex flex-col items-center justify-center mt-20 mb-12 gap-2">
                    <Button variant="outline" className="text-foreground hover:bg-muted rounded-full px-10 h-10 border-border">
                        浏览更多文章
                    </Button>
                </div>
            )}
        </div>
    );
}
