import React, { useState, useMemo, useRef, useEffect } from 'react';
import { Loader2 } from 'lucide-react';
// 引入 Shadcn UI 原生 Progress 组件
import { Progress } from "@/components/ui/progress";

export interface ImageItem {
    src: string;
    src_source?: string;
    width: number;
    height: number;
    placeholder?: string;
    placeholder_source?: string;
    highdpi?: {
        double?: string;
        double_source?: string;
    };
    webp?: {
        src: string;
        src_source?: string;
        placeholder?: string;
        placeholder_source?: string;
        highdpi?: {
            double?: string;
            double_source?: string;
        };
    };
}

interface ResponsiveVideoImageProps {
    listImg?: ImageItem[];
    preview?: string;
    alt: string;
    dataCrawlType?: number;
    fallbackSrc?: string;
    className?: string;
}

export function ResponsiveVideoImage({
    listImg,
    preview,
    alt,
    dataCrawlType,
    fallbackSrc = "https://static0.srcdn.com/wordpress/wp-content/uploads/2024/12/gta-6-keyart-logo.jpg?w=1600&h=900&fit=crop",
    className = ""
}: ResponsiveVideoImageProps) {
    const [isCoverLoaded, setIsCoverLoaded] = useState(false);

    const [isHovered, setIsHovered] = useState(false);
    const [isPreviewLoading, setIsPreviewLoading] = useState(false);
    const [isPreviewReady, setIsPreviewReady] = useState(false);

    // 视频加载平滑百分比 (0 ~ 100)
    const [videoLoadProgress, setVideoLoadProgress] = useState(0);

    // 多图模式 (type = 3) 状态
    const [loadedImageUrls, setLoadedImageUrls] = useState<Set<string>>(new Set());
    const [activeImageIndex, setActiveImageIndex] = useState(0);
    const [displayedImageUrl, setDisplayedImageUrl] = useState<string>('');
    const [isMultiImageLoading, setIsMultiImageLoading] = useState(false);

    const containerRef = useRef<HTMLDivElement>(null);
    const videoRef = useRef<HTMLVideoElement>(null);
    const isSource = dataCrawlType === 1;

    // 1. 解析 preview 格式
    const previewData = useMemo(() => {
        if (!preview || !preview.trim() || !preview.includes('<')) return null;

        const firstBracketIndex = preview.indexOf('<');
        const typeStr = preview.substring(0, firstBracketIndex);
        const contentStr = preview.substring(firstBracketIndex + 1).trim();

        const type = parseInt(typeStr, 10);
        if (isNaN(type) || !contentStr) return null;

        let imageUrls: string[] = [];
        if (type === 3) {
            try {
                const parsed = JSON.parse(contentStr);
                if (Array.isArray(parsed)) imageUrls = parsed;
            } catch {
                imageUrls = contentStr.split(',').map(s => s.trim()).filter(Boolean);
            }
            if (imageUrls.length === 0) return null;
        }

        return {
            type, // 1: 视频, 2: GIF, 3: 多图
            url: contentStr,
            imageUrls
        };
    }, [preview]);

    // 2. 解析 Responsive 静态封面及 Placeholder
    const { webpSrcSet, jpgSrcSet, placeholderUrl, defaultSrc } = useMemo(() => {
        if (!listImg || !Array.isArray(listImg) || listImg.length === 0) {
            return { webpSrcSet: '', jpgSrcSet: '', placeholderUrl: '', defaultSrc: fallbackSrc };
        }

        const webpEntries: { url: string; width: number }[] = [];
        const jpgEntries: { url: string; width: number }[] = [];

        listImg.forEach((item) => {
            const webp1x = isSource ? (item.webp?.src_source || item.webp?.src) : item.webp?.src;
            const webp2x = isSource
                ? (item.webp?.highdpi?.double_source || item.webp?.highdpi?.double)
                : item.webp?.highdpi?.double;

            if (webp1x) webpEntries.push({ url: webp1x, width: item.width });
            if (webp2x) webpEntries.push({ url: webp2x, width: item.width * 2 });

            const jpg1x = isSource ? (item.src_source || item.src) : item.src;
            const jpg2x = isSource
                ? (item.highdpi?.double_source || item.highdpi?.double)
                : item.highdpi?.double;

            if (jpg1x) jpgEntries.push({ url: jpg1x, width: item.width });
            if (jpg2x) jpgEntries.push({ url: jpg2x, width: item.width * 2 });
        });

        const webpSrcSet = webpEntries
            .sort((a, b) => a.width - b.width)
            .map(e => `${e.url} ${e.width}w`)
            .join(', ');

        const jpgSrcSet = jpgEntries
            .sort((a, b) => a.width - b.width)
            .map(e => `${e.url} ${e.width}w`)
            .join(', ');

        const firstItem = listImg[0];
        const placeholderUrl = isSource
            ? (firstItem.webp?.placeholder_source || firstItem.placeholder_source || firstItem.webp?.placeholder || firstItem.placeholder || '')
            : (firstItem.webp?.placeholder || firstItem.placeholder || '');

        const lastItem = listImg[listImg.length - 1];
        const defaultSrc = isSource
            ? (lastItem.highdpi?.double_source || lastItem.src_source || lastItem.src)
            : (lastItem.highdpi?.double || lastItem.src);

        return { webpSrcSet, jpgSrcSet, placeholderUrl, defaultSrc };
    }, [listImg, isSource, fallbackSrc]);

    // 多图预加载
    const preloadImage = (url: string) => {
        if (loadedImageUrls.has(url)) return;

        const img = new Image();
        img.src = url;
        img.onload = () => {
            setLoadedImageUrls(prev => new Set(prev).add(url));
        };
    };

    // 🎯 核心优化：结合原生 video.buffered 检测与极小步伐缓动算法
    useEffect(() => {
        if (!isHovered || previewData?.type !== 1 || !isPreviewLoading) return;

        // 每 100ms 刷新一次，保证极度平滑的向右推进
        const timer = setInterval(() => {
            setVideoLoadProgress((prev) => {
                const video = videoRef.current;
                let realPercent = 0;

                // 1. 实时检测原生视频的真实缓冲进度
                if (video && Number.isFinite(video.duration) && video.duration > 0 && video.buffered.length > 0) {
                    const bufferedEnd = video.buffered.end(video.buffered.length - 1);
                    realPercent = Math.round((bufferedEnd / video.duration) * 100);
                }

                // 2. 匀速微量增长 (每次仅递增 0.8% ~ 1.8%，越靠近 90% 增长越细腻)
                const remaining = 92 - prev;
                const increment = Math.max(0.4, Math.min(1.8, remaining * 0.05));
                const smoothNext = Math.min(92, prev + increment);

                // 取真实缓冲百分比与模拟平滑进度的最大值
                return Math.max(prev, realPercent, smoothNext);
            });
        }, 100);

        return () => clearInterval(timer);
    }, [isHovered, previewData, isPreviewLoading]);

    // 3. 鼠标移入
    const handleMouseEnter = () => {
        setIsHovered(true);

        if (previewData) {
            if (previewData.type === 1) {
                setVideoLoadProgress(2); // 起步 2%，给用户即时响应感
                setIsPreviewLoading(true);
                setIsPreviewReady(false);
            } else if (previewData.type === 3) {
                setIsPreviewReady(true);
                if (previewData.imageUrls.length > 0) {
                    const firstUrl = previewData.imageUrls[0];
                    setDisplayedImageUrl(firstUrl);
                    previewData.imageUrls.forEach(preloadImage);
                }
            } else {
                setIsPreviewReady(true);
            }
        }
    };

    // 4. 鼠标移出：重置状态
    const handleMouseLeave = () => {
        setIsHovered(false);
        setIsPreviewLoading(false);
        setIsPreviewReady(false);
        setIsMultiImageLoading(false);
        setVideoLoadProgress(0);
        setActiveImageIndex(0);

        if (previewData?.type === 1 && videoRef.current) {
            videoRef.current.pause();
            videoRef.current.currentTime = 0;
        }
    };

    // 5. 多图划过切换 (type = 3)
    const handleMouseMove = (e: React.MouseEvent<HTMLDivElement>) => {
        if (
            previewData?.type === 3 &&
            previewData.imageUrls.length > 0 &&
            containerRef.current
        ) {
            const rect = containerRef.current.getBoundingClientRect();
            const x = e.clientX - rect.left;

            const percentage = Math.max(0, Math.min(1, x / rect.width));

            const newIndex = Math.min(
                Math.floor(percentage * previewData.imageUrls.length),
                previewData.imageUrls.length - 1
            );

            setActiveImageIndex(newIndex);
            const targetUrl = previewData.imageUrls[newIndex];

            if (loadedImageUrls.has(targetUrl)) {
                setDisplayedImageUrl(targetUrl);
                setIsMultiImageLoading(false);
            } else {
                setIsMultiImageLoading(true);
                preloadImage(targetUrl);
            }
        }
    };

    useEffect(() => {
        if (previewData?.type === 3 && isHovered) {
            const targetUrl = previewData.imageUrls[activeImageIndex];
            if (targetUrl && loadedImageUrls.has(targetUrl)) {
                setDisplayedImageUrl(targetUrl);
                setIsMultiImageLoading(false);
            }
        }
    }, [loadedImageUrls, activeImageIndex, previewData, isHovered]);

    // 🎯 6. 视频缓冲足够播放：冲满 100% 并顺滑隐藏
    const handleVideoCanPlay = () => {
        setVideoLoadProgress(100);

        // 留出 250ms 让用户直观感知到进度条全满，然后隐去
        setTimeout(() => {
            setIsPreviewLoading(false);
            setIsPreviewReady(true);
        }, 250);

        if (videoRef.current && isHovered) {
            videoRef.current.play().catch(() => { });
        }
    };

    return (
        <div
            ref={containerRef}
            className="relative w-full h-full overflow-hidden rounded-xl bg-muted group/image select-none"
            onMouseEnter={handleMouseEnter}
            onMouseLeave={handleMouseLeave}
            onMouseMove={handleMouseMove}
        >
            {/* 贴顶无黑框 Shadcn UI Progress 组件 */}
            {previewData && previewData.type === 1 && isHovered && isPreviewLoading && (
                <div className="absolute top-0 left-0 right-0 z-30 w-full overflow-hidden">
                    <Progress
                        value={videoLoadProgress}
                        className="h-1 w-full rounded-none bg-white/20 [&>*]:!bg-red-600 [&>*]:shadow-[0_0_8px_rgba(220,38,38,0.8)] [&>*]:!transition-all [&>*]:!duration-300"
                    />
                </div>
            )}

            {/* 多图未缓存加载框 (type = 3) */}
            {previewData?.type === 3 && isHovered && isMultiImageLoading && (
                <div className="absolute top-2 right-2 z-40 bg-black/60 backdrop-blur-md text-white p-1.5 rounded-full shadow-lg transition-all animate-in fade-in">
                    <Loader2 className="w-4 h-4 animate-spin text-white/90" />
                </div>
            )}

            {/* 模糊占位底图 */}
            {placeholderUrl && (
                <img
                    src={placeholderUrl}
                    alt={alt}
                    aria-hidden="true"
                    className={`absolute inset-0 w-full h-full object-cover rounded-xl transition-opacity duration-500 scale-105 filter blur-md ${isCoverLoaded ? 'opacity-0 pointer-events-none' : 'opacity-100'
                        }`}
                />
            )}

            {/* 静态响应式封面 */}
            {listImg && listImg.length > 0 ? (
                <picture>
                    {webpSrcSet && (
                        <source
                            type="image/webp"
                            srcSet={webpSrcSet}
                            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
                        />
                    )}
                    {jpgSrcSet && (
                        <source
                            type="image/jpeg"
                            srcSet={jpgSrcSet}
                            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
                        />
                    )}
                    <img
                        src={defaultSrc}
                        alt={alt}
                        loading="lazy"
                        onLoad={() => setIsCoverLoaded(true)}
                        className={`${className} transition-all duration-300 ${isCoverLoaded ? 'opacity-100 blur-0' : 'opacity-0 blur-sm'
                            }`}
                    />
                </picture>
            ) : (
                <img
                    src={fallbackSrc}
                    alt={alt}
                    className={className}
                    loading="lazy"
                />
            )}

            {/* 动态预览层 */}
            {previewData && isHovered && (
                <div className={`absolute inset-0 z-20 transition-opacity duration-200 ${isPreviewReady ? 'opacity-100' : 'opacity-0 pointer-events-none'
                    }`}>
                    {previewData.type === 1 && (
                        <video
                            ref={videoRef}
                            src={previewData.url}
                            autoPlay
                            muted
                            loop
                            playsInline
                            preload="auto"
                            onCanPlay={handleVideoCanPlay}
                            onWaiting={() => setIsPreviewLoading(true)}
                            className="w-full h-full object-cover rounded-xl"
                        />
                    )}

                    {previewData.type === 2 && (
                        <img
                            src={previewData.url}
                            alt={`${alt} GIF预览`}
                            className="w-full h-full object-cover rounded-xl"
                        />
                    )}

                    {previewData.type === 3 && displayedImageUrl && (
                        <img
                            src={displayedImageUrl}
                            alt={`${alt} 预览图 ${activeImageIndex + 1}`}
                            className="w-full h-full object-cover rounded-xl transition-all duration-100"
                        />
                    )}
                </div>
            )}

            {/* 多图模式指示器 */}
            {previewData?.type === 3 && isHovered && previewData.imageUrls.length > 1 && (
                <div className="absolute bottom-2 left-0 right-0 flex justify-center items-center gap-1 z-30 pointer-events-none px-2">
                    {previewData.imageUrls.map((_, idx) => (
                        <div
                            key={idx}
                            className={`h-1.5 rounded-full transition-all duration-200 ${idx === activeImageIndex
                                    ? 'w-4 bg-white shadow-md'
                                    : 'w-1.5 bg-white/50 backdrop-blur-sm'
                                }`}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}
