import React, { useState, useMemo } from 'react';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { ScreenImageMeta } from '@/types/video';
import { cdnUrl } from '@/lib/utils';

export type PreviewImageItem = string | ScreenImageMeta;

interface VideoPreviewsProps {
    // 传入预览图数组（支持 ScreenImageMeta 对象数组、URL 字符串数组，或 JSON 字符串）
    images?: PreviewImageItem[] | string | null;
    // 片商采集类型（1: 源站免鉴权直链，优先使用 source_url 缓解本地存储和带宽压力）
    dataCrawlType?: number;
}

export const VideoPreviews = ({ images, dataCrawlType }: VideoPreviewsProps) => {
    // 状态管理：控制弹窗的开关，以及当前正在查看的大图
    const [isOpen, setIsOpen] = useState(false);
    const [selectedImage, setSelectedImage] = useState<string | null>(null);

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

    if (!normalizedImages || normalizedImages.length === 0) return null;

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

    // 获取 Lightbox 弹窗大图 URL（dataCrawlType === 1 时优先使用源站大图；本地大图仅当非空时返回）
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

    // 获取网络回退 URL（若优先源站，则回退本地；若优先本地，则回退源站）
    const getFallbackUrl = (item: PreviewImageItem): string | undefined => {
        if (typeof item === 'string') return undefined;

        const fallback = isSource
            ? (item.url || item.default_url || item.screen_img_default_url)
            : (item.source_url || item.full_source_url || item.screen_img_default_source_url || item.screen_img_full_source_url);

        if (!fallback) return undefined;
        return fallback.startsWith('http://') || fallback.startsWith('https://') ? fallback : cdnUrl(fallback);
    };

    // 点击缩略图弹出高清大图 Lightbox（仅在大尺寸图片存在时激活）
    const handleImageClick = (item: PreviewImageItem) => {
        const full = getFullUrl(item);
        if (full) {
            setSelectedImage(full);
            setIsOpen(true);
        }
    };

    return (
        <div className="mt-6">
            <h3 className="text-base sm:text-lg font-bold text-foreground mb-3 px-1">精彩剧照</h3>

            {/* 1. 缩略图网格布局：手机端 2 列，平板 3 列，桌面端 4 列，16:9 比例 */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 px-1">
                {normalizedImages.map((item, index) => {
                    const thumbUrl = getThumbUrl(item);
                    const fallbackUrl = getFallbackUrl(item);
                    const fullUrl = getFullUrl(item);
                    const hasFullImage = Boolean(fullUrl);

                    return (
                        <div
                            key={index}
                            onClick={() => {
                                if (hasFullImage) {
                                    handleImageClick(item);
                                }
                            }}
                            className={`relative w-full aspect-video overflow-hidden rounded-xl bg-muted border border-transparent transition-all duration-300 ${
                                hasFullImage
                                    ? 'cursor-pointer hover:border-border/50 group'
                                    : 'cursor-default'
                            }`}
                        >
                            <img
                                src={thumbUrl}
                                alt={`剧照预览 ${index + 1}`}
                                loading="lazy"
                                onError={(e) => {
                                    if (fallbackUrl && e.currentTarget.src !== fallbackUrl) {
                                        e.currentTarget.src = fallbackUrl;
                                    }
                                }}
                                className={`object-cover w-full h-full transition-all duration-300 ${
                                    hasFullImage ? 'group-hover:scale-105 group-hover:opacity-90' : ''
                                }`}
                            />
                            {/* 悬浮时遮罩提示（仅在大图可用时展示） */}
                            {hasFullImage && (
                                <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-300" />
                            )}
                        </div>
                    );
                })}
            </div>

            {/* 2. 点击查看大图的弹窗 (Lightbox) */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="max-w-4xl lg:max-w-5xl bg-transparent border-none shadow-none p-0 flex justify-center items-center">
                    <DialogTitle className="sr-only">查看高清剧照</DialogTitle>

                    {selectedImage && (
                        <div className="relative w-full rounded-md overflow-hidden flex justify-center items-center">
                            <img
                                src={selectedImage}
                                alt="高清剧照大图"
                                className="w-full h-auto max-h-[85vh] object-contain rounded-md"
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
};
