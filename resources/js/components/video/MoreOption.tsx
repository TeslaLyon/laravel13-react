import React, { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    BottomSheet,
    BottomSheetContent,
    BottomSheetItem,
} from "@/components/ui/bottom-sheet";
import { useIsMobile } from "@/hooks/use-mobile";
import {
    MoreHorizontal,
    MessageSquareWarning,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { FeedbackDialog } from "@/components/video/FeedbackDialog";
import { Video } from "@/types/video";
import { useRequireAuth } from '@/components/require-auth-provider';

// 1. 接收 video 数据，以便传递给反馈组件
export function MoreOption({ video }: { video?: Video }) {
    const isMobile = useIsMobile();
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    // 2. 新增控制反馈弹窗的状态
    const [isFeedbackOpen, setIsFeedbackOpen] = useState(false);

    const { requireAuth } = useRequireAuth();

    const handleFeedbackClick = () => {
        setIsMenuOpen(false);
        requireAuth(() => {
            setIsFeedbackOpen(true);
        });
    };

    return (
        <>
            {isMobile ? (
                <>
                    <Button
                        type="button"
                        variant="secondary"
                        size="icon"
                        onClick={() => setIsMenuOpen(true)}
                        className="rounded-full h-9 w-9 shrink-0 bg-secondary text-secondary-foreground hover:bg-secondary/80 cursor-pointer"
                    >
                        <MoreHorizontal className="w-4 h-4" />
                    </Button>

                    <BottomSheet open={isMenuOpen} onOpenChange={setIsMenuOpen}>
                        <BottomSheetContent>
                            <div className="flex flex-col py-1">
                                <BottomSheetItem
                                    icon={<MessageSquareWarning className="w-5 h-5" />}
                                    onClick={handleFeedbackClick}
                                >
                                    反馈
                                </BottomSheetItem>
                            </div>
                        </BottomSheetContent>
                    </BottomSheet>
                </>
            ) : (
                <DropdownMenu open={isMenuOpen} onOpenChange={setIsMenuOpen}>
                    <DropdownMenuTrigger asChild>
                        <Button
                            variant="secondary"
                            size="icon"
                            className="rounded-full h-9 w-9 shrink-0 bg-secondary text-secondary-foreground hover:bg-secondary/80 cursor-pointer"
                        >
                            <MoreHorizontal className="w-4 h-4" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent align="end" className="w-40 rounded-xl shadow-lg">
                        <DropdownMenuItem
                            className="gap-3 py-2 cursor-pointer text-[15px]"
                            onSelect={handleFeedbackClick}
                        >
                            <MessageSquareWarning className="w-5 h-5" />
                            反馈
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            {/* 3. 将 FeedbackDialog 放置在菜单外，通过属性控制它 */}
            <FeedbackDialog
                modelType="video"      // 声明当前模块是 video
                modelId={video?.id}    // 传入对应的实体 ID
                open={isFeedbackOpen}
                onOpenChange={setIsFeedbackOpen}
            />
        </>
    );
}
