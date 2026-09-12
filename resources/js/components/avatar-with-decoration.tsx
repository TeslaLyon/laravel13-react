import React from 'react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { cn } from '@/lib/utils';

export interface AvatarDecorationData {
    id: number;
    title: string;
    imageUrl: string;
}

interface AvatarWithDecorationProps {
    avatarSrc?: string;
    avatarFallback: string;
    decoration?: AvatarDecorationData | null;
    /** 头像尺寸 Tailwind 类名，例如 "w-20 h-20" */
    sizeClassName?: string;
    /**
     * 🌟 挂件缩放比例调节参数（默认为紧凑的 scale-[1.18]）
     * 可选调试值：scale-110 / scale-115 / scale-120 / scale-125 / scale-[1.18]
     */
    decorationScaleClassName?: string;
    className?: string;
}

export const AvatarWithDecoration: React.FC<AvatarWithDecorationProps> = ({
    avatarSrc,
    avatarFallback,
    decoration,
    sizeClassName = 'w-12 h-12',
    decorationScaleClassName = 'scale-[1.18]', // 🌟 紧凑预设值，消除悬空缝隙
    className,
}) => {
    return (
        <div
            className={cn(
                'relative shrink-0 flex items-center justify-center select-none',
                sizeClassName,
                className
            )}
        >
            {/*
              🌟 参数 A：头像描边
              已将原先的 border-4（4px）改为 border-2（2px），若想完全贴合可直接改为 border-0
            */}
            <Avatar className="w-full h-full border-2 border-background shadow-md ring-1 ring-border/40">
                <AvatarImage src={avatarSrc} alt={avatarFallback} className="object-cover" />
                <AvatarFallback className="font-bold">
                    {avatarFallback.slice(0, 2).toUpperCase()}
                </AvatarFallback>
            </Avatar>

            {/*
              🌟 参数 B：动图挂件图层
              通过 decorationScaleClassName 接收缩放值进行精准微调
            */}
            {decoration && (
                <div className="absolute inset-0 flex items-center justify-center pointer-events-none z-10">
                    <img
                        src={decoration.imageUrl}
                        alt={decoration.title}
                        className={cn(
                            'w-full h-full object-contain select-none drop-shadow-sm transition-transform duration-300',
                            decorationScaleClassName
                        )}
                    />
                </div>
            )}
        </div>
    );
};
