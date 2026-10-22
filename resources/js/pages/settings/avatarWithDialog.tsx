import React, { useState, useRef, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import Cropper, { type Point } from 'react-easy-crop';
import { Camera, ZoomIn, ZoomOut, Loader2, Upload, AlertCircle } from 'lucide-react';
import Heading from '@/components/heading';
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
import { edit as editAvatar, update as updateAvatarUrl } from '@/routes/profile/avatar';
import type { Auth } from '@/types';

type PageProps = {
    auth: Auth;
    errors: Record<string, string>;
};

// 🎯 缩放区间参数
const MIN_ZOOM = 1.0;
const MAX_ZOOM = 3.0;
const ZOOM_STEP = 0.05;

const MAX_RAW_FILE_SIZE_BYTES = 10 * 1024 * 1024;
const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

// 容器样式配置
const cropperContainerStyles: React.CSSProperties = {
    height: 'min(64vh, 560px)',
    minHeight: '360px',
    background: '#111',
    borderRadius: '8px',
    overflow: 'hidden',
    position: 'relative',
    boxSizing: 'border-box',
    wordWrap: 'break-word',
    WebkitTapHighlightColor: 'transparent',
    WebkitFontSmoothing: 'antialiased',
    textRendering: 'optimizeLegibility',
    textSizeAdjust: '100%',
};

export default function AvatarSettings() {
    const { auth } = usePage<PageProps>().props;
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [imageSrc, setImageSrc] = useState<string | null>(null);
    const [isModalOpen, setIsModalOpen] = useState(false);
    // 🎯 核心控制：标记弹窗动画是否已彻底结束，容器是否已就绪
    const [isCropperReady, setIsCropperReady] = useState(false);

    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(MIN_ZOOM);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<PixelCrop | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // 内存安全清理机制
    useEffect(() => {
        return () => {
            if (imageSrc && imageSrc.startsWith('blob:')) {
                URL.revokeObjectURL(imageSrc);
            }
        };
    }, [imageSrc]);

    // 🎯 核心解决机制：在弹窗展开动画彻底落定后，再挂载 Cropper 测量真实物理像素
    useEffect(() => {
        if (isModalOpen) {
            // 150ms 刚好等待 Radix Dialog 的 zoom-in 动画执行完毕
            const timer = setTimeout(() => {
                setIsCropperReady(true);
            }, 150);
            return () => clearTimeout(timer);
        } else {
            setIsCropperReady(false);
        }
    }, [isModalOpen]);

    // 放大与缩小交互
    const handleZoomOut = () => {
        setZoom((prev) => Math.max(MIN_ZOOM, Number((prev - ZOOM_STEP).toFixed(2))));
    };

    const handleZoomIn = () => {
        setZoom((prev) => Math.min(MAX_ZOOM, Number((prev + ZOOM_STEP).toFixed(2))));
    };

    // 1. 本地文件选择与校验
    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        setErrorMessage(null);

        if (e.target.files && e.target.files.length > 0) {
            const file = e.target.files[0];

            if (!ALLOWED_MIME_TYPES.includes(file.type)) {
                alert('仅支持 JPG、PNG 或 WebP 格式的图片，请重新选择！');
                e.target.value = '';
                return;
            }

            if (file.size > MAX_RAW_FILE_SIZE_BYTES) {
                alert(`图片文件过大 (${(file.size / 1024 / 1024).toFixed(1)} MB)！请选择 10MB 以内的图片。`);
                e.target.value = '';
                return;
            }

            if (imageSrc && imageSrc.startsWith('blob:')) {
                URL.revokeObjectURL(imageSrc);
            }
            const objectUrl = URL.createObjectURL(file);
            setImageSrc(objectUrl);
            setZoom(MIN_ZOOM);
            setCrop({ x: 0, y: 0 });
            setIsCropperReady(false); // 重置就绪状态
            setIsModalOpen(true);
        }

        e.target.value = '';
    };

    const onCropComplete = (_: any, croppedPixels: PixelCrop) => {
        setCroppedAreaPixels(croppedPixels);
    };

    // 2. 提交裁剪后的 400x400 头像 (通过前端 WebP 压缩 + Cloudflare R2 预签名秒级直传)
    const handleUpload = async () => {
        if (!imageSrc || !croppedAreaPixels) return;

        try {
            setIsUploading(true);
            setErrorMessage(null);

            // 1. 前端 Canvas 高速压缩为 400x400 WebP (质量 0.85，耗时仅 ~10ms，体积仅 15-25KB)
            const croppedWebpFile = await getCroppedImg(
                imageSrc,
                croppedAreaPixels,
                'avatar.webp',
                400,
                400,
                0.85,
                'image/webp'
            );

            let publicUrl: string | null = null;

            // 2. 尝试向后端申请 R2 预签名直传凭证
            try {
                const xsrfToken = decodeURIComponent(
                    document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] || ''
                );

                const presignRes = await fetch('/settings/avatar/presigned-url', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                    },
                    body: JSON.stringify({
                        content_type: 'image/webp',
                    }),
                });

                if (presignRes.ok) {
                    const presignData = await presignRes.json();
                    if (presignData.upload_url && presignData.public_url) {
                        // 3. 直接通过 PUT 上传至 Cloudflare R2
                        const r2Res = await fetch(presignData.upload_url, {
                            method: 'PUT',
                            body: croppedWebpFile,
                            headers: {
                                'Content-Type': 'image/webp',
                            },
                        });

                        if (r2Res.ok) {
                            publicUrl = presignData.public_url;
                        }
                    }
                }
            } catch (r2Error) {
                console.warn('R2 直传异常，自动切换为后端转存 R2 兜底模式:', r2Error);
            }

            // 4. 提交保存至数据库 (后端会自动清理旧头像文件以节省 R2 空间)
            if (publicUrl) {
                router.post(updateAvatarUrl(), { avatar_url: publicUrl }, {
                    preserveScroll: true,
                    onSuccess: () => {
                        handleModalClose(false);
                    },
                    onError: (errors) => {
                        setErrorMessage(errors.avatar_url || errors.avatar || '保存头像失败，请稍后重试。');
                    },
                    onFinish: () => {
                        setIsUploading(false);
                    },
                });
            } else {
                const formData = new FormData();
                formData.append('avatar', croppedWebpFile);

                router.post(updateAvatarUrl(), formData, {
                    preserveScroll: true,
                    onSuccess: () => {
                        handleModalClose(false);
                    },
                    onError: (errors) => {
                        setErrorMessage(errors.avatar || '上传头像失败，请稍后重试。');
                    },
                    onFinish: () => {
                        setIsUploading(false);
                    },
                });
            }
        } catch (error) {
            console.error('头像处理异常:', error);
            setErrorMessage('图片处理异常，请重试。');
            setIsUploading(false);
        }
    };

    const handleModalClose = (open: boolean) => {
        setIsModalOpen(open);
        if (!open) {
            setIsCropperReady(false);
            if (imageSrc && imageSrc.startsWith('blob:')) {
                URL.revokeObjectURL(imageSrc);
            }
            setImageSrc(null);
        }
    };

    const userAvatar = (auth.user as any)?.avatar;
    const displayName = auth.user.nickname || auth.user.name || 'User';

    return (
        <>
            <Head title="修改头像" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="修改个人头像"
                    description="上传您在社区和个人主页公开展示的头像照片"
                />

                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/png, image/jpeg, image/webp"
                    className="hidden"
                    onChange={handleFileChange}
                />

                {/* 当前头像展示卡片 */}
                <div className="rounded-2xl border border-border/70 bg-card p-6 shadow-xs flex flex-col sm:flex-row items-center sm:items-start gap-6">
                    <div className="relative group shrink-0">
                        <Avatar className="w-28 h-28 border-4 border-background shadow-md">
                            <AvatarImage src={userAvatar || ''} alt={displayName} />
                            <AvatarFallback className="text-3xl font-bold bg-muted text-muted-foreground">
                                {displayName.charAt(0).toUpperCase()}
                            </AvatarFallback>
                        </Avatar>

                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="absolute inset-0 rounded-full bg-black/50 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-white text-xs gap-1"
                            title="点击上传新头像"
                        >
                            <Camera className="w-5 h-5" />
                            <span>更换</span>
                        </button>
                    </div>

                    <div className="flex-1 text-center sm:text-left space-y-3">
                        <div>
                            <h3 className="font-semibold text-foreground text-base">当前头像展示</h3>
                            <p className="text-xs text-muted-foreground mt-1 leading-relaxed">
                                支持 JPG、PNG 或 WebP 格式，文件大小需在 10MB 以内。上传后将自动裁剪生成标准的 400×400 像素头像。
                            </p>
                        </div>

                        <div className="flex items-center justify-center sm:justify-start gap-3 pt-2">
                            <Button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="gap-2 font-semibold shadow-xs"
                            >
                                <Upload className="w-4 h-4" />
                                <span>选择新图片</span>
                            </Button>
                        </div>
                    </div>
                </div>

                {/* 裁剪弹窗 */}
                <Dialog open={isModalOpen} onOpenChange={handleModalClose}>
                    <DialogContent className="sm:max-w-2xl">
                        <DialogHeader>
                            <DialogTitle>调整头像尺寸与范围</DialogTitle>
                        </DialogHeader>

                        {errorMessage && (
                            <div className="flex items-center gap-2 p-3 bg-destructive/10 text-destructive text-xs rounded-lg border border-destructive/20">
                                <AlertCircle className="w-4 h-4 shrink-0" />
                                <span>{errorMessage}</span>
                            </div>
                        )}

                        {/* 裁剪容器 */}
                        <div
                            className="w-full my-2 select-none flex items-center justify-center"
                            style={cropperContainerStyles}
                        >
                            {/* 🎯 仅在弹窗动画完全结束（isCropperReady 为 true）后才挂载 Cropper */}
                            {imageSrc && isCropperReady ? (
                                <Cropper
                                    image={imageSrc}
                                    crop={crop}
                                    zoom={zoom}
                                    minZoom={MIN_ZOOM}
                                    maxZoom={MAX_ZOOM}
                                    aspect={1}
                                    cropShape="round"
                                    showGrid={false}
                                    onCropChange={setCrop}
                                    onZoomChange={setZoom}
                                    onCropComplete={onCropComplete}
                                    style={{
                                        containerStyle: {
                                            backgroundColor: 'transparent',
                                        },
                                        cropAreaStyle: {
                                            border: '2px solid rgba(255, 255, 255, 0.9)',
                                        },
                                        mediaStyle: {
                                            opacity: 0.98,
                                        },
                                    }}
                                />
                            ) : (
                                <div className="flex flex-col items-center gap-2 text-neutral-400">
                                    <Loader2 className="w-6 h-6 animate-spin" />
                                    <span className="text-xs">正在载入视口...</span>
                                </div>
                            )}
                        </div>

                        {/* 缩放条控制区 */}
                        <div className="flex items-center gap-2 px-1 py-1">
                            <button
                                type="button"
                                onClick={handleZoomOut}
                                disabled={zoom <= MIN_ZOOM || !isCropperReady}
                                className="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                                title="缩小"
                            >
                                <ZoomOut className="w-4 h-4" />
                            </button>

                            <input
                                type="range"
                                min={MIN_ZOOM}
                                max={MAX_ZOOM}
                                step={ZOOM_STEP}
                                value={zoom}
                                disabled={!isCropperReady}
                                onChange={(e) => setZoom(Number(e.target.value))}
                                className="w-full h-1.5 bg-secondary rounded-lg appearance-none cursor-pointer accent-primary disabled:opacity-50"
                            />

                            <button
                                type="button"
                                onClick={handleZoomIn}
                                disabled={zoom >= MAX_ZOOM || !isCropperReady}
                                className="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                                title="放大"
                            >
                                <ZoomIn className="w-4 h-4" />
                            </button>
                        </div>

                        {/* 底部操作按钮 */}
                        <DialogFooter className="flex flex-row justify-end items-center gap-3 sm:gap-4 mt-4">
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => handleModalClose(false)}
                                disabled={isUploading}
                                className="rounded-lg"
                            >
                                取消
                            </Button>
                            <Button
                                type="button"
                                onClick={handleUpload}
                                disabled={isUploading || !isCropperReady}
                                className="gap-2 rounded-lg"
                            >
                                {isUploading && <Loader2 className="w-4 h-4 animate-spin" />}
                                {isUploading ? '保存中...' : '确认并保存头像'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </>
    );
}

AvatarSettings.layout = {
    breadcrumbs: [
        {
            title: '设置',
            href: editAvatar(),
        },
        {
            title: '修改头像',
            href: editAvatar(),
        },
    ],
};
