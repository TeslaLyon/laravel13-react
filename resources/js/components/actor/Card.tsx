import React, { useState, useEffect } from 'react';
import {
    MoreVertical,
    Clapperboard,
    Image as ImageIcon,
    Link2,
    UserPlus,
    UserMinus,
    Loader2
} from "lucide-react";
import { Button } from "@/components/ui/button";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Skeleton } from "@/components/ui/skeleton";
import { useHttp, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { ResponsiveImage } from "@/components/ui/responsive-image";
import { Actor } from "@/types/actor";
import { menuStatus } from '@/actions/App/Http/Controllers/ActorController';
import { getCardHoverColor } from "@/lib/utils";
import ActorController from '@/actions/App/Http/Controllers/ActorController';
// 1. 引入未登录检测与拦截 Hook
import { useRequireAuth } from '@/components/require-auth-provider';

export interface StudioItem {
    id: string;
    name: string;
    logo: string;
    videoCount: string | number;
    imageCount: string | number;
    linkCount: string | number;
}

interface SubscribeResponse {
    status: boolean;
    message: string;
}

export default function ActorCard({ actor }: { actor: Actor }) {
    const hoverBgStyle = getCardHoverColor(actor.id);
    const [isOpen, setIsOpen] = useState(false);
    const [isFollowing, setIsFollowing] = useState(false);
    const { get, processing: isGetting } = useHttp();
    const { post, processing: isPosting } = useHttp();

    // 2. 初始化登录鉴权方法
    const { requireAuth } = useRequireAuth();

    // 动态特性标签
    const tags = (actor as unknown as { tags?: Array<{ label: string; style: string }> }).tags || [
        { label: "热门", style: "bg-orange-500/15 text-orange-600 dark:text-orange-400" },
        { label: "高分", style: "bg-blue-500/15 text-blue-600 dark:text-blue-400" }
    ];

    useEffect(() => {
        if (isOpen) {
            const fetchFollowStatus = async () => {
                if (isGetting) return;
                get(menuStatus.url({ actor: actor.id, slug: actor.slug }), {
                    onSuccess: (response: unknown) => {
                        const subscribeResponse = response as SubscribeResponse;
                        setIsFollowing(subscribeResponse.status);
                    },
                    onError: () => {
                        toast.error('获取关注状态失败，请刷新后重试');
                    }
                });
            };

            fetchFollowStatus();
        }
    }, [isOpen, actor.id, actor.slug]);

    // 3. 拦截菜单展开动作：未登录用户点击菜单按钮时，阻止菜单打开和后续的查询请求
    const handleOpenChange = (open: boolean) => {
        if (open) {
            requireAuth(() => {
                setIsOpen(true);
            });
        } else {
            setIsOpen(false);
        }
    };

    // 4. 拦截关注/取消关注网络请求
    const handleToggleFollow = async (e?: React.MouseEvent) => {
        if (e) e.stopPropagation();

        // 使用 requireAuth 包裹 post 网络请求
        requireAuth(() => {
            if (isPosting) return;
            post(`/actors/${actor.id}/${actor.slug}/follow`, {
                onSuccess: (response: unknown) => {
                    const subscribeResponse = response as SubscribeResponse;
                    toast.success(subscribeResponse.message);
                    setIsFollowing(subscribeResponse.status);
                },
                onError: () => {
                    toast.error('操作失败，请刷新页面重试');
                }
            });
        });
    };

    // 拦截指标按钮点击，阻止事件冒泡并跳转
    const handleMetricClick = (e: React.MouseEvent, tab: string) => {
        e.preventDefault();
        e.stopPropagation();
        router.get(ActorController.show.url({ actor: actor.id, slug: actor.slug, tab }));
    };

    return (
        <div className="group relative flex flex-col gap-1.5 cursor-pointer z-0 w-full mt-2">
            {/* 核心悬浮背景框 */}
            <div className={`absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100 -z-10 ${hoverBgStyle}`} />

            {/* 封面图 */}
            <div className="relative w-full aspect-[9/16] rounded-xl overflow-hidden mb-1 bg-muted">
                <ResponsiveImage
                    images={actor.booty_img}
                    alt={`${actor.name} 封面图`}
                    className="block w-full h-full object-cover object-top transition-transform duration-500 group-hover:scale-105"
                />
            </div>

            {/* 信息模块 */}
            <div className="flex flex-col px-1 mt-1 gap-2.5">

                {/* 演员姓名与更多操作菜单 */}
                <div className="flex items-center justify-between gap-2 w-full min-h-[36px]">
                    <h3 className="text-base md:text-[17px] font-bold leading-snug line-clamp-1 text-foreground group-hover:text-primary transition-colors flex-1 min-w-0">
                        {actor.name}
                    </h3>

                    {/* 使用 handleOpenChange 替换原来的 setIsOpen */}
                    <DropdownMenu open={isOpen} onOpenChange={handleOpenChange}>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 shrink-0 text-muted-foreground hover:text-foreground hover:bg-foreground/10 data-[state=open]:bg-foreground/10 opacity-0 group-hover:opacity-100 data-[state=open]:opacity-100 transition-all rounded-full shadow-none"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <MoreVertical className="w-4.5 h-4.5" />
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            align="end"
                            className="w-48 rounded-xl shadow-lg p-1.5"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {isGetting ? (
                                <div className="p-1">
                                    <Skeleton className="h-9 w-full rounded-lg" />
                                </div>
                            ) : (
                                <DropdownMenuItem
                                    className="gap-2.5 py-2.5 cursor-pointer rounded-lg text-foreground hover:bg-muted"
                                    onSelect={(e) => {
                                        e.preventDefault();
                                        handleToggleFollow();
                                    }}
                                >
                                    {isPosting ? (
                                        <>
                                            <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />
                                            <span className="text-sm">处理中...</span>
                                        </>
                                    ) : isFollowing ? (
                                        <>
                                            <UserMinus className="w-4 h-4 text-destructive" />
                                            <span className="text-sm text-destructive font-medium">取消关注</span>
                                        </>
                                    ) : (
                                        <>
                                            <UserPlus className="w-4 h-4 text-primary" />
                                            <span className="text-sm font-medium">关注演员</span>
                                        </>
                                    )}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* 特性标签 */}
                <div className="flex flex-wrap items-center gap-1.5 min-h-[24px]">
                    {tags.map((tag, idx) => (
                        <span
                            key={idx}
                            className={`px-2.5 py-0.5 rounded-md text-xs font-semibold tracking-wide ${tag.style}`}
                        >
                            {tag.label}
                        </span>
                    ))}
                </div>

                {/* 数据指标栏 */}
                <div className="flex items-center gap-2 justify-between w-full mt-1">
                    <button
                        type="button"
                        onClick={(e) => handleMetricClick(e, 'videos')}
                        className="flex-1 flex justify-center items-center gap-1.5 px-2.5 py-2 rounded-lg bg-muted/60 hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200 group/btn cursor-pointer"
                        title="查看视频作品"
                    >
                        <Clapperboard className="w-4 h-4 group-hover/btn:scale-110 transition-transform" />
                        <span className="text-xs sm:text-sm font-semibold">
                            {(actor as unknown as { videos_count?: number }).videos_count ?? 0}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={(e) => handleMetricClick(e, 'images')}
                        className="flex-1 flex justify-center items-center gap-1.5 px-2.5 py-2 rounded-lg bg-muted/60 hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200 group/btn cursor-pointer"
                        title="查看图片相册"
                    >
                        <ImageIcon className="w-4 h-4 group-hover/btn:scale-110 transition-transform" />
                        <span className="text-xs sm:text-sm font-semibold">
                            {(actor as unknown as { images_count?: number }).images_count ?? 0}
                        </span>
                    </button>

                    <button
                        type="button"
                        onClick={(e) => handleMetricClick(e, 'links')}
                        className="flex-1 flex justify-center items-center gap-1.5 px-2.5 py-2 rounded-lg bg-muted/60 hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200 group/btn cursor-pointer"
                        title="查看相关链接"
                    >
                        <Link2 className="w-4 h-4 group-hover/btn:scale-110 transition-transform" />
                        <span className="text-xs sm:text-sm font-semibold">
                            {(actor as unknown as { links_count?: number }).links_count ?? 0}
                        </span>
                    </button>
                </div>

            </div>
        </div>
    );
}