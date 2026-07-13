import React from 'react';
import { Button } from "@/components/ui/button";
import { Link } from '@inertiajs/react';
import ImageWithPlaceholder from "@/components/ImageWithPlaceholder";

export interface ActorGridData {
    id: string | number;
    name: string;
    description?: string;
    photo9x16: string;
    url?: string;
}

interface ActorGridProps {
    actors: ActorGridData[];
}

export const ActorFullBody = ({ actors }: ActorGridProps) => {
    if (!actors || actors.length === 0) return null;

    return (
        <div className="mt-6">
            <h3 className="text-base font-semibold text-foreground mb-4 px-1">出镜演员</h3>

            {/* 🌟 核心变化：使用 CSS Grid 网格布局 */}
            {/* 手机端一行 2 个，平板一行 3 个，桌面端一行 4 个或 5 个，gap-4 控制间距 */}
            <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-1 px-1">

                {actors.map((actor) => (
                    // 卡片容器：不再需要固定宽度，宽度由网格系统自动分配
                    <div
                        key={actor.id}
                        className="flex flex-col group w-full bg-muted/10 p-2 rounded-xl border border-transparent hover:border-border/50 hover:bg-muted/30 transition-all"
                    >
                        {/* 路由跳转区域 */}
                        <Link
                            href={actor.url || '#'}
                            className="flex flex-col gap-2"
                        >
                            {/* 9:16 图片区域：w-full 让它充满当前网格 */}
                            <div className="relative w-full aspect-[9/16] overflow-hidden rounded-lg bg-muted border border-border/50">
                                <img
                                    src={actor.photo9x16}
                                    alt={actor.name}
                                    className="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500"
                                />
                                {/* <ImageWithPlaceholder
                                    src={actor.photo9x16}
                                    alt={actor.name}
                                    className="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500"
                                /> */}
                            </div>

                            {/* 文字区域 */}
                            <div className="flex flex-col px-1 mt-1">
                                <h4 className="text-sm sm:text-base font-semibold leading-tight text-foreground truncate mb-1">
                                    {actor.name}
                                </h4>
                                {actor.description && (
                                    <p className="text-xs text-muted-foreground line-clamp-2">
                                        {actor.description}
                                    </p>
                                )}
                            </div>
                        </Link>

                        {/* 关注按钮：利用 mt-auto 将按钮推到卡片最底部，保证所有卡片按钮对齐 */}
                        <div className="mt-auto pt-3">
                            <Button
                                variant="secondary"
                                size="sm"
                                className="w-full rounded-full text-xs font-medium bg-muted hover:bg-muted-foreground/10 text-foreground"
                            >
                                关注
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};
