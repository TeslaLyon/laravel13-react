import React, { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    ListVideo,
    Clock,
    Bookmark,
    Download,
    Share,
    Ban,
    MinusCircle,
    Flag,
    MoreHorizontal,
    MessageSquareWarning,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { FeedbackDialog } from "@/components/video/FeedbackDialog";
import { Video } from "@/types/video";
import { useRequireAuth } from '@/components/require-auth-provider';

// 1. 接收 video 数据，以便传递给反馈组件
export function MoreOption({ video }: { video?: Video }) {
    // 2. 新增控制反馈弹窗的状态
    const [isFeedbackOpen, setIsFeedbackOpen] = useState(false);

    const { requireAuth } = useRequireAuth();

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    {/* asChild 允许使用自定义按钮，阻止冒泡防止跳转 */}
                    <Button variant="secondary" size="icon" className="rounded-full ">
                        <MoreHorizontal className="w-4 h-4" />
                    </Button>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="end" className="w-40 rounded-xl shadow-lg">
                    {/* 列表项 1：点击时打开反馈弹窗 */}
                    <DropdownMenuItem
                        className="gap-3 py-2 cursor-pointer text-[15px]"
                        onSelect={() => {
                            requireAuth(() => {
                                setIsFeedbackOpen(true);
                            });
                        }}
                    >
                        <MessageSquareWarning className="w-5 h-5" />
                        反馈
                    </DropdownMenuItem>

                    {/* <DropdownMenuItem className="gap-3 py-2 cursor-pointer text-[15px]">
                        <Clock className="w-5 h-5" />
                        保存到“稍后观看”
                    </DropdownMenuItem>
                    ... 保留你原本注释掉的其他代码 ... */}
                </DropdownMenuContent>
            </DropdownMenu>

            {/* 3. 将 FeedbackDialog 放置在下拉菜单外，通过属性控制它 */}
            <FeedbackDialog
                modelType="video"      // 声明当前模块是 video
                modelId={video?.id}    // 传入对应的实体 ID
                open={isFeedbackOpen}
                onOpenChange={setIsFeedbackOpen}
            />
        </>
    );
}
