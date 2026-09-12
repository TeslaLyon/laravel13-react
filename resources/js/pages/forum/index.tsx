import React from 'react';
import { Head, Link } from '@inertiajs/react';
import ForumCategoryBlock from '@/components/forum/ForumCategoryBlock';
import ForumNoticeBanner from '@/components/forum/ForumNoticeBanner';
import { ForumCategory, ForumNoticeItem } from '@/types/forum';
import { SquarePen, Compass } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ForumIndexProps {
    categories?: ForumCategory[];
    notices?: ForumNoticeItem[]; // 🎯 1. 声明公告列表类型
}

export default function ForumIndexPage({ categories = [], notices = [] }: ForumIndexProps) {
    return (
        <div className="min-h-screen bg-background pb-16">
            <Head title="社区论坛大厅" />

            <main className="container mx-auto px-4 md:px-8 pt-6">

                {/* 1. 页面头部控制区 */}
                <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <div className="flex items-center gap-2 text-primary font-semibold text-sm mb-1">
                            <Compass className="w-4 h-4" />
                            <span>社区大厅</span>
                        </div>
                        <h1 className="text-2xl sm:text-3xl font-extrabold text-foreground tracking-tight">
                            探索版块与讨论
                        </h1>
                    </div>

                    <Link href="/forum/threads/create">
                        <Button className="gap-2 rounded-xl shadow-xs font-semibold shrink-0">
                            <SquarePen className="w-4 h-4" />
                            <span>发布新帖</span>
                        </Button>
                    </Link>
                </div>

                {/* 🎯 2. 全局公告通知模块 (位于大厅头部与版块分类列表之间) */}
                {notices && notices.length > 0 && (
                    <div className="mb-6">
                        <ForumNoticeBanner notices={notices} />
                    </div>
                )}

                {/* 3. 分类卡片列表 */}
                <div className="space-y-6">
                    {categories.length > 0 ? (
                        categories.map((category) => (
                            <ForumCategoryBlock key={category.id} category={category} />
                        ))
                    ) : (
                        <div className="p-12 text-center text-muted-foreground bg-card rounded-2xl border border-border/80">
                            暂无版块数据，请先运行填充脚本。
                        </div>
                    )}
                </div>

            </main>
        </div>
    );
}
