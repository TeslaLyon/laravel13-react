import React from 'react';
import { Head, Link } from '@inertiajs/react';
import ThreadListItem from '@/components/forum/ThreadListItem';
import { ThreadItem, ForumNode } from '@/types/forum';
import { SquarePen, ChevronRight, Home } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface ForumShowProps {
    node: ForumNode;
    threads: {
        data: ThreadItem[];
        links: any[];
    };
}

export default function ForumShowPage({ node, threads }: ForumShowProps) {
    return (
        <div className="min-h-screen bg-background pb-16">
            <Head title={`${node.name} - 主题列表`} />

            <main className="max-w-[1400px] mx-auto px-4 md:px-8 pt-6">

                {/* 面包屑导航 */}
                <nav className="flex items-center gap-2 text-xs text-muted-foreground mb-6">
                    <Link href="/forum" className="hover:text-foreground flex items-center gap-1">
                        <Home className="w-3.5 h-3.5" /> 大厅
                    </Link>
                    <ChevronRight className="w-3 h-3" />
                    <span className="text-foreground font-semibold">{node.name}</span>
                </nav>

                {/* 版块头部区域 */}
                <div className="bg-card rounded-2xl border border-border/80 p-6 mb-6 flex flex-col md:flex-row md:items-center justify-between gap-4 shadow-sm">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground tracking-tight">{node.name}</h1>
                        {node.description && (
                            <p className="text-sm text-muted-foreground mt-1">{node.description}</p>
                        )}
                    </div>

                    <Link href={`/forum/threads/create?node_id=${node.id}`}>
                        <Button className="gap-2 rounded-xl font-semibold shrink-0">
                            <SquarePen className="w-4 h-4" />
                            <span>在此版块发帖</span>
                        </Button>
                    </Link>
                </div>

                {/* 主题列表卡片 */}
                <div className="bg-card rounded-2xl border border-border/80 shadow-sm overflow-hidden">
                    <div className="px-5 py-3 bg-muted/60 border-b border-border/80 font-bold text-sm text-foreground">
                        全部讨论话题
                    </div>

                    <div className="divide-y divide-border/40">
                        {threads.data && threads.data.length > 0 ? (
                            threads.data.map((thread) => (
                                <ThreadListItem key={thread.id} thread={thread} />
                            ))
                        ) : (
                            <div className="p-12 text-center text-xs text-muted-foreground">
                                该版块下暂无主题帖，来发布第一篇吧！
                            </div>
                        )}
                    </div>
                </div>

            </main>
        </div>
    );
}
