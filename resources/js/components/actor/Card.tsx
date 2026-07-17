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
import { useHttp, Link } from '@inertiajs/react';
import { toast } from 'sonner';
import { ResponsiveImage } from "@/components/ui/responsive-image";
import { Actor } from "@/types/actor";
import { menuStatus } from '@/actions/App/Http/Controllers/ActorController';
import { getCardHoverColor } from "@/lib/utils";

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

// 模拟的特殊属性标签（实际开发中可以从 actor.tags 中获取）
const actorTags = [
    { label: "热门", style: "bg-orange-500/15 text-orange-600 dark:text-orange-400" },
    { label: "新人", style: "bg-green-500/15 text-green-600 dark:text-green-400" },
    { label: "高分", style: "bg-blue-500/15 text-blue-600 dark:text-blue-400" }
];

export default function ActorCard({ actor }: { actor: Actor }) {
    const hoverBgStyle = getCardHoverColor(actor.id);
    const [isOpen, setIsOpen] = useState(false);
    const [isFollowing, setIsFollowing] = useState(false);
    const { get, processing: isGetting } = useHttp();
    const { post, processing: isPosting } = useHttp();

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
                        toast.error('刷新页面后重试');
                    },
                    onFinish: () => { }
                });
            };

            fetchFollowStatus();
        }
    }, [isOpen, actor.id]);

    const handleToggleFollow = async () => {
        if (isPosting) return;
        post(`/actors/${actor.id}/${actor.slug}/follow`, {
            onSuccess: (response: unknown) => {
                const subscribeResponse = response as SubscribeResponse;
                toast.success(subscribeResponse.message);
                setIsFollowing(subscribeResponse.status);
            },
            onError: () => {
                toast.error('刷新页面后重试');
            },
            onFinish: () => { }
        });
    };

    return (
        <div className="group relative flex flex-col gap-1 cursor-pointer z-0 w-full mt-3">
            {/* 核心层：向外扩张的绝对定位背景框 */}
            <div className={`absolute -inset-3 rounded-2xl border border-transparent opacity-0 transition-opacity duration-200 group-hover:opacity-100 -z-10 ${hoverBgStyle}`}></div>

            <div className="relative w-full aspect-[9/16] rounded-xl overflow-hidden mb-1 bg-muted">
                <ResponsiveImage
                    images={actor.booty_img}
                    alt={`${actor.name} profile picture`}
                    className="block w-full h-full object-cover object-top transition-transform duration-300 group-hover:scale-105"
                />
            </div>

            {/* 2. 底部信息区域 - 优化间距：使用 gap-2.5 统一模块间的距离 */}
            <div className="flex flex-col px-1 mt-2 gap-2.5">

                {/* 标题与菜单组合行 */}
                <div className="flex items-center justify-between gap-2 w-full min-h-[32px]">
                    <h3 className="text-[16px] font-semibold leading-tight line-clamp-1 text-foreground group-hover:text-primary transition-colors flex-1 min-w-0">
                        {actor.name}
                    </h3>

                    <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 shrink-0 text-muted-foreground hover:text-foreground hover:bg-foreground/10 data-[state=open]:bg-foreground/10 opacity-0 group-hover:opacity-100 data-[state=open]:opacity-100 transition-all rounded-full shadow-none"
                                onClick={(e) => e.stopPropagation()}
                            >
                                <MoreVertical className="w-5 h-5" />
                            </Button>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            align="end"
                            className="w-64 rounded-xl shadow-lg p-2"
                            onClick={(e) => e.stopPropagation()}
                        >
                            {isGetting ? (
                                <div className="px-1 py-0.5">
                                    <Skeleton className="h-9 w-full rounded-lg" />
                                </div>
                            ) : (
                                <DropdownMenuItem
                                    className="gap-3 py-2.5 cursor-pointer rounded-lg text-foreground hover:bg-muted"
                                    onSelect={(e) => {
                                        e.preventDefault();
                                        handleToggleFollow();
                                    }}
                                >
                                    {isPosting ? (
                                        <>
                                            <Loader2 className="w-[18px] h-[18px] animate-spin text-muted-foreground" />
                                            <span className="text-[15px]">处理中...</span>
                                        </>
                                    ) : isFollowing ? (
                                        <>
                                            <UserMinus className="w-[18px] h-[18px] text-muted-foreground" />
                                            <span className="text-[15px]">取消关注</span>
                                        </>
                                    ) : (
                                        <>
                                            <UserPlus className="w-[18px] h-[18px]" />
                                            <span className="text-[15px]">关注</span>
                                        </>
                                    )}
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                {/* 3. 新增的颜色标签模块 */}
                <div className="flex flex-wrap items-center gap-1.5">
                    {actorTags.map((tag, idx) => (
                        <span
                            key={idx}
                            className={`px-2 py-0.5 rounded-md text-[11px] font-medium tracking-wide ${tag.style}`}
                        >
                            {tag.label}
                        </span>
                    ))}
                </div>

                {/* 1. 底部三个数据指标项 - 改为了 Inertia Link 按钮 */}
                <div className="flex items-center gap-2 justify-between w-full mt-1">
                    <Link
                        href={`/actors/${actor.id}/videos`}
                        className="flex-1 flex justify-center items-center gap-1.5 px-2 py-1.5 rounded-lg bg-muted/70 hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200 group/btn"
                    >
                        <Clapperboard className="w-4 h-4 group-hover/btn:scale-110 transition-transform" />
                        <span className="text-xs font-semibold">{878}</span>
                    </Link>

                    <Link
                        href={`/actors/${actor.id}/images`}
                        className="flex-1 flex justify-center items-center gap-1.5 px-2 py-1.5 rounded-lg bg-muted/70 hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200 group/btn"
                    >
                        <ImageIcon className="w-4 h-4 group-hover/btn:scale-110 transition-transform" />
                        <span className="text-xs font-semibold">{56}</span>
                    </Link>

                    <Link
                        href={`/actors/${actor.id}/links`}
                        className="flex-1 flex justify-center items-center gap-1.5 px-2 py-1.5 rounded-lg bg-muted/70 hover:bg-primary hover:text-primary-foreground text-muted-foreground transition-all duration-200 group/btn"
                    >
                        <Link2 className="w-4 h-4 group-hover/btn:scale-110 transition-transform" />
                        <span className="text-xs font-semibold">{43}</span>
                    </Link>
                </div>

            </div>
        </div>
    );
}
