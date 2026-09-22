import React, { useState, useMemo } from 'react';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from "@/components/ui/dialog";
import { ScreenImageMeta } from '@/types/video';

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

    // 获取缩略图 URL（当 dataCrawlType === 1 时优先使用免鉴权源站小图）
    const getThumbUrl = (item: PreviewImageItem): string => {
        if (typeof item === 'string') return item;
        if (isSource) {
            return (
                item.screen_img_default_source_url ||
                item.screen_img_full_source_url ||
                item.screen_img_default_url ||
                item.screen_img_full_url ||
                ''
            );
        }
        return (
            item.screen_img_default_url ||
            item.screen_img_default_source_url ||
            item.screen_img_full_url ||
            item.screen_img_full_source_url ||
            ''
        );
    };

    // 获取 Lightbox 弹窗大图 URL（当 dataCrawlType === 1 时优先使用免鉴权源站高清大图）
    const getFullUrl = (item: PreviewImageItem): string => {
        if (typeof item === 'string') return item;
        if (isSource) {
            return (
                item.screen_img_full_source_url ||
                item.screen_img_default_source_url ||
                item.screen_img_full_url ||
                item.screen_img_default_url ||
                ''
            );
        }
        return (
            item.screen_img_full_url ||
            item.screen_img_full_source_url ||
            item.screen_img_default_url ||
            item.screen_img_default_source_url ||
            ''
        );
    };

    // 获取网络回退 URL（若优先源站，则回退本地；若优先本地，则回退源站）
    const getFallbackUrl = (item: PreviewImageItem): string | undefined => {
        if (typeof item === 'string') return undefined;
        if (isSource) {
            return item.screen_img_default_url || item.screen_img_full_url;
        }
        return item.screen_img_default_source_url || item.screen_img_full_source_url;
    };

    // 点击缩略图弹出高清大图 Lightbox
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

                    return (
                        <div
                            key={index}
                            onClick={() => handleImageClick(item)}
                            className="relative w-full aspect-video overflow-hidden rounded-xl bg-muted cursor-pointer group border border-transparent hover:border-border/50"
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
                                className="object-cover w-full h-full group-hover:scale-105 group-hover:opacity-90 transition-all duration-300"
                            />
                            {/* 悬浮时遮罩提示 */}
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-300" />
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
