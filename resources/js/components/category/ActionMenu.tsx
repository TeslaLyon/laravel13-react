import React, { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { MoreVertical, Loader2, UserMinus, UserPlus } from "lucide-react";

export interface CategoryActionMenuProps {
    targetId: number | string;
    isFollowing?: boolean;
    isGetting?: boolean;
    isPosting?: boolean;
    onToggleFollow?: (targetId: number | string) => void | Promise<void>;
    onOpenChange?: (open: boolean) => void;
}

export default function CategoryActionMenu({
    targetId,
    isFollowing = false,
    isGetting = false,
    isPosting = false,
    onToggleFollow,
    onOpenChange,
}: CategoryActionMenuProps) {
    const [isOpen, setIsOpen] = useState(false);

    const handleOpenChange = (open: boolean) => {
        setIsOpen(open);
        if (onOpenChange) {
            onOpenChange(open);
        }
    };

    const handleFollowClick = () => {
        if (onToggleFollow && !isPosting) {
            onToggleFollow(targetId);
        }
    };

    return (
        <DropdownMenu open={isOpen} onOpenChange={handleOpenChange}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    /* 🎯 优化后的 Light 主题高质感毛玻璃样式 */
                    className="h-8 w-8 shrink-0 rounded-full border border-white/60 dark:border-white/10 bg-white/70 hover:bg-white dark:bg-black/50 dark:hover:bg-black/70 text-slate-700 hover:text-slate-900 dark:text-slate-200 backdrop-blur-md shadow-sm data-[state=open]:bg-white dark:data-[state=open]:bg-black/80 data-[state=open]:text-slate-900 transition-all duration-200"
                    onClick={(e) => e.stopPropagation()}
                >
                    <MoreVertical className="w-4 h-4" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                className="w-56 rounded-xl shadow-xl p-1.5 border border-border/50 bg-popover/95 backdrop-blur-md"
                onClick={(e) => e.stopPropagation()}
            >
                {isGetting ? (
                    <div className="px-1 py-0.5">
                        <Skeleton className="h-9 w-full rounded-lg bg-muted/60" />
                    </div>
                ) : (
                    <DropdownMenuItem
                        className="gap-3 py-2 cursor-pointer rounded-lg text-foreground hover:bg-accent transition-colors"
                        onSelect={(e) => {
                            e.preventDefault();
                            handleFollowClick();
                        }}
                    >
                        {isPosting ? (
                            <>
                                <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />
                                <span className="text-sm">处理中...</span>
                            </>
                        ) : isFollowing ? (
                            <>
                                <UserMinus className="w-4 h-4 text-muted-foreground" />
                                <span className="text-sm">取消关注</span>
                            </>
                        ) : (
                            <>
                                <UserPlus className="w-4 h-4 text-primary" />
                                <span className="text-sm font-medium">关注</span>
                            </>
                        )}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
