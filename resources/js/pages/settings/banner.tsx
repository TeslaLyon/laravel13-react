import React, { useState, useRef, useEffect } from 'react';
import { Head, usePage, router } from '@inertiajs/react';
import Cropper, { type Point } from 'react-easy-crop';
import {
    Image as ImageIcon,
    ZoomIn,
    ZoomOut,
    Loader2,
    Upload,
    AlertCircle,
    Check,
    X,
    Trash2,
    Info,
    ShieldAlert,
    AlertTriangle,
} from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import getCroppedImg, { PixelCrop } from '@/lib/cropImage';
import type { Auth } from '@/types';
import { edit, destroy, update } from '@/actions/App/Http/Controllers/Settings/ProfileBannerController';
import { ImageUploadGuidelines } from '@/components/image-upload-guidelines';

type PageProps = {
    auth: Auth;
    errors: Record<string, string>;
};

// 🎯 缩放区间参数
const MIN_ZOOM = 1.0;
const MAX_ZOOM = 3.0;
const ZOOM_STEP = 0.05;

// 🎯 YouTube 桌面端标准横幅参数 (2560 × 424 ≈ 6.0377)
const BANNER_ASPECT_RATIO = 2560 / 424;
const BANNER_OUTPUT_WIDTH = 2560;
const BANNER_OUTPUT_HEIGHT = 424;

const MAX_RAW_FILE_SIZE_BYTES = 10 * 1024 * 1024; // 10MB
const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];

