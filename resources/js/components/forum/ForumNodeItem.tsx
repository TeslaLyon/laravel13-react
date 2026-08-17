import React from 'react';
import { Link } from '@inertiajs/react';
import { MessagesSquare, ExternalLink, CornerDownRight } from 'lucide-react';
import { ForumNode } from '@/types/forum';

export default function ForumNodeItem({ node }: { node: ForumNode }) {
    const isLinkNode = node.nodeType === 'link';

    // 动态决定跳转 Target
    const targetUrl = isLinkNode
        ? (node.linkUrl || '#')
        : `/forum/nodes/${node.id}`;

    return (
        <div className="group relative flex flex-col md:flex-row items-stretch border-b border-border/60 last:border-b-0 hover:bg-muted/30 transition-colors duration-150">

            {/* 1. 左侧：图标 + 标题 + 描述 + 子版块 */}
            <div className="flex items-start gap-3.5 p-4 flex-1 min-w-0">
                <div className="mt-0.5 shrink-0">
                    <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-muted text-muted-foreground/80">
                        {isLinkNode ? (
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
                                className="font-bold text-base text-foreground hover:text-primary transition-colors flex items-center gap-1.5"
                            >
                                <span>{node.name}</span>
                                <ExternalLink className="w-3.5 h-3.5 text-muted-foreground" />
                            </a>
                        ) : (
                            <Link
                                href={targetUrl}
                                className="font-bold text-base text-foreground hover:text-primary transition-colors leading-snug"
                            >
                                {node.name}
                            </Link>
                        )}
                    </div>

                    {node.description && (
                        <p className="text-xs text-muted-foreground mt-1 line-clamp-1">
                            {node.description}
                        </p>
                    )}

                    {/* 子版块 (Sub-forums) */}
                    {node.subForums && node.subForums.length > 0 && (
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1 mt-2.5 pt-2 border-t border-border/40 text-xs">
                            <span className="text-muted-foreground/60 flex items-center gap-1">
                                <CornerDownRight className="w-3 h-3" /> 子版块：
                            </span>
                            {node.subForums.map((sub) => (
                                <Link
                                    key={sub.id}
                                    href={`/forum/nodes/${sub.id}`}
                                    className="text-muted-foreground hover:text-primary transition-colors"
                                >
                                    {sub.name}
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

            {/* 3. 右侧：最新动态 (Last Post) */}
            <div className="flex items-center px-4 py-3 md:w-72 lg:w-80 shrink-0 border-t md:border-t-0 md:border-l border-border/30 bg-muted/5">
                {!isLinkNode && node.lastPost ? (
                    <div className="flex flex-col min-w-0 flex-1">
                        <Link
                            href={`/forum/threads/${node.lastPost.threadId}`}
                            className="text-xs font-semibold text-foreground hover:text-primary transition-colors truncate"
                        >
                            {node.lastPost.threadTitle}
                        </Link>
                        <div className="flex items-center gap-1.5 text-[11px] text-muted-foreground mt-1">
                            <span className="font-medium text-foreground/80 truncate">{node.lastPost.authorName}</span>
                            <span>•</span>
                            <time>{node.lastPost.createdAt}</time>
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
