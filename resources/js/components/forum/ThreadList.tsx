import React, { useMemo } from 'react';
import { Pin, MessagesSquare } from 'lucide-react';
import ThreadListItem from './ThreadListItem';
import { ThreadItem } from '@/types/forum';

interface ThreadListProps {
    threads: ThreadItem[];
}

export default function ThreadList({ threads }: ThreadListProps) {
    // 🎯 按 isSticky 拆分为置顶集合与普通主题集合
    const { stickyThreads, normalThreads } = useMemo(() => {
        const sticky: ThreadItem[] = [];
        const normal: ThreadItem[] = [];

        threads.forEach((t) => {
            if (t.isSticky) {
                sticky.push(t);
            } else {
                normal.push(t);
            }
        });

        return { stickyThreads: sticky, normalThreads: normal };
    }, [threads]);

    if (threads.length === 0) {
        return (
            <div className="p-12 text-center text-muted-foreground/70 border border-dashed rounded-2xl">
                <MessagesSquare className="w-10 h-10 mx-auto mb-2 text-muted-foreground/40" />
                <p className="text-sm font-medium">该版块暂无任何主题帖，快来发布第一篇吧！</p>
            </div>
        );
    }

    return (
        <div className="rounded-2xl border border-border/80 bg-card overflow-hidden shadow-xs">
            {/* 🎯 1. 置顶主题分区 (仅当存在置顶帖时渲染) */}
            {stickyThreads.length > 0 && (
                <div className="border-b-2 border-border/80">
                    <div className="flex items-center gap-2 px-4 py-2.5 bg-amber-500/10 dark:bg-amber-500/15 border-b border-amber-500/20 text-xs font-bold text-amber-700 dark:text-amber-400">
                        <Pin className="w-3.5 h-3.5 fill-amber-500/50" />
                        <span>置顶公告与精华</span>
                        <span className="text-[11px] font-normal text-amber-600/80 dark:text-amber-400/80">
                            ({stickyThreads.length})
                        </span>
                    </div>

                    <div className="divide-y divide-border/40">
                        {stickyThreads.map((thread) => (
                            <ThreadListItem key={thread.id} thread={thread} />
                        ))}
                    </div>
                </div>
            )}

            {/* 🎯 2. 普通主题分区 */}
            {normalThreads.length > 0 ? (
                <div>
                    {stickyThreads.length > 0 && (
                        <div className="flex items-center gap-2 px-4 py-2 bg-muted/40 border-b border-border/40 text-xs font-semibold text-muted-foreground">
                            <MessagesSquare className="w-3.5 h-3.5" />
                            <span>全部讨论主题</span>
                        </div>
                    )}

                    <div className="divide-y divide-border/40">
                        {normalThreads.map((thread) => (
                            <ThreadListItem key={thread.id} thread={thread} />
                        ))}
                    </div>
                </div>
            ) : (
                stickyThreads.length > 0 && (
                    <div className="p-6 text-center text-xs text-muted-foreground">
                        暂无更多常规讨论主题
                    </div>
                )
            )}
        </div>
    );
}
