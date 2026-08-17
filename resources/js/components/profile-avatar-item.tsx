import React, { useState, useRef } from 'react';
import { router } from '@inertiajs/react';
import Cropper from 'react-easy-crop';
import { Camera, ZoomIn, ZoomOut, Loader2, ImagePlus } from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import getCroppedImg, { PixelCrop } from '@/lib/cropImage';
import type { User } from '@/types';

interface ProfileAvatarItemProps {
    user: User & { avatar?: string | null; nickname?: string | null };
    uploadUrl?: string;
    className?: string;
}

export default function ProfileAvatarItem({
    user,
    uploadUrl = '/settings/profile/avatar',
    className = '',
}: ProfileAvatarItemProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [imageSrc, setImageSrc] = useState<string | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [crop, setCrop] = useState({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(1);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<PixelCrop | null>(null);
    const [isUploading, setIsUploading] = useState(false);

    // 选取本地图片
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
        e.target.value = '';
    };

    // 裁剪区域计算
    const onCropComplete = (_: any, croppedPixels: PixelCrop) => {
        setCroppedAreaPixels(croppedPixels);
    };

    // 提交上传
    const handleUpload = async () => {
        if (!imageSrc || !croppedAreaPixels) return;

        try {
            setIsUploading(true);
            const croppedFile = await getCroppedImg(imageSrc, croppedAreaPixels);

            const formData = new FormData();
            formData.append('avatar', croppedFile);

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
            console.error('头像上传异常:', error);
            setIsUploading(false);
        }
    };

    const displayName = user.nickname || user.name || 'User';

    return (
        <div className={`grid gap-3 pt-1 pb-4 border-b border-border/60 ${className}`}>
            {/* 隐藏的原生文件输入框 */}
            <input
                ref={fileInputRef}
                type="file"
                accept="image/png, image/jpeg, image/webp"
                className="hidden"
                onChange={handleFileChange}
            />

            {/* 顶部标签与提示 */}
            <div>
                <Label className="text-sm font-semibold text-foreground">个人头像</Label>
                <p className="text-xs text-muted-foreground mt-0.5">
                    支持 JPG、PNG 或 WebP 图片格式，选择后可自由平移与缩放裁剪。
                </p>
            </div>

            {/* 头像展示与操作区域 */}
            <div className="flex items-center gap-4 mt-1">
                <div className="relative group shrink-0">
                    <Avatar className="w-16 h-16 border-2 border-border/80 shadow-xs">
                        <AvatarImage src={user.avatar || ''} alt={displayName} />
                        <AvatarFallback className="text-base font-bold bg-muted text-muted-foreground">
                            {displayName.charAt(0).toUpperCase()}
                        </AvatarFallback>
                    </Avatar>

                    {/* 快捷点击遮罩 */}
                    <button
                        type="button"
                        onClick={() => fileInputRef.current?.click()}
                        className="absolute inset-0 rounded-full bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-white"
                        title="点击更换头像"
                    >
                        <Camera className="w-4 h-4" />
                    </button>
                </div>

                <div className="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="gap-1.5 rounded-lg text-xs font-medium"
                        onClick={() => fileInputRef.current?.click()}
                    >
                        <ImagePlus className="w-3.5 h-3.5" />
                        <span>更换头像</span>
                    </Button>
                </div>
            </div>

            {/* 裁剪对话框 */}
            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>调整头像尺寸与位置</DialogTitle>
                    </DialogHeader>

                    {/* 裁剪视口 */}
                    <div className="relative w-full h-72 bg-neutral-950 rounded-xl overflow-hidden my-2">
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

                    {/* 缩放条 */}
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

                    <DialogFooter className="gap-2 sm:gap-0 mt-2">
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
                            {isUploading ? '正在保存...' : '确认并保存'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </div>
    );
}
