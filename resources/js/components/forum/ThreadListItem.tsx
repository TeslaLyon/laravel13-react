import React from 'react';
import { Link } from '@inertiajs/react';
import { Pin, Lock, MessageSquare, Eye } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { ThreadItem } from '@/types/forum';

export default function ThreadListItem({ thread }: { thread: ThreadItem }) {
    return (
        <div className={`group flex flex-col md:flex-row items-stretch border-b border-border/60 last:border-b-0 hover:bg-muted/30 transition-colors ${thread.isSticky ? 'bg-primary/5' : ''
            }`}>

            {/* 1. 左侧：发帖人头像 + 前缀 + 标题 + 作者信息 */}
            <div className="flex items-center gap-3.5 p-4 flex-1 min-w-0">
                <Avatar className="w-9 h-9 rounded-xl shrink-0 border border-border">
                    <AvatarImage src={thread.authorAvatar} alt={thread.authorName} />
                    <AvatarFallback className="text-xs rounded-xl">{thread.authorName.charAt(0)}</AvatarFallback>
                </Avatar>

                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                        {/* 置顶徽章 */}
                        {thread.isSticky && (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                <Pin className="w-3 h-3" /> 置顶
                            </span>
                        )}

                        {/* 主题分类前缀 (例如: [公告], [分享]) */}
                        {thread.prefix && (
                            <span className="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-primary/10 text-primary">
                                {thread.prefix}
                            </span>
                        )}

                        {/* 主题标题 */}
                        <Link
                            href={`/forum/threads/${thread.id}`}
                            className="font-semibold text-base text-foreground hover:text-primary transition-colors truncate leading-snug"
                        >
                            {thread.title}
                        </Link>

                        {/* 锁定标识 */}
                        {thread.isLocked && <Lock className="w-3.5 h-3.5 text-muted-foreground/70 shrink-0" />}
                    </div>

                    {/* 发起人与时间 */}
                    <div className="flex items-center gap-2 text-xs text-muted-foreground mt-1">
                        <span className="font-medium text-foreground/80">{thread.authorName}</span>
                        <span>•</span>
                        <time>{thread.createdAt}</time>
                    </div>
                </div>
            </div>

            {/* 2. 中间：回复数与浏览数 */}
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

            {/* 3. 右侧：最新跟帖回复信息 */}
            <div className="flex items-center px-4 py-3 md:w-64 shrink-0 border-t md:border-t-0 md:border-l border-border/30 bg-muted/5 text-xs">
                {thread.lastPost ? (
                    <div className="flex flex-col min-w-0">
                        <div className="flex items-center gap-1.5 text-muted-foreground">
                            <span>最新回复:</span>
                            <span className="font-semibold text-foreground/90 truncate">{thread.lastPost.authorName}</span>
                        </div>
                        <time className="text-[11px] text-muted-foreground/70 mt-0.5">{thread.lastPost.createdAt}</time>
                    </div>
                ) : (
                    <span className="text-muted-foreground/60 italic">暂无跟帖</span>
                )}
            </div>

        </div>
    );
}
