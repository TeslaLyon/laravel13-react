import React, { useState, useMemo, useEffect, useCallback } from 'react';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { ScreenImageMeta } from '@/types/video';
import { cdnUrl } from '@/lib/utils';
import { ChevronDown, ChevronUp, ChevronLeft, ChevronRight, X, Images } from 'lucide-react';

export type PreviewImageItem = string | ScreenImageMeta;

interface VideoPreviewsProps {
    // 传入预览图数组（支持 ScreenImageMeta 对象数组、URL 字符串数组，或 JSON 字符串）
    images?: PreviewImageItem[] | string | null;
    // 片商采集类型（1: 源站免鉴权直链，优先使用 source_url 缓解本地存储和带宽压力）
    dataCrawlType?: number;
    // 默认折叠时展示的剧照数量（默认 8 张，适配 2~4 列排版，大幅减少首屏 DOM 节点与并发网络加载）
    initialDisplayCount?: number;
}

export const VideoPreviews = ({
    images,
    dataCrawlType,
    initialDisplayCount = 8
}: VideoPreviewsProps) => {
    // 状态管理：控制展开/收起、大图弹窗以及当前聚焦索引
    const [isExpanded, setIsExpanded] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const [selectedIndex, setSelectedIndex] = useState<number | null>(null);

    const isSource = dataCrawlType === 1;

    // 标准化图片数据
    const normalizedImages: PreviewImageItem[] = useMemo(() => {
        if (!images) return [];
        if (typeof images === 'string') {
            try {
                const parsed = JSON.parse(images);
                return Array.isArray(parsed) ? parsed : [];
            } catch {
                return [];
            }
        }
        return Array.isArray(images) ? images : [];
    }, [images]);

    const totalCount = normalizedImages.length;
    const hasMore = totalCount > initialDisplayCount;

    // 当前展示的剧照子集（折叠时仅展示前 N 张，彻底减轻海量剧照对页面滚动的阻塞）
    const displayedImages = useMemo(() => {
        if (!hasMore || isExpanded) {
            return normalizedImages;
        }
        return normalizedImages.slice(0, initialDisplayCount);
    }, [normalizedImages, hasMore, isExpanded, initialDisplayCount]);

    if (!normalizedImages || totalCount === 0) return null;

    // 获取缩略图 URL（dataCrawlType === 1 时优先使用免防盗链源站直链）
    const getThumbUrl = (item: PreviewImageItem): string => {
        if (typeof item === 'string') {
            return isSource || item.startsWith('http://') || item.startsWith('https://') ? item : cdnUrl(item);
        }

        const raw = isSource
            ? (item.source_url || item.default_source_url || item.screen_img_default_source_url || item.url || item.default_url || item.sm || '')
            : (item.url || item.default_url || item.source_url || item.sm || item.screen_img_default_url || item.screen_img_default_source_url || '');

        if (!raw) return '';
        return isSource || raw.startsWith('http://') || raw.startsWith('https://') ? raw : cdnUrl(raw);
    };

    // 获取 Lightbox 弹窗大图 URL
    const getFullUrl = (item: PreviewImageItem): string | null => {
        if (!item || typeof item === 'string') return null;

        const raw = isSource
            ? (item.full_source_url || item.screen_img_full_source_url || item.full_url || item.xx || '')
            : (item.full_url || item.xx || item.screen_img_full_url || '');

        if (!raw || typeof raw !== 'string' || !raw.trim()) {
            return null;
        }

        const trimmed = raw.trim();
        return isSource || trimmed.startsWith('http://') || trimmed.startsWith('https://') ? trimmed : cdnUrl(trimmed);
    };

    // 获取网络回退 URL
    const getFallbackUrl = (item: PreviewImageItem): string | undefined => {
        if (typeof item === 'string') return undefined;

        const fallback = isSource
            ? (item.url || item.default_url || item.screen_img_default_url)
            : (item.source_url || item.full_source_url || item.screen_img_default_source_url || item.screen_img_full_source_url);

        if (!fallback) return undefined;
        return fallback.startsWith('http://') || fallback.startsWith('https://') ? fallback : cdnUrl(fallback);
    };

    // 点击缩略图弹出高清大图 Lightbox
    const handleImageClick = (index: number) => {
        const item = normalizedImages[index];
        const full = getFullUrl(item);
        if (full) {
            setSelectedIndex(index);
            setIsOpen(true);
        }
    };

    // 智能获取图片比例 (优先使用元数据 width/height 计算，避免硬编码比例导致拉伸或裁切)
    const getImageAspectRatio = (item: PreviewImageItem): string | undefined => {
        if (typeof item === 'object' && item !== null) {
            const w = Number(item.width) || Number(item.full_width) || Number(item.default_width);
            const h = Number(item.height) || Number(item.full_height) || Number(item.default_height);
            if (w > 0 && h > 0) {
                return `${w} / ${h}`;
            }
        }
        return undefined;
    };

    // 弹窗大图左右切换控制
    const handlePrev = useCallback(() => {
        if (selectedIndex !== null && selectedIndex > 0) {
            setSelectedIndex(selectedIndex - 1);
        }
    }, [selectedIndex]);

    const handleNext = useCallback(() => {
        if (selectedIndex !== null && selectedIndex < totalCount - 1) {
            setSelectedIndex(selectedIndex + 1);
        }
    }, [selectedIndex, totalCount]);

    // 弹窗打开时的键盘按键导航（左/右键翻页）
    useEffect(() => {
        if (!isOpen) return;

        const handleKeyDown = (e: KeyboardEvent) => {
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                handlePrev();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                handleNext();
            }
        };

        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [isOpen, handlePrev, handleNext]);

    const currentFullUrl = selectedIndex !== null ? getFullUrl(normalizedImages[selectedIndex]) : null;

    return (
        <div className="mt-6">
            <div className="flex items-center justify-between mb-3 px-1">
                <div className="flex items-center gap-2">
                    <h3 className="text-base sm:text-lg font-bold text-foreground">精彩剧照</h3>
                    <span className="text-xs text-muted-foreground bg-muted/80 px-2 py-0.5 rounded-full font-medium">
                        {totalCount} 张
                    </span>
                </div>

                {hasMore && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => setIsExpanded(!isExpanded)}
                        className="text-xs text-muted-foreground hover:text-foreground gap-1 h-7 px-2"
                    >
                        {isExpanded ? (
                            <>
                                <span>收起</span>
                                <ChevronUp className="w-3.5 h-3.5" />
                            </>
                        ) : (
                            <>
                                <span>展开全部 ({totalCount})</span>
                                <ChevronDown className="w-3.5 h-3.5" />
                            </>
                        )}
                    </Button>
                )}
            </div>

            {/* 1. 自适应瀑布流网格布局（强化 CSS 渲染隔离与硬件加速）：
                 - 移动端：2 列
                 - 平板端：3 列
                 - 桌面端：4 列
                 - content-visibility: auto + contain-intrinsic-size 智能跳过视口外无谓渲染
                 - decoding="async" 异步后台线程解压图片，彻底消除主线程阻塞与滚动卡顿
            */}
            <div className="columns-2 sm:columns-3 lg:columns-4 gap-3 px-1 space-y-3">
                {displayedImages.map((item, index) => {
                    const thumbUrl = getThumbUrl(item);
                    const fallbackUrl = getFallbackUrl(item);
                    const fullUrl = getFullUrl(item);
                    const hasFullImage = Boolean(fullUrl);
                    const aspectRatio = getImageAspectRatio(item);

                    return (
                        <div
                            key={index}
                            onClick={() => {
                                if (hasFullImage) {
                                    handleImageClick(index);
                                }
                            }}
                            style={{
                                aspectRatio: aspectRatio || undefined,
                                contentVisibility: 'auto',
                                containIntrinsicSize: 'auto none auto 240px',
                            }}
                            className={`break-inside-avoid relative w-full overflow-hidden rounded-xl bg-muted/50 border border-transparent transform-gpu ${
                                hasFullImage
                                    ? 'cursor-pointer hover:border-border/60 hover:shadow-md group'
                                    : 'cursor-default'
                            }`}
                        >
                            <img
                                src={thumbUrl}
                                alt={`剧照预览 ${index + 1}`}
                                loading="lazy"
                                decoding="async"
                                fetchPriority={index < 4 ? undefined : 'low'}
                                onError={(e) => {
                                    if (fallbackUrl && e.currentTarget.src !== fallbackUrl) {
                                        e.currentTarget.src = fallbackUrl;
                                    }
                                }}
                                className={`w-full h-auto block transition-transform duration-200 transform-gpu ${
                                    hasFullImage ? 'group-hover:scale-[1.02]' : ''
                                }`}
                            />
                            {/* 悬浮微光遮罩 */}
                            {hasFullImage && (
                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-200 pointer-events-none" />
                            )}
                        </div>
                    );
                })}
            </div>

            {/* 折叠模式下的底部优雅渐变过渡与展开引导栏 */}
            {hasMore && !isExpanded && (
                <div className="mt-3 flex justify-center">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => setIsExpanded(true)}
                        className="rounded-full px-5 py-2 text-xs sm:text-sm font-medium border-border/80 bg-background/80 hover:bg-muted backdrop-blur-sm shadow-sm gap-2 transition-all duration-200"
                    >
                        <Images className="w-4 h-4 text-primary" />
                        <span>查看剩余 {totalCount - initialDisplayCount} 张高清剧照</span>
                        <ChevronDown className="w-3.5 h-3.5 text-muted-foreground" />
                    </Button>
                </div>
            )}

            {/* 2. 点击查看大图的弹窗 (Lightbox)，支持左右切换与键盘导航 */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-4xl lg:max-w-5xl bg-black/90 border-zinc-800 text-white shadow-2xl p-2 sm:p-4 flex flex-col justify-center items-center backdrop-blur-md">
                    <DialogTitle className="sr-only">查看高清剧照</DialogTitle>

                    {/* 顶部指示器与关闭 */}
                    <div className="w-full flex justify-between items-center px-2 py-1 text-xs sm:text-sm text-zinc-400">
                        <span>
                            {selectedIndex !== null ? `${selectedIndex + 1} / ${totalCount}` : ''}
                        </span>
                    </div>

                    {/* 大图展示区域 */}
                    <div className="relative w-full rounded-md overflow-hidden flex justify-center items-center min-h-[40vh] max-h-[82vh]">
                        {currentFullUrl && (
                            <img
                                key={currentFullUrl}
                                src={currentFullUrl}
                                alt="高清剧照大图"
                                decoding="async"
                                className="w-full h-auto max-h-[80vh] object-contain rounded-md select-none transition-opacity duration-200"
                            />
                        )}

                        {/* 左翻页按钮 */}
                        {selectedIndex !== null && selectedIndex > 0 && (
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handlePrev();
                                }}
                                className="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center transition-colors border border-white/10 shadow-lg backdrop-blur-sm"
                                title="上一张 (← 键盘左键)"
                            >
                                <ChevronLeft className="w-5 h-5 sm:w-6 sm:h-6" />
                            </button>
                        )}

                        {/* 右翻页按钮 */}
                        {selectedIndex !== null && selectedIndex < totalCount - 1 && (
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    handleNext();
                                }}
                                className="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-black/60 hover:bg-black/90 text-white flex items-center justify-center transition-colors border border-white/10 shadow-lg backdrop-blur-sm"
                                title="下一张 (→ 键盘右键)"
                            >
                                <ChevronRight className="w-5 h-5 sm:w-6 sm:h-6" />
                            </button>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
};
