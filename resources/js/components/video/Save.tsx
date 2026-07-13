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

interface CollectResponse {
    message: string;
}

export function Save({ videoId, slug, initialIsCollect }: { videoId: number, slug: string, initialIsCollect: boolean }) {
    const { post, processing } = useHttp()
    const [isCollect, setIsCollect] = useState(initialIsCollect);
    const handleCollectToggle = () => {
        if (processing) return;
        post(`/videos/${videoId}/${slug}/collect`, {
            onSuccess: (response: unknown) => {
                const typedResponse = response as CollectResponse;
                toast.success(typedResponse.message);
                setIsCollect(!isCollect);
            },
            onError: () => {
                toast.error('刷新页面后重试');
            }
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
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">
                    <p>收藏</p>
                </TooltipContent>
            </Tooltip>
        </>
    );
}
