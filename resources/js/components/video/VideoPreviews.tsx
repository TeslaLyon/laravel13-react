import React, { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogTitle,
} from "@/components/ui/dialog";

interface VideoPreviewsProps {
    // 传入预览图的 URL 数组
    images: string[];
}

export const VideoPreviews = ({ images }: VideoPreviewsProps) => {
    // 状态管理：控制弹窗的开关，以及当前正在查看的图片
    const [isOpen, setIsOpen] = useState(false);
    const [selectedImage, setSelectedImage] = useState<string | null>(null);

    if (!images || images.length === 0) return null;

    // 点击缩略图的处理函数
    const handleImageClick = (imgSrc: string) => {
        setSelectedImage(imgSrc);
        setIsOpen(true);
    };

    return (
        <div className="mt-8">
            <h3 className="text-base font-semibold text-foreground mb-4 px-1">视频预览</h3>

            {/* 1. 缩略图网格布局 */}
            {/* 手机端 2 列，平板 3 列，桌面端 4 列。使用 aspect-video 强制 16:9 比例 */}
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 px-1">
                {images.map((img, index) => (
                    <div
                        key={index}
                        onClick={() => handleImageClick(img)}
                        className="relative w-full aspect-video overflow-hidden rounded-xl bg-muted cursor-pointer group border border-transparent hover:border-border/50"
                    >
                        <img
                            src={img}
                            alt={`预览图 ${index + 1}`}
                            className="object-cover w-full h-full group-hover:scale-105 group-hover:opacity-90 transition-all duration-300"
                        />
                        {/* 悬浮时显示一个淡淡的放大镜图标提示或遮罩层，提升交互体验 */}
                        <div className="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors duration-300" />
                    </div>
                ))}
            </div>

            {/* 2. 点击查看大图的弹窗 (Lightbox) */}
            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                {/* max-w-5xl 控制弹窗的最大宽度，bg-transparent 去掉默认白色背景，让大图更有沉浸感 */}
                <DialogContent className="max-w-4xl lg:max-w-5xl bg-transparent border-none shadow-none p-0 flex justify-center items-center">

                    {/* 为了可访问性(无障碍要求)，shadcn Dialog 必须包含 Title，我们可以用 sr-only 隐藏它 */}
                    <DialogTitle className="sr-only">查看大图</DialogTitle>

                    {selectedImage && (
                        <div className="relative w-full rounded-md overflow-hidden">
                            <img
                                src={selectedImage}
                                alt="预览大图"
                                className="w-full h-auto max-h-[85vh] object-contain rounded-md"
                            />
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </div>
    );
};
