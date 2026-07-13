import React, { useRef, useState } from 'react';
import { Button } from "@/components/ui/button";
import { Link } from '@inertiajs/react';

export interface ActorPosterData {
    id: string | number;
    name: string;
    description?: string;
    photo9x16: string;
    url?: string;
}

interface ActorPosterProps {
    actors: ActorPosterData[];
}

export const ActorPoster = ({ actors }: ActorPosterProps) => {
    // 1. 用于鼠标拖拽滑动的状态和 Ref
    const scrollRef = useRef<HTMLDivElement>(null);
    const [isDragging, setIsDragging] = useState(false);
    const [dragDistance, setDragDistance] = useState(0); // 记录拖拽距离，用于区分是“点击”还是“滑动”
    const startPos = useRef({ x: 0, scrollLeft: 0 });

    // 2. 鼠标事件处理函数
    const handleMouseDown = (e: React.MouseEvent) => {
        if (!scrollRef.current) return;
        setIsDragging(true);
        setDragDistance(0); // 每次按下重置距离
        startPos.current = {
            x: e.pageX - scrollRef.current.offsetLeft,
            scrollLeft: scrollRef.current.scrollLeft
        };
    };

    const handleMouseUpOrLeave = () => {
        setIsDragging(false);
    };

    const handleMouseMove = (e: React.MouseEvent) => {
        if (!isDragging || !scrollRef.current) return;
        e.preventDefault(); // 防止拖拽时选中文本

        const x = e.pageX - scrollRef.current.offsetLeft;
        const walk = (x - startPos.current.x) * 1.5; // 1.5 是滑动速度倍率，可自行调整

        scrollRef.current.scrollLeft = startPos.current.scrollLeft - walk;
        setDragDistance(Math.abs(walk));
    };

    // 3. 防止滑动时意外触发 Link 的跳转
    const handleLinkClick = (e: React.MouseEvent) => {
        if (dragDistance > 5) {
            e.preventDefault(); // 如果鼠标移动超过 5px，认为是滑动，阻止跳转
        }
    };

    if (!actors || actors.length === 0) return null;

    return (
        <div className="mt-6">
            <h3 className="text-base font-semibold text-foreground mb-3 px-2">出镜演员</h3>

            {/* 滑动容器 */}
            <div
                ref={scrollRef}
                className="flex flex-row gap-4 overflow-x-auto pb-4 px-2 scrollbar-none snap-x"
                // 绑定鼠标事件
                onMouseDown={handleMouseDown}
                onMouseLeave={handleMouseUpOrLeave}
                onMouseUp={handleMouseUpOrLeave}
                onMouseMove={handleMouseMove}
                // 当处于拖拽状态时，改变鼠标指针样式
                style={{ cursor: isDragging ? 'grabbing' : 'grab' }}
            >
                {actors.map((actor) => (
                    // 🌟 样式变化：改为竖向卡片 (flex-col)，宽度增大到 140px (甚至可以改到 160px)
                    <div
                        key={actor.id}
                        className="flex-shrink-0 snap-start flex flex-col w-[140px] sm:w-[160px] group"
                    >
                        {/* 路由跳转区域（图片 + 文字） */}
                        <Link
                            href={actor.url || '#'}
                            onClick={handleLinkClick}
                            className="flex flex-col gap-2"
                        >
                            {/* 图片区域：大幅增加尺寸，依然保持 9:16 */}
                            <div className="relative w-full aspect-[9/16] overflow-hidden rounded-xl bg-muted/30 border border-border/50">
                                <img
                                    src={actor.photo9x16}
                                    alt={actor.name}
                                    draggable={false} // 禁用浏览器默认的图片拖拽，防止与我们的滑动冲突
                                    className="object-cover w-full h-full group-hover:scale-105 transition-transform duration-500"
                                />
                            </div>

                            {/* 文字区域 */}
                            <div className="flex flex-col px-1">
                                <h4 className="text-base font-semibold leading-none text-foreground mb-1.5 truncate">
                                    {actor.name}
                                </h4>
                                {actor.description && (
                                    <p className="text-xs text-muted-foreground truncate">
                                        {actor.description}
                                    </p>
                                )}
                            </div>
                        </Link>

                        {/* 关注按钮：放在卡片最底部，充满宽度 */}
                        <Button
                            variant="secondary"
                            size="sm"
                            className="w-full mt-3 rounded-full text-xs font-medium bg-muted hover:bg-muted/80 text-foreground"
                        >
                            关注
                        </Button>
                    </div>
                ))}
            </div>
        </div>
    );
};
