import React, { useState, useEffect } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import {
    Clock,
    Bookmark,
    EllipsisVertical,
    Loader2,
    AlarmClockPlus,
    AlarmClockMinus

} from "lucide-react";
import { Skeleton } from "@/components/ui/skeleton";
import { useHttp, usePage, router } from '@inertiajs/react';
import { toast } from 'sonner';

interface VideoStatusResponse {
    isSaveToWatchLater: boolean;
    isCollect: boolean;
}

interface ActionResponse {
    status: boolean;
    message: string;
}

export function StoreMenu({ videoId, slug }: { videoId: number, slug: string }) {
    const { auth } = usePage().props as any;
    const user = auth?.user;

    const [isOpen, setIsOpen] = useState(false);

    const [isWatchLater, setIsWatchLater] = useState(false);
    const [isFavorited, setIsFavorited] = useState(false);

    const [isGetting, setIsGetting] = useState(false);
    const [isWatchLaterProcessing, setIsWatchLaterProcessing] = useState(false);
    const [isFavoriteProcessing, setIsFavoriteProcessing] = useState(false);

    const { get, post } = useHttp();

    useEffect(() => {
        if (isOpen && user) {
            setIsGetting(true);
            get(`/videos/${videoId}/${slug}/menu-status`, {
                onSuccess: (response: unknown) => {
                    const res = response as VideoStatusResponse;
                    setIsWatchLater(res.isSaveToWatchLater);
                    setIsFavorited(res.isCollect);
                },
                onError: () => {
                    toast.error('获取状态失败，请刷新重试');
                },
                onFinish: () => {
                    setIsGetting(false);
                }
            });
        }
    }, [isOpen, videoId, slug, user]);

    const checkLoginOrPrompt = () => {
        if (!user) {
            toast('需要登录', {
                description: '请先登录后再进行此操作',
                action: {
                    label: '去登录',
                    onClick: () => router.visit('/login'),
                },
                duration: 15000,
            });
            setIsOpen(false);
            return false;
        }
        return true;
    };

    const handleToggleWatchLater = () => {
        if (!checkLoginOrPrompt()) return;
        if (isWatchLaterProcessing) return;

        setIsWatchLaterProcessing(true);

        post(`/videos/${videoId}/${slug}/watch-later`, {
            onSuccess: (response: unknown) => {
                const res = response as ActionResponse;
                toast.success(res.message);
                setIsWatchLater(res.status);
            },
            onError: () => {
                toast.error('操作失败，请重试');
            },
            onFinish: () => {
                setIsWatchLaterProcessing(false);
            }
        });
    };

    const handleToggleFavorite = () => {
        if (!checkLoginOrPrompt()) return;
        if (isFavoriteProcessing) return;

        setIsFavoriteProcessing(true);

        post(`/videos/${videoId}/${slug}/collect`, {
            onSuccess: (response: unknown) => {
                const res = response as ActionResponse;
                toast.success(res.message);
                setIsFavorited(res.status);
            },
            onError: () => {
                toast.error('操作失败，请重试');
            },
            onFinish: () => {
                setIsFavoriteProcessing(false);
            }
        });
    };

    return (
        <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
            <DropdownMenuTrigger asChild>
                <button
                    className="p-1.5 hover:bg-muted rounded-full transition-colors outline-none data-[state=open]:bg-muted"
                    onClick={(e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    }}
                >
                    <EllipsisVertical className="w-5 h-5 text-primary" />
                </button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-56 rounded-xl shadow-lg p-2" onClick={(e) => e.stopPropagation()}>
                {isGetting ? (
                    <div className="flex flex-col gap-2 p-1">
                        <Skeleton className="h-7.5 w-full rounded-lg bg-muted/60" />
                        <Skeleton className="h-7.5 w-full rounded-lg bg-muted/60" />
                    </div>
                ) : (
                    <>
                        <DropdownMenuItem
                            className="gap-3 py-2 cursor-pointer text-[15px] rounded-lg"
                            onSelect={(e) => {
                                e.preventDefault();
                                handleToggleWatchLater();
                            }}
                        >
                            {isWatchLaterProcessing ? (
                                <Loader2 className="w-5 h-5 animate-spin text-muted-foreground" />
                            ) : isWatchLater ? (
                                <AlarmClockMinus className="w-5 h-5" />
                            ) : (
                                <AlarmClockPlus className="w-5 h-5" />
                            )}
                            {isWatchLater ? '从“稍后观看”中移除' : '保存到“稍后观看”'}
                        </DropdownMenuItem>

                        <DropdownMenuItem
                            className="gap-3 py-2 cursor-pointer text-[15px] rounded-lg"
                            onSelect={(e) => {
                                e.preventDefault();
                                handleToggleFavorite();
                            }}
                        >
                            {isFavoriteProcessing ? (
                                <Loader2 className="w-5 h-5 animate-spin text-muted-foreground" />
                            ) : (
                                /* 🎯 修改点：通过判断 isFavorited 动态设置 fill 属性 */
                                <Bookmark
                                    className="w-5 h-5"
                                    fill={isFavorited ? "currentColor" : "none"}
                                />
                            )}
                            {isFavorited ? '取消收藏' : '保存到收藏夹'}
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
