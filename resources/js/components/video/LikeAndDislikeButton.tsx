import { Button } from "@/components/ui/button";
import {
    ThumbsUp,
    ThumbsDown,
} from "lucide-react";
import { useHttp } from '@inertiajs/react';
import { subscribe } from '@/routes/channels';
import { Spinner } from '@/components/ui/spinner';
import { toast } from 'sonner';
import { like } from "@/routes/videos";
import React, { useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";

interface PostOptions {
    onSuccess?: (response: unknown) => void;
    onError?: (error: unknown) => void;
}

export function LikeAndDislikeButton({ videoId, slug, initialLiked, initialDisliked, initialLikeCount }: {
    videoId: number,
    slug: string,
    initialLiked: boolean,
    initialDisliked: boolean,
    initialLikeCount: number
}) {
    const [liked, setLiked] = useState(initialLiked);
    const [disLiked, setDisLiked] = useState(initialDisliked);
    const [likeCount, setLikeCount] = useState(initialLikeCount);
    const { post, processing } = useHttp()
    // 2. 处理点赞逻辑
    const handleLike = async () => {
        if (processing) return;

        // 记录操作前的状态，用于接口失败时回滚
        const prevLiked = liked;
        const prevDisliked = disLiked;
        const prevCount = likeCount;

        // 乐观更新 UI
        if (liked) {
            // 取消点赞
            setLiked(false);
            setLikeCount(prev => prev - 1);
        } else {
            // 新增点赞
            setLiked(true);
            setLikeCount(prev => prev + 1);
            if (disLiked) setDisLiked(false); // 互斥：如果处于踩的状态，则取消踩
        }
        post(`/videos/${videoId}/${slug}/like`, {
            onSuccess: (response: any) => {
                toast.success(response.message);
            },
            onError: () => {
                toast.error('刷新页面后重试');
                setLiked(prevLiked);
                setDisLiked(prevDisliked);
                setLikeCount(prevCount);
            },
            onNetworkError: () => {
                toast.error('网络错误，请检查网络连接并重试');
                setLiked(prevLiked);
                setDisLiked(prevDisliked);
                setLikeCount(prevCount);
            }
        });
    };

    // 3. 处理踩逻辑
    const handleDislike = async () => {
        if (processing) return;

        const prevLiked = liked;
        const prevDisliked = disLiked;
        const prevCount = likeCount;

        // 乐观更新 UI
        if (disLiked) {
            // 取消踩
            setDisLiked(false);
        } else {
            // 新增踩
            setDisLiked(true);
            if (liked) {
                // 互斥：如果处于点赞状态，则取消点赞并让数量减一
                setLiked(false);
                setLikeCount(prev => prev - 1);
            }
        }

        post(`/videos/${videoId}/${slug}/dislike`, {
            onSuccess: (response: any) => {
                toast.success(response.message);
            },
            onError: () => {
                toast.error('刷新页面后重试');
                setLiked(prevLiked);
                setDisLiked(prevDisliked);
                setLikeCount(prevCount);
            },
            onNetworkError: () => {
                toast.error('网络错误，请检查网络连接并重试');
                setLiked(prevLiked);
                setDisLiked(prevDisliked);
                setLikeCount(prevCount);
            }
        });
    };
    return (
        <>
            <div className="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800">

                {/* 点赞按钮 */}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            // 关键点：左边圆角，右边直角。Hover 时的背景色也要稍微加深
                            className="h-10 rounded-l-full rounded-r-none px-4 hover:bg-zinc-200 dark:hover:bg-zinc-700"
                            onClick={handleLike}
                            disabled={processing}
                        >
                            {/* 使用 fill="currentColor" 让图标变成实心，贴合图片中的状态 */}
                            <ThumbsUp className="mr-2 h-5 w-5" {...(liked ? { fill: "currentColor" } : {})} />
                            <span className="text-sm font-semibold">{likeCount}</span>

                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p>点赞</p>
                    </TooltipContent>
                </Tooltip>


                {/* 分隔线：满足“相隔的竖线的高度是按钮图标的高度而不是按钮的总高度” */}
                {/* h-5 与上方图标的 h-5 高度保持一致，w-[1px] 控制极细的宽度 */}
                <div className="h-5 w-px bg-zinc-300 dark:bg-zinc-600" />

                {/* 踩按钮 */}

                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            // 关键点：右边圆角，左边直角
                            className="h-10 rounded-r-full rounded-l-none px-4 hover:bg-zinc-200 dark:hover:bg-zinc-700"
                            onClick={handleDislike}
                            disabled={processing}
                        >
                            {/* 空心图标，不加 fill 属性 */}
                            <ThumbsDown className="h-5 w-5" {...(disLiked ? { fill: "currentColor" } : {})} />
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p>不喜欢</p>
                    </TooltipContent>
                </Tooltip>
            </div>
        </>
    );
};
