import { Button } from "@/components/ui/button";
import {
    ThumbsUp,
    ThumbsDown,
    Music,
} from "lucide-react";
import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import React, { useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
// 1. 引入全局自定义 Hook
import { useRequireAuth } from '@/components/require-auth-provider';

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
    const [isAnimating, setIsAnimating] = useState(false);

    const { post, processing } = useHttp();

    // 2. 获取全局拦截方法
    const { requireAuth } = useRequireAuth();

    // 3. 处理点赞逻辑
    const handleLike = () => {
        // 使用 requireAuth 包裹真实业务逻辑即可！
        requireAuth(() => {
            if (processing) return;

            const prevLiked = liked;
            const prevDisliked = disLiked;
            const prevCount = likeCount;

            if (liked) {
                setLiked(false);
                setLikeCount(prev => prev - 1);
            } else {
                setLiked(true);
                setLikeCount(prev => prev + 1);

                setIsAnimating(true);
                setTimeout(() => {
                    setIsAnimating(false);
                }, 1000);

                if (disLiked) setDisLiked(false);
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
                    setIsAnimating(false);
                },
                onNetworkError: () => {
                    toast.error('网络错误，请检查网络连接并重试');
                    setLiked(prevLiked);
                    setDisLiked(prevDisliked);
                    setLikeCount(prevCount);
                    setIsAnimating(false);
                }
            });
        });
    };

    // 4. 处理踩逻辑
    const handleDislike = () => {
        // 同样用 requireAuth 一行包裹！
        requireAuth(() => {
            if (processing) return;

            const prevLiked = liked;
            const prevDisliked = disLiked;
            const prevCount = likeCount;

            if (disLiked) {
                setDisLiked(false);
            } else {
                setDisLiked(true);
                if (liked) {
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
        });
    };

    return (
        <>
            <style>{`
                @keyframes float-1 {
                    0% { opacity: 1; transform: translate(0, 0) scale(1) rotate(0deg); }
                    100% { opacity: 0; transform: translate(-24px, -24px) scale(0.5) rotate(-30deg); }
                }
                @keyframes float-2 {
                    0% { opacity: 1; transform: translate(0, 0) scale(1) rotate(0deg); }
                    100% { opacity: 0; transform: translate(24px, -16px) scale(0.6) rotate(20deg); }
                }
                @keyframes float-3 {
                    0% { opacity: 1; transform: translate(0, 0) scale(1) rotate(0deg); }
                    100% { opacity: 0; transform: translate(-10px, 20px) scale(0.4) rotate(-15deg); }
                }
                .animate-float-1 { animation: float-1 0.8s ease-out forwards; }
                .animate-float-2 { animation: float-2 0.7s ease-out forwards; }
                .animate-float-3 { animation: float-3 0.9s ease-out forwards; }
                .bounce-in { animation: bounce 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
                @keyframes bounce {
                    0% { transform: scale(0.5); }
                    100% { transform: scale(1); }
                }
            `}</style>

            <div className="inline-flex items-center rounded-full bg-zinc-100 dark:bg-zinc-800">
                {/* 点赞按钮 */}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            className="h-10 rounded-l-full rounded-r-none px-4 hover:bg-zinc-200 dark:hover:bg-zinc-700 relative overflow-visible"
                            onClick={handleLike}
                            disabled={processing}
                        >
                            <div className="relative flex items-center justify-center mr-2 h-5 w-5">
                                {isAnimating ? (
                                    <>
                                        <Music className="h-5 w-5 fill-current bounce-in text-foreground" />
                                        <Music className="absolute h-3 w-3 text-pink-500 fill-current animate-float-1" />
                                        <Music className="absolute h-2 w-2 text-purple-500 fill-current animate-float-2" />
                                        <Music className="absolute h-3 w-3 text-red-500 fill-current animate-float-3" />
                                    </>
                                ) : (
                                    <ThumbsUp
                                        className={`h-5 w-5 transition-transform duration-200 ${liked ? 'scale-110' : 'scale-100'}`}
                                        {...(liked ? { fill: "currentColor" } : {})}
                                    />
                                )}
                            </div>

                            {likeCount > 0 && <span className="text-sm font-semibold">{likeCount}</span>}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">
                        <p>点赞</p>
                    </TooltipContent>
                </Tooltip>

                {/* 分隔线 */}
                <div className="h-5 w-px bg-zinc-300 dark:bg-zinc-600" />

                {/* 踩按钮 */}
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            variant="ghost"
                            className="h-10 rounded-r-full rounded-l-none px-4 hover:bg-zinc-200 dark:hover:bg-zinc-700"
                            onClick={handleDislike}
                            disabled={processing}
                        >
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
}
