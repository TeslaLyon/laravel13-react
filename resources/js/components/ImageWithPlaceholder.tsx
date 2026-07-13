import React, { useState } from 'react';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

// 定义组件的 Props 接口
// 继承原生的 img 属性，这样组件就能原生支持 width, height, loading 等所有标准属性
interface ImageWithPlaceholderProps extends React.ImgHTMLAttributes<HTMLImageElement> {
    src: string;
    alt: string;
    className?: string;
    skeletonClassName?: string;
}

export default function ImageWithPlaceholder({
    src,
    alt,
    className,
    skeletonClassName,
    ...props
}: ImageWithPlaceholderProps) {
    // 明确指定 state 的类型为 boolean
    const [isLoaded, setIsLoaded] = useState<boolean>(false);

    return (
        <div className={cn("relative overflow-hidden rounded-md", className)}>
            {/* 占位符：当图片未加载完时显示 */}
            {!isLoaded && (
                <Skeleton
                    className={cn(
                        "absolute inset-0 h-full w-full",
                        skeletonClassName
                    )}
                />
            )}

            {/* 实际图片 */}
            <img
                src={src}
                alt={alt}
                // 图片加载完成时触发
                onLoad={() => setIsLoaded(true)}
                className={cn(
                    "h-full w-full object-cover transition-opacity duration-500 ease-in-out",
                    isLoaded ? "opacity-100" : "opacity-0" // 通过透明度实现平滑过渡
                )}
                {...props}
            />
        </div>
    );
}
