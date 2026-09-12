import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Pin, Lock, Eye, MessageSquare, ChevronLeft } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import PostFloorItem from '@/components/forum/PostFloorItem';
import QuickReplyEditor from '@/components/forum/QuickReplyEditor';
import ForumPagination from '@/components/forum/ForumPagination';
import { ThreadShowPageProps } from '@/types/forum';

export default function ThreadShow({
    breadcrumbs,
    thread,
    posts,
    canReply,
    seo,
}: ThreadShowPageProps) {
    // 🎯 1. 精确构建当前帖子的纯净基础路由 (例如: /forum/threads/31/my-title)
    const threadBaseUrl = `/forum/threads/${thread.id}${thread.slug ? `/${thread.slug}` : ''}`;

    return (
        <div className="min-h-screen bg-background text-foreground py-6">
            <Head title={`${thread.title} - 论坛交流`}>
                {seo?.canonicalUrl && <link rel="canonical" href={seo.canonicalUrl} />}
                {seo?.prevPageUrl && <link rel="prev" href={seo.prevPageUrl} />}
                {seo?.nextPageUrl && <link rel="next" href={seo.nextPageUrl} />}
            </Head>

            <div className="max-w-6xl mx-auto px-4 sm:px-6 space-y-5">
                {/* 标头卡片 */}
                <header className="bg-card border border-border/80 rounded-2xl p-5 md:p-6 shadow-xs space-y-3">
                    {/* 置顶与标签徽章 */}
                    <div className="flex items-center gap-2 flex-wrap">
                        {thread.isSticky && (
                            <Badge
                                variant="outline"
                                className="h-7 gap-1.5 px-3 py-1 text-sm font-bold rounded-lg bg-red-500/15 text-red-600 dark:text-red-400 border-red-500/30 shadow-xs select-none shrink-0"
                            >
                                <Pin className="w-4 h-4 fill-red-500/40 shrink-0" />
                                <span>置顶</span>
                            </Badge>
                        )}

                        {thread.prefixes &&
                            thread.prefixes.map((prefix) => (
                                <Badge
                                    key={prefix.id}
                                    variant="outline"
                                    className="h-7 px-3 py-1 text-sm font-bold rounded-lg shadow-xs leading-none shrink-0 tracking-wide select-none border-transparent transition-all hover:opacity-90 hover:scale-[1.02]"
                                    style={{
                                        backgroundColor: prefix.bgColor || '#6f42c1',
                                        color: prefix.textColor || '#ffffff',
                                    }}
                                >
                                    {prefix.name}
                                </Badge>
                            ))}

                        {thread.isLocked && (
                            <Badge
                                variant="outline"
                                className="h-7 gap-1.5 px-2.5 py-1 text-xs font-semibold rounded-lg bg-muted text-muted-foreground border-border/80 shrink-0 select-none"
                            >
                                <Lock className="w-3.5 h-3.5" />
                                <span>已锁定</span>
                            </Badge>
                        )}
                    </div>

                    <h1 className="text-xl md:text-2xl font-bold text-foreground leading-snug tracking-tight">
                        {thread.title}
                    </h1>

                    <div className="flex items-center justify-between text-xs text-muted-foreground pt-2 border-t border-border/40 flex-wrap gap-2">
                        <div className="flex items-center gap-3">
                            <span>发布于 {thread.createdAt}</span>
                            <span>•</span>
                            <span className="flex items-center gap-1">
                                <Eye className="w-3.5 h-3.5" /> {thread.viewCount} 次浏览
                            </span>
                            <span>•</span>
                            <span className="flex items-center gap-1">
                                <MessageSquare className="w-3.5 h-3.5" /> {thread.replyCount} 条回复
                            </span>
                        </div>

                        <Link href={`/forum/nodes/${thread.nodeId}`}>
                            <Button variant="ghost" size="sm" className="h-7 text-xs gap-1">
                                <ChevronLeft className="w-3.5 h-3.5" /> 返回版块
                            </Button>
                        </Link>
                    </div>
                </header>

                {/* 🎯 2. 顶端分页栏：传入 baseUrl */}
                <div className="py-0.5">
                    <ForumPagination pagination={posts} baseUrl={threadBaseUrl} />
                </div>

                {/* 3. 楼层列表 */}
                <main className="space-y-4">
                    {posts.data.map((post) => (
                        <PostFloorItem key={post.id} post={post} />
                    ))}
                </main>

                {/* 🎯 4. 底端分页栏：传入 baseUrl 并开启统计 */}
                <div className="pt-1 pb-2">
                    <ForumPagination
                        pagination={posts}
                        baseUrl={threadBaseUrl}
                        showStats={true}
                    />
                </div>

                {/* 5. 底部回复框 */}
                <section className="pt-2">
                    <QuickReplyEditor threadId={thread.id} canReply={canReply} />
                </section>
            </div>
        </div>
    );
}
