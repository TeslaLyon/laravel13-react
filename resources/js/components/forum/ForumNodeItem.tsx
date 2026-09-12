import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { MessagesSquare, ExternalLink, CornerDownRight } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import UserHoverCard from './UserHoverCard';
import { ForumNode } from '@/types/forum';
import { show } from '@/actions/App/Http/Controllers/Forum/ThreadController';
import { Badge } from '@/components/ui/badge';

export default function ForumNodeItem({ node }: { node: ForumNode }) {
    const isLinkNode = node.nodeType === 'link';
    const [imgLoadError, setImgLoadError] = useState(false);

    // 动态决定跳转 Target
    const targetUrl = isLinkNode
        ? (node.linkUrl || '#')
        : `/forum/nodes/${node.id}`;

    const hasCustomIcon = Boolean(node.iconUrl && !imgLoadError);

    // 🎯 核心安全解构：提取作者信息与兜底字段，消除 TS undefined 报错
    const lastPost = node.lastPost;
    const author = lastPost?.author;
    const authorName = author?.name || lastPost?.authorName || '匿名用户';
    const authorAvatar = author?.avatar || '';
    const authorInitial = authorName.charAt(0).toUpperCase();

    return (
        <div className="group relative flex flex-col md:flex-row items-stretch border-b border-border/60 last:border-b-0 hover:bg-muted/30 transition-colors duration-150">

            {/* 1. 左侧：图标 + 标题 + 描述 + 子版块 */}
            <div className="flex items-start gap-3.5 p-4 flex-1 min-w-0">
                <div className="mt-0.5 shrink-0">
                    <div className="w-10 h-10 rounded-xl overflow-hidden flex items-center justify-center bg-muted text-muted-foreground/80 border border-border/40">
                        {hasCustomIcon ? (
                            <img
                                src={node.iconUrl!}
                                alt={node.title}
                                className="w-full h-full object-cover"
                                onError={() => setImgLoadError(true)}
                            />
                        ) : isLinkNode ? (
                            <ExternalLink className="w-5 h-5 text-amber-500" />
                        ) : (
                            <MessagesSquare className="w-5 h-5 text-primary" />
                        )}
                    </div>
                </div>

                <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                        {isLinkNode ? (
                            <a
                                href={targetUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="font-bold text-base text-foreground hover:text-primary hover:underline hover:underline-offset-4 decoration-primary/40 transition-all flex items-center gap-1.5 leading-snug"
                            >
                                <span>{node.title}</span>
                                <ExternalLink className="w-3.5 h-3.5 text-muted-foreground" />
                            </a>
                        ) : (
                            <Link
                                href={targetUrl}
                                className="font-bold text-base text-foreground hover:text-primary hover:underline hover:underline-offset-4 decoration-primary/40 transition-all leading-snug"
                            >
                                {node.title}
                            </Link>
                        )}
                    </div>

                    {node.description && (
                        <p className="text-xs text-muted-foreground mt-1 line-clamp-1 leading-relaxed">
                            {node.description}
                        </p>
                    )}

                    {/* 子版块 (Sub-forums) */}
                    {node.subForums && node.subForums.length > 0 && (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2.5 pt-2 border-t border-border/40 text-xs">
                            <span className="text-muted-foreground/60 flex items-center gap-1 shrink-0 select-none">
                                <CornerDownRight className="w-3 h-3" /> 子版块：
                            </span>
                            {node.subForums.map((sub) => (
                                <Link
                                    key={sub.id}
                                    href={`/forum/nodes/${sub.id}`}
                                    className="text-muted-foreground hover:text-primary hover:underline hover:underline-offset-2 decoration-primary/50 transition-colors"
                                >
                                    {sub.title}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {/* 2. 中间：数据统计 (仅非外链版块显示) */}
            {!isLinkNode && (
                <div className="hidden lg:flex items-center justify-center gap-6 px-4 py-3 w-44 shrink-0 text-xs text-muted-foreground border-l border-border/30 bg-muted/10">
                    <div className="text-center">
                        <span className="block font-bold text-foreground text-sm">{node.threadCount}</span>
                        <span className="text-[11px]">主题</span>
                    </div>
                    <div className="text-center">
                        <span className="block font-bold text-foreground text-sm">{node.messageCount}</span>
                        <span className="text-[11px]">帖子</span>
                    </div>
                </div>
            )}

            {/* 3. 右侧：最新动态 (头像 + 彩色前缀标签 + 标题 + 悬浮资料卡) */}
            <div className="flex items-center px-4 py-3 md:w-80 lg:w-96 shrink-0 border-t md:border-t-0 md:border-l border-border/30 bg-muted/5">
                {!isLinkNode && lastPost ? (
                    <div className="flex items-center gap-3 min-w-0 flex-1">

                        {/* 左侧：发帖用户圆形头像 (带悬浮卡片) */}
                        <UserHoverCard author={author}>
                            <div className="shrink-0 cursor-pointer group/avatar">
                                <Avatar className="w-9 h-9 border border-border/60 transition-transform group-hover/avatar:scale-105 shadow-2xs">
                                    <AvatarImage src={authorAvatar} alt={authorName} />
                                    <AvatarFallback className="text-xs font-bold bg-primary/10 text-primary">
                                        {authorInitial}
                                    </AvatarFallback>
                                </Avatar>
                            </div>
                        </UserHoverCard>

                        {/* 右侧：两行布局 */}
                        <div className="flex flex-col min-w-0 flex-1">
                            {/* 第一行：彩色分类前缀标签 + 帖子标题 */}
                            <div className="flex items-center gap-1.5 min-w-0">
                                {lastPost.prefixes && lastPost.prefixes.length > 0 && (
                                    <div className="flex items-center gap-1 shrink-0">
                                        {lastPost.prefixes.map((prefix) => (
                                            <Badge
                                                key={prefix.id}
                                                variant="outline"
                                                className="h-4.5 px-1.5 text-[10px] font-bold rounded-sm shadow-none leading-none shrink-0 tracking-wide select-none border-transparent transition-all hover:opacity-90"
                                                style={{
                                                    backgroundColor: prefix.bgColor || '#6f42c1',
                                                    color: prefix.textColor || '#ffffff',
                                                }}
                                            >
                                                {prefix.name}
                                            </Badge>
                                        ))}
                                    </div>
                                )}

                                <Link
                                    href={show.url({ thread: lastPost.threadId, slug: lastPost.slug })}
                                    className="text-xs font-bold text-foreground hover:text-primary hover:underline hover:underline-offset-2 decoration-primary/40 transition-colors truncate"
                                    title={lastPost.threadTitle || ''}
                                >
                                    {lastPost.threadTitle || '无标题'}
                                </Link>
                            </div>

                            {/* 第二行：发布时间 · 作者名字 (带悬浮卡片) */}
                            <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-1 truncate">
                                <time className="shrink-0">{lastPost.createdAt}</time>
                                <span className="text-muted-foreground/40">•</span>
                                <UserHoverCard author={author}>
                                    <span className="font-medium text-foreground/80 hover:text-primary hover:underline cursor-pointer truncate transition-colors">
                                        {authorName}
                                    </span>
                                </UserHoverCard>
                            </div>
                        </div>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground/60 italic">
                        {isLinkNode ? '外部跳转链接' : '暂无动态'}
                    </span>
                )}
            </div>

        </div>
    );
}