export default function BannerSettings() {
    const { auth } = usePage<PageProps>().props;
    const fileInputRef = useRef<HTMLInputElement>(null);

    const [imageSrc, setImageSrc] = useState<string | null>(null);
    const [imageRatio, setImageRatio] = useState<number | null>(null);
    const [crop, setCrop] = useState<Point>({ x: 0, y: 0 });
    const [zoom, setZoom] = useState(MIN_ZOOM);
    const [croppedAreaPixels, setCroppedAreaPixels] = useState<PixelCrop | null>(null);
    const [isUploading, setIsUploading] = useState(false);
    const [isDeleting, setIsDeleting] = useState(false);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);

    // 悬浮高亮区域状态 ('desktop' | 'mobile' | null)
    const [hoveredZone, setHoveredZone] = useState<'desktop' | 'mobile' | null>(null);

    // 内存安全清理机制：释放生成的 ObjectURL
    useEffect(() => {
        return () => {
            if (imageSrc && imageSrc.startsWith('blob:')) {
                URL.revokeObjectURL(imageSrc);
            }
        };
    }, [imageSrc]);

    // 缩放操作
    const handleZoomOut = () => {
        setZoom((prev) => Math.max(MIN_ZOOM, Number((prev - ZOOM_STEP).toFixed(2))));
    };

    const handleZoomIn = () => {
        setZoom((prev) => Math.min(MAX_ZOOM, Number((prev + ZOOM_STEP).toFixed(2))));
    };

    // 1. 本地文件校验、比例预解析与读取
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
            const img = new window.Image();
            img.onload = () => {
                setImageRatio(img.naturalWidth / img.naturalHeight);
                setImageSrc(objectUrl);
                setZoom(MIN_ZOOM);
                setCrop({ x: 0, y: 0 });
                setHoveredZone(null);
            };
            img.src = objectUrl;
        }

        e.target.value = '';
    };

    // 2. 裁剪框变动时记录选区像素
    const onCropComplete = (_: any, croppedPixels: PixelCrop) => {
        setCroppedAreaPixels(croppedPixels);
    };

    // 3. 取消并关闭裁剪平铺工作区
    const handleCancel = () => {
        if (imageSrc && imageSrc.startsWith('blob:')) {
            URL.revokeObjectURL(imageSrc);
        }
        setImageSrc(null);
        setImageRatio(null);
        setCroppedAreaPixels(null);
        setErrorMessage(null);
        setHoveredZone(null);
    };

    // 4. 提交裁剪生成的图片
    const handleUpload = async () => {
        if (!imageSrc || !croppedAreaPixels) return;

        try {
            setIsUploading(true);
            setErrorMessage(null);

            const croppedFile = await getCroppedImg(
                imageSrc,
                croppedAreaPixels,
                'banner.jpg',
                BANNER_OUTPUT_WIDTH,
                BANNER_OUTPUT_HEIGHT
            );

            const formData = new FormData();
            formData.append('banner', croppedFile);

            router.post(update.url(), formData, {
                preserveScroll: true,
                onSuccess: () => {
                    handleCancel();
                },
                onError: (errors) => {
                    if (errors.banner) {
                        setErrorMessage(errors.banner);
                    } else {
                        setErrorMessage('横幅上传失败，请稍后重试。');
                    }
                },
                onFinish: () => {
                    setIsUploading(false);
                },
            });
        } catch (error) {
            console.error('横幅处理异常:', error);
            setErrorMessage('图片处理异常，请重试。');
            setIsUploading(false);
        }
    };

    // 5. 移除当前已有横幅
    const handleDeleteBanner = () => {
        if (!confirm('确定要删除当前的横幅并恢复为默认背景吗？')) return;

        setIsDeleting(true);
        router.delete(destroy.url(), {
            preserveScroll: true,
            onFinish: () => {
                setIsDeleting(false);
            },
        });
    };

    const userBanner = (auth.user as any)?.banner || (auth.user as any)?.banner_url;

    return (
        <>
            <Head title="修改横幅" />

            <div className="w-full max-w-6xl space-y-6">
                <Heading
                    variant="small"
                    title="修改横幅"
                    description="上传展示在您个人主页顶部的宽幅背景图像（YouTube 标准 2560 × 424 比例）"
                />

                <input
                    ref={fileInputRef}
                    type="file"
                    accept="image/png, image/jpeg, image/webp"
                    className="hidden"
                    onChange={handleFileChange}
                />

                {/* 🎯 横幅上传规范与违规内容警示卡片 */}
                <ImageUploadGuidelines title="频道横幅上传规范与合规提示" />

                {/* 当前横幅预览卡片 */}
                <div className="rounded-2xl border border-border/70 bg-card p-6 sm:p-7 shadow-xs space-y-5">
                    <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                        <div>
                            <h3 className="font-semibold text-foreground text-base">当前横幅展示</h3>
                            <p className="text-xs text-muted-foreground mt-1">
                                支持 JPG、PNG 或 WebP 格式。上传后将自动裁剪生成 2560 × 424 像素的高清横条。
                            </p>
                        </div>

                        <div className="flex items-center gap-2 shrink-0">
                            {userBanner && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={handleDeleteBanner}
                                    disabled={isDeleting || isUploading}
                                    className="gap-1.5 text-xs text-destructive hover:bg-destructive/10 hover:text-destructive border-destructive/30"
                                >
                                    {isDeleting ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Trash2 className="w-3.5 h-3.5" />}
                                    <span>移除横幅</span>
                                </Button>
                            )}

                            <Button
                                type="button"
                                onClick={() => fileInputRef.current?.click()}
                                className="gap-2 font-semibold shadow-xs"
                            >
                                <Upload className="w-4 h-4" />
                                <span>{imageSrc ? '重新选择图片' : '选择新图片'}</span>
                            </Button>
                        </div>
                    </div>

                    {/* 2560:424 窄横幅真实预览视口 */}
                    <div className="relative w-full aspect-[2560/424] min-h-[110px] rounded-xl overflow-hidden bg-muted border border-border/50 group shadow-inner">
                        {userBanner ? (
                            <img
                                src={userBanner}
                                alt="Channel Banner"
                                className="w-full h-full object-cover object-center"
                            />
                        ) : (
                            <div className="w-full h-full flex flex-col items-center justify-center text-muted-foreground/50 bg-gradient-to-r from-muted/60 via-muted to-muted/60">
                                <ImageIcon className="w-8 h-8 mb-1.5 stroke-[1.5]" />
                                <span className="text-xs font-medium">暂未设置自定义横幅（2560 × 424）</span>
                            </div>
                        )}

                        <button
                            type="button"
                            onClick={() => fileInputRef.current?.click()}
                            className="absolute inset-0 bg-black/40 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer text-white text-xs gap-1.5 backdrop-blur-[2px]"
                            title="点击更换横幅"
                        >
                            <ImageIcon className="w-6 h-6" />
                            <span className="font-medium">点击更换横幅图片</span>
                        </button>
                    </div>
                </div>

                {/* 平铺展开的 2560 × 424 裁剪工作区 */}
                {imageSrc && (
                    <div className="rounded-2xl border border-border/80 bg-card p-6 sm:p-7 shadow-xs space-y-5 animate-in fade-in slide-in-from-top-4 duration-200">
                        <div className="flex items-center justify-between border-b border-border/60 pb-3">
                            <div>
                                <h4 className="font-medium text-sm text-foreground">调整横幅可视范围</h4>
                                <p className="text-[11px] text-muted-foreground mt-0.5">图片已完全平铺。上下拖动图片或缩放，选取最佳截取范围</p>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                onClick={handleCancel}
                                className="h-8 px-2 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <X className="w-4 h-4 mr-1" />
                                取消调整
                            </Button>
                        </div>

                        <div className="flex items-center gap-2 p-3 bg-muted/50 rounded-xl border border-border/50 text-xs text-muted-foreground">
                            <Info className="w-4 h-4 text-primary shrink-0" />
                            <span>
                                <strong>排版建议：</strong> 请将核心文字与 Logo 放置在<strong>“移动端可见区域”</strong>内，两侧区域在手机屏幕上会被自动裁剪隐藏。
                            </span>
                        </div>

                        {errorMessage && (
                            <div className="flex items-center gap-2 p-3 bg-destructive/10 text-destructive text-xs rounded-lg border border-destructive/20">
                                <AlertCircle className="w-4 h-4 shrink-0" />
                                <span>{errorMessage}</span>
                            </div>
                        )}

                        {/* 裁剪视口容器 */}
                        <div
                            className="w-full select-none relative overflow-hidden rounded-xl border border-border/60 shadow-inner"
                            style={{
                                width: '100%',
                                aspectRatio: imageRatio ? `${imageRatio}` : '16/9',
                                maxHeight: 'min(70vh, 560px)',
                                background: '#0a0a0a',
                            }}
                        >
                            {/* 1. 底层裁剪组件 */}
                            <Cropper
                                image={imageSrc}
                                crop={crop}
                                zoom={zoom}
                                minZoom={MIN_ZOOM}
                                maxZoom={MAX_ZOOM}
                                aspect={BANNER_ASPECT_RATIO}
                                cropShape="rect"
                                showGrid={false}
                                restrictPosition={true}
                                onCropChange={setCrop}
                                onZoomChange={setZoom}
                                onCropComplete={onCropComplete}
                                style={{
                                    cropAreaStyle: {
                                        border: '1.5px solid rgba(255, 255, 255, 0.9)',
                                        boxShadow: '0 0 0 9999em rgba(0, 0, 0, 0.7)',
                                    },
                                }}
                            />

                            {/* 2. 标尺覆盖层 */}
                            <div className="absolute inset-0 pointer-events-none flex items-center justify-center">
                                <div
                                    className="relative flex items-center justify-between overflow-hidden"
                                    style={{
                                        width: '100%',
                                        aspectRatio: `${BANNER_ASPECT_RATIO}`,
                                    }}
                                >
                                    {/* 左侧：桌面端可见区域 */}
                                    <div
                                        className={`h-full flex-1 border-r border-dashed border-white/40 flex flex-col items-center justify-center p-1 transition-all duration-200 ${hoveredZone === 'mobile'
                                            ? 'opacity-0 invisible'
                                            : hoveredZone === 'desktop'
                                                ? 'bg-blue-500/25 border-blue-400 opacity-100'
                                                : 'bg-black/25 opacity-100'
                                            }`}
                                    >
                                        <button
                                            type="button"
                                            onMouseEnter={() => setHoveredZone('desktop')}
                                            onMouseLeave={() => setHoveredZone(null)}
                                            className="pointer-events-auto cursor-pointer text-[10px] sm:text-xs text-white/90 font-medium px-2 py-0.5 rounded bg-black/70 border border-white/20 backdrop-blur-xs transition-colors hover:bg-blue-600 hover:text-white hover:border-blue-400 truncate max-w-full"
                                        >
                                            桌面设备上可见区域
                                        </button>
                                    </div>

                                    {/* 中央：移动端可见核心区域 (60.39%) */}
                                    <div
                                        className={`h-full border-x-2 flex flex-col items-center justify-between py-1.5 px-1 transition-all duration-200 ${hoveredZone === 'desktop'
                                            ? 'opacity-0 invisible'
                                            : hoveredZone === 'mobile'
                                                ? 'border-amber-400 bg-amber-500/25 shadow-[0_0_20px_rgba(245,158,11,0.3)] opacity-100'
                                                : 'border-amber-400 bg-amber-500/10 shadow-[0_0_15px_rgba(245,158,11,0.15)] opacity-100'
                                            }`}
                                        style={{ width: '60.39%' }}
                                    >
                                        <button
                                            type="button"
                                            onMouseEnter={() => setHoveredZone('mobile')}
                                            onMouseLeave={() => setHoveredZone(null)}
                                            className="pointer-events-auto cursor-pointer flex items-center gap-1 bg-amber-400 text-black text-[10px] sm:text-xs font-bold px-2.5 py-0.5 rounded-full shadow-sm hover:bg-amber-300 transition-colors"
                                        >
                                            <span>移动端可见区域 (所有设备)</span>
                                        </button>
                                        <span
                                            className={`text-amber-200/90 text-[9px] sm:text-[10px] font-mono tracking-wider bg-black/70 px-2 py-0.5 rounded border border-amber-400/30 transition-opacity ${hoveredZone === 'desktop' ? 'opacity-0' : 'opacity-100'
                                                }`}
                                        >
                                            1546 × 424 (核心区域)
                                        </span>
                                    </div>

                                    {/* 右侧：桌面端可见区域 */}
                                    <div
                                        className={`h-full flex-1 border-l border-dashed border-white/40 flex flex-col items-center justify-center p-1 transition-all duration-200 ${hoveredZone === 'mobile'
                                            ? 'opacity-0 invisible'
                                            : hoveredZone === 'desktop'
                                                ? 'bg-blue-500/25 border-blue-400 opacity-100'
                                                : 'bg-black/25 opacity-100'
                                            }`}
                                    >
                                        <button
                                            type="button"
                                            onMouseEnter={() => setHoveredZone('desktop')}
                                            onMouseLeave={() => setHoveredZone(null)}
                                            className="pointer-events-auto cursor-pointer text-[10px] sm:text-xs text-white/90 font-medium px-2 py-0.5 rounded bg-black/70 border border-white/20 backdrop-blur-xs transition-colors hover:bg-blue-600 hover:text-white hover:border-blue-400 truncate max-w-full"
                                        >
                                            桌面设备上可见区域
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 缩放控制条 */}
                        <div className="flex items-center gap-3 px-3 py-2 bg-muted/40 rounded-xl border border-border/40">
                            <button
                                type="button"
                                onClick={handleZoomOut}
                                disabled={zoom <= MIN_ZOOM}
                                className="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-background transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
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
                                onChange={(e) => setZoom(Number(e.target.value))}
                                className="w-full h-1.5 bg-secondary rounded-lg appearance-none cursor-pointer accent-primary"
                            />

                            <button
                                type="button"
                                onClick={handleZoomIn}
                                disabled={zoom >= MAX_ZOOM}
                                className="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-background transition-colors disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
                                title="放大"
                            >
                                <ZoomIn className="w-4 h-4" />
                            </button>
                        </div>

                        {/* 底部按钮 */}
                        <div className="flex items-center justify-end gap-3 pt-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={handleCancel}
                                disabled={isUploading}
                                className="rounded-lg"
                            >
                                取消
                            </Button>
                            <Button
                                type="button"
                                onClick={handleUpload}
                                disabled={isUploading}
                                className="gap-2 rounded-lg"
                            >
                                {isUploading ? (
                                    <Loader2 className="w-4 h-4 animate-spin" />
                                ) : (
                                    <Check className="w-4 h-4" />
                                )}
                                {isUploading ? '正在保存横幅...' : '确认并保存横幅'}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

BannerSettings.layout = {
    breadcrumbs: [
        {
            title: '设置',
            href: edit(),
        },
        {
            title: '修改横幅',
            href: edit(),
        },
    ],
};
