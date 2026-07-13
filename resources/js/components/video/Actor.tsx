import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar";
import { Button } from "@/components/ui/button";
import { Link } from '@inertiajs/react'; // 引入 Inertia 的 Link 组件用于路由跳转

// 定义单名演员的数据结构
export interface ActorData {
    id: string | number;
    name: string;
    description?: string;
    avatar?: string;     // 圆形头像URL
    photo9x16?: string;  // 9:16全身照URL
    url?: string;        // 🌟 新增：演员详情页的跳转链接
}

interface ActorListProps {
    actors: ActorData[];
    variant?: 'circle' | 'fullbody';
}

export const Actor = ({ actors, variant = 'circle' }: ActorListProps) => {
    if (!actors || actors.length === 0) return null;

    return (
        <div className="mt-4">
            <h3 className="text-base font-semibold text-foreground mb-2 px-2">出镜演员</h3>

            {/* 🌟 核心改动 1：改为横向排列容器 */}
            {/* 使用 flex-row，允许横向滚动(overflow-x-auto)，隐藏滚动条(scrollbar-none)，并加入吸附效果(snap-x) */}
            <div className="flex flex-row gap-3 overflow-x-auto pb-2 px-2 scrollbar-none snap-x">

                {actors.map((actor) => (
                    <div
                        key={actor.id}
                        // 🌟 核心改动 2：卡片定宽且不压缩
                        // flex-shrink-0 防止卡片被挤压变形，设置合适的宽度 w-[240px]
                        className="flex-shrink-0 snap-start flex items-center justify-between p-2 pr-3 rounded-xl bg-muted/20 hover:bg-muted/50 transition-colors w-[230px] sm:w-[260px]"
                    >
                        {/* 🌟 核心改动 3：使用 Link 包裹头像和文字区域，实现跳转 */}
                        <Link
                            href={actor.url || '#'}
                            className="flex items-center gap-3 flex-1 min-w-0 group hover:opacity-85 transition-opacity"
                        >
                            {variant === 'circle' ? (
                                <Avatar className="h-10 w-10 bg-muted border-none shrink-0">
                                    <AvatarImage src={actor.avatar} alt={actor.name} className="object-cover" />
                                    <AvatarFallback className="bg-transparent font-medium">
                                        {actor.name.charAt(0)}
                                    </AvatarFallback>
                                </Avatar>
                            ) : (
                                <div className="relative w-10 aspect-[9/16] overflow-hidden rounded-md bg-muted shrink-0">
                                    <img
                                        src={actor.photo9x16}
                                        alt={actor.name}
                                        className="object-cover w-full h-full"
                                    />
                                </div>
                            )}

                            {/* 文字区域（超长文本自动省略号） */}
                            <div className="flex flex-col justify-center min-w-0 pr-2">
                                <h4 className="text-sm font-medium leading-none text-foreground mb-1.5 truncate">
                                    {actor.name}
                                </h4>
                                {actor.description && (
                                    <p className="text-xs text-muted-foreground truncate">
                                        {actor.description}
                                    </p>
                                )}
                            </div>
                        </Link>

                        {/* 右侧：关注按钮 (保持不变，但使用 shrink-0 确保不被文字挤压) */}
                        <Button
                            variant="secondary"
                            size="sm"
                            className="rounded-full px-3 h-7 text-xs font-medium bg-muted/80 hover:bg-muted text-foreground shrink-0"
                        >
                            关注
                        </Button>
                    </div>
                ))}
            </div>
        </div>
    );
};
