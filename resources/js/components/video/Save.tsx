import { Button } from "@/components/ui/button";
import { Bookmark } from "lucide-react";
import { Spinner } from '@/components/ui/spinner';
import { toast } from 'sonner';
import { useHttp } from '@inertiajs/react';
import React, { useState } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from "@/components/ui/tooltip";
import { useRequireAuth } from '@/components/require-auth-provider';
import VideoController from "@/actions/App/Http/Controllers/VideoController";

interface CollectResponse {
    status?: boolean;
    message: string;
    favorites_count?: number;
}

export function Save({ videoId, slug, initialIsCollect, initialFavoritesCount = 0 }: {
    videoId: number;
    slug: string;
    initialIsCollect: boolean;
    initialFavoritesCount?: number;
}) {
    const { post, processing } = useHttp();
    const [isCollect, setIsCollect] = useState(initialIsCollect);
    const [favoritesCount, setFavoritesCount] = useState(initialFavoritesCount);
    const { requireAuth } = useRequireAuth();

    const handleCollectToggle = () => {
        requireAuth(() => {
            if (processing) return;

            const prevCollect = isCollect;
            const prevCount = favoritesCount;

            // 乐观更新 UI
            if (isCollect) {
                setIsCollect(false);
                setFavoritesCount(prev => Math.max(0, prev - 1));
            } else {
                setIsCollect(true);
                setFavoritesCount(prev => prev + 1);
            }

            post(VideoController.collect.url({ video: videoId, slug: slug }), {
                onSuccess: (response: unknown) => {
                    const typedResponse = response as CollectResponse;
                    toast.success(typedResponse.message);
                    if (typeof typedResponse.favorites_count === 'number') {
                        setFavoritesCount(typedResponse.favorites_count);
                    }
                },
                onError: () => {
                    toast.error('刷新页面后重试');
                    setIsCollect(prevCollect);
                    setFavoritesCount(prevCount);
                },
                onNetworkError: () => {
                    toast.error('网络错误，请检查网络连接并重试');
                    setIsCollect(prevCollect);
                    setFavoritesCount(prevCount);
                }
            });
        });
    };

    const label = isCollect ? '已收藏' : '收藏';

    return (
        <>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        onClick={handleCollectToggle}
                        variant="secondary"
                        className="rounded-full px-4 h-9 shadow-none hover:bg-muted-foreground/10 gap-2"
                        disabled={processing}
                    >
                        {processing ? (
                            <Spinner />
                        ) : (
                            <Bookmark
                                className={`w-4 h-4 ${isCollect ? 'fill-current' : ''}`}
                            />
                        )}
                        <span className="text-sm font-medium">{label}</span>
                        {favoritesCount > 0 && (
                            <span className="text-sm font-semibold">{favoritesCount}</span>
                        )}
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">
                    <p>收藏</p>
                </TooltipContent>
            </Tooltip>
        </>
    );
}
