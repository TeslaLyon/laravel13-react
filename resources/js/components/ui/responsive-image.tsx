import { cn } from "@/lib/utils"; // shadcn 项目的标准工具类
import React, { ImgHTMLAttributes } from "react";

// 1. 定义数据类型，确保 TypeScript 类型安全
interface ImageVariant {
    src: string;
    highdpi?: { double: string };
    placeholder?: string;
}

export interface ImageData extends ImageVariant {
    width: number;
    height: number;
    webp?: ImageVariant;
}

interface ResponsiveImageProps extends ImgHTMLAttributes<HTMLImageElement> {
    images: ImageData[];
    alt: string;
}

export function ResponsiveImage({
    images,
    alt,
    className,
    ...props
}: ResponsiveImageProps) {
    // 如果数据为空，不渲染
    if (!images || images.length === 0) return null;

    // 2. 将图片按宽度从大到小排序 (Desktop First 逻辑)
    // 这样浏览器会从上往下匹配 media 查询，找到第一个符合屏幕宽度的 source
    const sortedImages = [...images].sort((a, b) => b.width - a.width);

    // 3. 选取最小的一张图作为默认的回退图 (Fallback)
    const fallbackImage = sortedImages[sortedImages.length - 1];

    return (
        <picture>
            {sortedImages.map((img, index) => {
                // 最后一张（最小的图）不需要 media 查询，它将直接交给 <img> 标签处理
                const isLast = index === sortedImages.length - 1;
                const mediaQuery = isLast ? undefined : `(min-width: ${img.width}px)`;

                return (
                    <React.Fragment key={`${img.width}-${index}`}>
                        {/* 优先提供 WebP 格式源 */}
                        {img.webp && (
                            <source
                                type="image/webp"
                                media={mediaQuery}
                                srcSet={`${img.webp.src} 1x${img.webp.highdpi?.double ? `, ${img.webp.highdpi.double} 2x` : ""
                                    }`}
                            />
                        )}

                        {/* 常规格式源 (JPEG/PNG) */}
                        {!isLast && (
                            <source
                                media={mediaQuery}
                                srcSet={`${img.src} 1x${img.highdpi?.double ? `, ${img.highdpi.double} 2x` : ""
                                    }`}
                            />
                        )}
                    </React.Fragment>
                );
            })}

            {/* 4. 默认 <img> 标签：所有 source 都不匹配时，或浏览器不支持 picture 时的兜底 */}
            <img
                src={fallbackImage.src}
                srcSet={`${fallbackImage.src} 1x${fallbackImage.highdpi?.double ? `, ${fallbackImage.highdpi.double} 2x` : ""
                    }`}
                width={fallbackImage.width}
                height={fallbackImage.height}
                alt={alt}
                loading="lazy" // 开启浏览器原生懒加载
                className={cn(
                    "w-full h-auto object-cover transition-all duration-300",
                    className
                )}
                style={{
                    // 将 placeholder 作为背景图，在原图加载出来之前提供模糊预览
                    backgroundImage: fallbackImage.placeholder
                        ? `url(${fallbackImage.placeholder})`
                        : "none",
                    backgroundSize: "cover",
                    backgroundPosition: "center",
                }}
                {...props}
            />
        </picture>
    );
}
