import React, { useMemo } from 'react';
import { Link } from '@inertiajs/react';
import { Pin, Lock } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import UserHoverCard from './UserHoverCard';
import { ThreadItem } from '@/types/forum';
import { show } from '@/actions/App/Http/Controllers/Forum/ThreadController';

const POSTS_PER_PAGE = 15;

export default function ThreadListItem({ thread }: { thread: ThreadItem }) {
    // 1. 发帖作者与最新回复作者安全解构
    const author = thread.author || {
        id: (thread as any).authorId || 0,
        name: thread.authorName || '匿名用户',
        avatar: thread.authorAvatar || null,
    };
    const authorName = author.name || thread.authorName || '匿名用户';
    const authorAvatar = author.avatar || thread.authorAvatar || '';
    const authorInitial = authorName.charAt(0).toUpperCase();

    const lastPostAuthor = thread.lastPost?.author || {
        id: 0,
        name: thread.lastPost?.authorName || '匿名',
    };
    const lastPostAuthorName = lastPostAuthor.name || thread.lastPost?.authorName || '匿名';

    // 2. 快捷尾页页码计算
    const { totalPages, miniPages } = useMemo(() => {
        const totalPosts = (thread.replyCount || 0) + 1;
        const pages = Math.ceil(totalPosts / POSTS_PER_PAGE);

        if (pages <= 1) return { totalPages: pages, miniPages: [] };
        if (pages === 2) return { totalPages: pages, miniPages: [2] };
        if (pages === 3) return { totalPages: pages, miniPages: [2, 3] };
        return {
            totalPages: pages,
            miniPages: [pages - 2, pages - 1, pages],
        };
    }, [thread.replyCount]);

    return (
        <div
            className={`group relative flex flex-col md:flex-row items-stretch border-b border-border/60 last:border-b-0 hover:bg-muted/30 transition-all duration-150 ${thread.isSticky
                // 🎯 核心改动 1：置顶帖背景色由黄色改为红色微光，左侧竖线改为红色
                ? 'bg-red-500/[0.04] dark:bg-red-500/[0.07] border-l-4 border-l-red-500/80'
                : 'border-l-4 border-l-transparent'
                }`}
        >
            {/* 左侧：发帖人头像 + 前缀标签 + 标题 (流式混排换行) + 作者与尾页信息 */}
            <div className="flex items-start md:items-center gap-3.5 p-4 flex-1 min-w-0">
                <UserHoverCard author={author}>
                    <div className="shrink-0 cursor-pointer group/avatar mt-0.5 md:mt-0">
                        <Avatar className="w-10 h-10 border border-border/70 shadow-2xs transition-transform group-hover/avatar:scale-105">
                            <AvatarImage src={authorAvatar} alt={authorName} className="object-cover" />
                            <AvatarFallback className="text-xs font-bold bg-primary/10 text-primary">
                                {authorInitial}
                            </AvatarFallback>
                        </Avatar>
                    </div>
                </UserHoverCard>

                <div className="flex-1 min-w-0 space-y-1">
                    {/* 🎯 第一行：流式排版 */}
                    <div className="leading-snug">
                        {/* 🎯 核心改动 2：微型置顶徽章改用红色系 */}
                        {thread.isSticky && (
                            <Badge
                                variant="outline"
                                className="inline-flex align-middle -translate-y-px h-4.5 gap-0.5 px-1.5 mr-1.5 text-[10px] font-bold rounded-sm bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30 shadow-none select-none shrink-0"
                            >
                                <Pin className="w-2.5 h-2.5 fill-amber-500/40 shrink-0" />
                                <span>置顶</span>
                            </Badge>
                        )}

                        {/* 彩色分类前缀标签 */}
                        {thread.prefixes && thread.prefixes.length > 0 ? (
                            thread.prefixes.map((prefix) => (
                                <Badge
                                    key={prefix.id}
                                    variant="outline"
                                    className="inline-flex align-middle -translate-y-px h-4.5 px-1.5 mr-1.5 text-[10px] font-bold rounded-sm shadow-none leading-none shrink-0 tracking-wide select-none border-transparent transition-all hover:opacity-90"
                                    style={{
                                        backgroundColor: prefix.bgColor || '#6f42c1',
                                        color: prefix.textColor || '#ffffff',
                                    }}
                                >
                                    {prefix.name}
                                </Badge>
                            ))
                        ) : thread.prefix ? (
                            <span className="inline-flex align-middle -translate-y-px px-1.5 py-0.5 mr-1.5 rounded text-[10px] font-semibold bg-primary/10 text-primary select-none shrink-0">
                                {thread.prefix}
                            </span>
                        ) : null}

                        {/* 🎯 核心改动 3：置顶帖标题悬停色由黄色改为红色 */}
                        <Link
                            href={show.url({ thread: thread.id, slug: thread.slug })}
                            className={`inline align-middle font-semibold text-base break-words transition-colors hover:underline hover:underline-offset-2 ${thread.isSticky
                                ? 'text-foreground font-bold hover:text-red-600 dark:hover:text-red-400'
                                : 'text-foreground hover:text-primary'
                                }`}
                        >
                            {thread.title}
                        </Link>

                        {/* 锁定标识 */}
                        {thread.isLocked && (
                            <span
                                title="该主题已锁定回复"
                                className="inline-flex align-middle ml-1.5 text-muted-foreground/70"
                            >
                                <Lock className="w-3.5 h-3.5 shrink-0" />
                            </span>
                        )}
                    </div>

                    {/* 第二行：发起人 + 时间 + 快捷页码 */}
                    <div className="flex items-center gap-1.5 text-xs text-muted-foreground pt-0.5 truncate flex-wrap">
                        <UserHoverCard author={author}>
                            <span className="font-medium text-foreground/80 hover:text-primary hover:underline cursor-pointer transition-colors">
                                {authorName}
                            </span>
                        </UserHoverCard>
                        <span>•</span>
                        <time>{thread.createdAt}</time>

                        {miniPages.length > 0 && (
                            <div className="inline-flex items-center gap-1 ml-1 select-none">
                                <span className="text-muted-foreground/40">•</span>
                                {totalPages > 3 && (
                                    <span className="text-[10px] text-muted-foreground/40 tracking-tighter">...</span>
                                )}
                                {miniPages.map((page) => {
                                    // 🎯 生成纯净的路径型 URL (/forum/threads/31/my-slug/page-2)
                                    const threadBase = `/forum/threads/${thread.id}${thread.slug ? `/${thread.slug}` : ''}`;
                                    const targetUrl = page === 1 ? threadBase : `${threadBase}/page-${page}`;

                                    return (
                                        <Link
                                            key={page}
                                            href={targetUrl}
                                            className="inline-flex items-center justify-center min-w-[18px] h-4.5 px-1 text-[10px] font-semibold rounded bg-muted/60 hover:bg-primary/10 hover:text-primary hover:border-primary/30 border border-border/60 text-muted-foreground/90 transition-colors"
                                            title={`跳转至第 ${page} 页`}
                                        >
                                            {page}
                                        </Link>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </div>

            {/* 中间：回复数与浏览数 */}
            <div className="hidden sm:flex items-center justify-center gap-6 px-4 py-3 w-40 shrink-0 text-xs text-muted-foreground border-l border-border/30 bg-muted/10">
                <div className="text-center">
                    <span className="block font-bold text-foreground text-sm">{thread.replyCount}</span>
                    <span className="text-[11px]">回复</span>
                </div>
                <div className="text-center">
                    <span className="block font-bold text-foreground text-sm">{thread.viewCount}</span>
                    <span className="text-[11px]">浏览</span>
                </div>
            </div>

            {/* 右侧：最新跟帖信息 */}
            <div className="flex items-center px-4 py-3 md:w-64 shrink-0 border-t md:border-t-0 md:border-l border-border/30 bg-muted/5 text-xs">
                {thread.lastPost ? (
                    <div className="flex flex-col min-w-0">
                        <div className="flex items-center gap-1.5 text-muted-foreground truncate">
                            <span>最新回复:</span>
                            <UserHoverCard author={lastPostAuthor}>
                                <span className="font-semibold text-foreground/90 hover:text-primary hover:underline cursor-pointer truncate transition-colors">
                                    {lastPostAuthorName}
                                </span>
                            </UserHoverCard>
                        </div>
                        <time className="text-[11px] text-muted-foreground/70 mt-0.5">
                            {thread.lastPost.createdAt}
                        </time>
                    </div>
                ) : (
                    <span className="text-muted-foreground/60 italic">暂无跟帖</span>
                )}
            </div>
        </div>
    );
}
