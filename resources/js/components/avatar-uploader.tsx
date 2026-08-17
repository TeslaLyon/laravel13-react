import React, { useState, useRef } from 'react';
import { router } from '@inertiajs/react';
import Cropper from 'react-easy-crop';
import { Camera, ZoomIn, ZoomOut, Loader2 } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import getCroppedImg, { PixelCrop } from '@/lib/cropImage';

interface AvatarUploaderProps {
    currentAvatar?: string | null;
    userName: string;
    uploadUrl?: string; // 默认上传接口路径
}

export default function AvatarUploader({
    currentAvatar,
    userName,
    uploadUrl = '/settings/profile/avatar',
}: AvatarUploaderProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [imageSrc, setImageSrc] = useState<string | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<PixelCrop | null>(null);
    const [isUploading, setIsUploading] = useState(false);

    // 1. 选择本地图片
    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                setImageSrc(reader.result as string);
                setZoom(1);
                setIsModalOpen(true);
            });
            reader.readAsDataURL(file);
        }
        // 重置 input 以便再次选取同一文件
        e.target.value = '';
    };

    // 2. 裁剪区域改变回调
    const onCropComplete = (_: any, croppedPixels: PixelCrop) => {
        setCroppedAreaPixels(croppedPixels);
    };

    // 3. 确认裁剪并上传
    const handleUpload = async () => {
        if (!imageSrc || !croppedAreaPixels) return;

        try {
            setIsUploading(true);
            const croppedFile = await getCroppedImg(imageSrc, croppedAreaPixels);

            // 构造上传数据
            const formData = new FormData();
            formData.append('avatar', croppedFile);

            // 通过 Inertia 上传
            router.post(uploadUrl, formData, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsModalOpen(false);
                    setImageSrc(null);
                },
                onFinish: () => {
                    setIsUploading(false);
                },
            });
        } catch (error) {
            console.error('裁剪或上传失败:', error);
            setIsUploading(false);
        }
    };

    return (
        <div className="flex items-center gap-5">
            {/* 头像预览区 */}
            <div className="relative group">
                <Avatar className="w-20 h-20 border-2 border-border shadow-xs">
                    <AvatarImage src={currentAvatar || ''} alt={userName} />
                    <AvatarFallback className="text-lg font-semibold bg-muted">
                        {userName ? userName.charAt(0).toUpperCase() : 'U'}
                    </AvatarFallback>
                </Avatar>

                {/* 悬浮快速更换遮罩 */}
                <button
                    type="button"
                    onClick={() => fileInputRef.current?.click()}
                    className="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-white"
                    title="点击更换头像"
                >
                    <Camera className="w-5 h-5" />
                </button>
            </div>

            {/* 操作控制与说明 */}
            <div className="space-y-1.5">
                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/png, image/jpeg, image/webp"
                    className="hidden"
                    onChange={handleFileChange}
                />
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => fileInputRef.current?.click()}
                >
                    更换头像
                </Button>
                <p className="text-xs text-muted-foreground">
                    支持 JPG、PNG 或 WebP 格式，上传后可自由缩放和裁剪。
                </p>
            </div>

            {/* 裁剪弹窗 Dialog */}
            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>裁剪新头像</DialogTitle>
                    </DialogHeader>

                    {/* 裁剪区域 */}
                    <div className="relative w-full h-72 bg-neutral-900 rounded-lg overflow-hidden my-2">
                        {imageSrc && (
                            <Cropper
                                image={imageSrc}
                                crop={crop}
                                zoom={zoom}
                                aspect={1}
                                cropShape="round"
                                showGrid={false}
                                onCropChange={setCrop}
                                onZoomChange={setZoom}
                                onCropComplete={onCropComplete}
                            />
                        )}
                    </div>

                    {/* 缩放滑动控制条 */}
                    <div className="flex items-center gap-3 px-2 py-1">
                        <ZoomOut className="w-4 h-4 text-muted-foreground shrink-0" />
                        <input
                            type="range"
                            min={1}
                            max={3}
                            step={0.05}
                            value={zoom}
                            onChange={(e) => setZoom(Number(e.target.value))}
                            className="w-full h-1.5 bg-secondary rounded-lg appearance-none cursor-pointer accent-primary"
                        />
                        <ZoomIn className="w-4 h-4 text-muted-foreground shrink-0" />
                    </div>

                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => setIsModalOpen(false)}
                            disabled={isUploading}
                        >
                            取消
                        </Button>
                        <Button
                            type="button"
                            onClick={handleUpload}
                            disabled={isUploading}
                            className="gap-2"
                        >
                            {isUploading && <Loader2 className="w-4 h-4 animate-spin" />}
                            {isUploading ? '保存中...' : '保存头像'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
