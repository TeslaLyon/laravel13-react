import React, { useState, useMemo, useEffect } from 'react';
import {
    Download,
    Magnet,
    ShoppingBag,
    Copy,
    ExternalLink,
    Zap,
    Gift,
    Upload,
    Link as LinkIcon,
    FileUp,
    Plus,
    ArrowLeft,
    HardDrive,
    AlertCircle,
    MessageSquareWarning,
    Loader2
} from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";

// 1. 引入 Inertia 与 toast
import { useHttp } from "@inertiajs/react";
import { toast } from "sonner";
import { Video } from "@/types/video";
import VideoDownloadSubmissionController from '@/actions/App/Http/Controllers/VideoDownloadSubmissionController';
import VideoDownloadFeedbackController from '@/actions/App/Http/Controllers/VideoDownloadFeedbackController';

// 2. 引入全局登录拦截 Hook
import { useRequireAuth } from '@/components/require-auth-provider';

export interface DownloadItem {
    id: number;
    type: 'magnet' | 'store' | 'link';
    costType: 'free' | 'paid';
    title: string;
    description: string;
    resolution?: string;
    price?: string;
    link: string;
    fileSize?: number | null;
}

interface DownloadDialogProps {
    video?: Video;
}

const FEEDBACK_TYPES = [
    { value: 1, label: '死链/无速度', desc: '链接失效、无做种、长时间 0KB 速度' },
    { value: 2, label: '内容不符/货不对板', desc: '下载后影片内容与番号/标题不符' },
    { value: 3, label: '密码/提取码错误', desc: '网盘提取码失效或解压密码错误' },
    { value: 4, label: '清晰度不符', desc: '标称 4K/1080P，实际为模糊画质' },
    { value: 5, label: '广告捆绑/恶意文件', desc: '含病毒木马、非视频或大量引流广告' },
    { value: 6, label: '其他问题', desc: '其他未列出的资源异常' },
];

export function DownloadDialog({ video }: DownloadDialogProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [activeView, setActiveView] = useState<'list' | 'submit'>('list');

    const [submitType, setSubmitType] = useState<'magnet' | 'link' | 'torrent'>('magnet');
    const [submitContent, setSubmitContent] = useState('');
    const [extractionCode, setExtractionCode] = useState('');   // 提取码
    const [archivePassword, setArchivePassword] = useState(''); // 解压密码
    const [remark, setRemark] = useState('');                   // 备注说明
    const [torrentFile, setTorrentFile] = useState<File | null>(null);

    // 纠错反馈弹窗状态
    const [feedbackOpen, setFeedbackOpen] = useState(false);
    const [selectedFeedbackDownload, setSelectedFeedbackDownload] = useState<DownloadItem | null>(null);

    // 3. 获取全局登录拦截方法
    const { requireAuth } = useRequireAuth();

    // 4. 使用 useHttp Hook 管理表单提交状态
    const { post, processing, transform } = useHttp({
        video_id: 0 as number,
        type: 'magnet' as string,
        content: null as string | null,
        torrent_file: null as File | null,
        extraction_code: null as string | null,
        archive_password: null as string | null,
        remark: null as string | null,
    });

    // 5. 纠错反馈表单 Hook
    const feedbackForm = useHttp({
        type: 1 as number,
        content: '' as string,
    });

    // 字节大小转换显示 (KB / MB / GB)
    const formatFileSize = (bytes?: number | null): string | null => {
        if (!bytes || bytes <= 0) return null;
        const gb = 1024 * 1024 * 1024;
        const mb = 1024 * 1024;
        const kb = 1024;

        if (bytes >= gb) {
            return (bytes / gb).toFixed(2) + ' GB';
        }
        if (bytes >= mb) {
            return (bytes / mb).toFixed(2) + ' MB';
        }
        if (bytes >= kb) {
            return (bytes / kb).toFixed(1) + ' KB';
        }
        return bytes + ' B';
    };

    const downloads = useMemo<DownloadItem[]>(() => {
        const tableDownloads = video?.active_downloads ?? video?.downloads;
        if (Array.isArray(tableDownloads) && tableDownloads.length > 0) {
            return tableDownloads.map((item) => ({
                id: item.id,
                type: (item.type as 'magnet' | 'store' | 'link') || 'magnet',
                costType: (item.cost_type as 'free' | 'paid') || 'free',
                title: item.title,
                description: item.description || '',
                resolution: item.resolution || undefined,
                price: item.price || undefined,
                link: item.link,
                fileSize: item.file_size,
            }));
        }

        const rawInfo = video?.video_detail?.download_info;
        if (Array.isArray(rawInfo)) {
            return (rawInfo as any[]).map((item, idx) => ({
                id: item.id ?? idx,
                type: item.type || 'magnet',
                costType: item.costType || item.cost_type || 'free',
                title: item.title,
                description: item.description || '',
                resolution: item.resolution,
                price: item.price,
                link: item.link,
                fileSize: item.fileSize ?? item.file_size ?? null,
            }));
        }
        return [];
    }, [video?.active_downloads, video?.downloads, video?.video_detail?.download_info]);

    useEffect(() => {
        if (isOpen) {
            if (downloads.length > 0) {
                setActiveView('list');
            } else {
                setActiveView('submit');
            }
        } else {
            resetForm();
        }
    }, [isOpen, downloads.length]);

    const getResolutionBadge = (resolution?: string) => {
        if (!resolution) return null;
        switch (resolution.toUpperCase()) {
            case '4K':
            case '2160P':
                return (
                    <Badge variant="outline" className="text-purple-600 bg-purple-500/10 border-purple-500/30 px-2 py-0.5 text-xs font-bold shrink-0">
                        4K UHD
                    </Badge>
                );
            case '1080P':
                return (
                    <Badge variant="outline" className="text-blue-600 bg-blue-500/10 border-blue-500/30 px-2 py-0.5 text-xs font-bold shrink-0">
                        1080P HD
                    </Badge>
                );
            case '720P':
                return (
                    <Badge variant="outline" className="text-slate-600 bg-slate-500/10 border-slate-500/30 px-2 py-0.5 text-xs font-medium shrink-0">
                        720P
                    </Badge>
                );
            case 'SD':
            case '480P':
                return (
                    <Badge variant="outline" className="text-amber-600 bg-amber-500/10 border-amber-500/30 px-2 py-0.5 text-xs font-bold shrink-0">
                        SD 标清
                    </Badge>
                );
            default:
                return (
                    <Badge variant="outline" className="text-slate-600 bg-slate-500/10 border-slate-500/30 px-2 py-0.5 text-xs font-medium shrink-0">
                        {resolution}
                    </Badge>
                );
        }
    };

    // 5. 重构后的弹窗触发逻辑：使用全局 requireAuth 拦截
    const handleOpenDialog = () => {
        requireAuth(() => {
            setIsOpen(true);
        });
    };

    const handleCopy = async (text: string) => {
        try {
            await navigator.clipboard.writeText(text);
            toast.success("复制成功！", {
                duration: 4000,
                description: "下载链接已保存到您的剪贴板。",
            });
        } catch (err) {
            toast.error("复制失败", {
                description: "您的浏览器不支持或拒绝了剪贴板访问，请手动复制。",
            });
        }
    };

    const handleDirectDownload = (link: string, type?: string) => {
        if (type === 'magnet' || link.startsWith('magnet:')) {
            window.location.href = link;
        } else {
            window.open(link, '_blank', 'noopener,noreferrer');
        }
    };

    const resetForm = () => {
        setSubmitContent('');
        setExtractionCode('');
        setArchivePassword('');
        setRemark('');
        setTorrentFile(null);
        setSubmitType('magnet');
    };

    // 提交处理逻辑
    const handleSubmit = () => {
        if (!video?.id) {
            toast.error("未知视频 ID");
            return;
        }

        if (submitType === 'torrent' && !torrentFile) {
            toast.error("请选择要上传的种子文件 (.torrent)");
            return;
        }

        if (submitType !== 'torrent' && !submitContent.trim()) {
            toast.error("内容不能为空", {
                description: submitType === 'magnet' ? "请输入有效的磁力链接" : "请输入有效的第三方下载链接",
            });
            return;
        }

        transform(() => ({
            video_id: video.id,
            type: submitType,
            content: submitType !== 'torrent' ? submitContent.trim() : null,
            torrent_file: submitType === 'torrent' ? torrentFile : null,
            extraction_code: extractionCode.trim() || null,
            archive_password: archivePassword.trim() || null,
            remark: remark.trim() || null,
        }));

        post(VideoDownloadSubmissionController.store.url({ video: video.id, slug: video.slug }), {
            onSuccess: (response: any) => {
                const serverMessage = response?.data?.message || response?.message || "提交成功，感谢您的贡献！";

                toast.success(serverMessage, {
                    duration: 5000,
                });

                resetForm();

                if (downloads.length > 0) {
                    setActiveView('list');
                } else {
                    setIsOpen(false);
                }
            },
            onError: (errors: any) => {
                const firstError = Object.values(errors)[0] as string;
                toast.error("提交失败", {
                    description: firstError || "服务器繁忙，请稍后再试。",
                });
            },
        });
    };

    // 打开纠错反馈弹窗
    const handleOpenFeedback = (item: DownloadItem) => {
        requireAuth(() => {
            setSelectedFeedbackDownload(item);
            feedbackForm.setData({
                type: 1,
                content: '',
            });
            feedbackForm.clearErrors();
            setFeedbackOpen(true);
        });
    };

    // 提交纠错反馈
    const handleFeedbackSubmit = () => {
        if (!video?.id || !selectedFeedbackDownload?.id) {
            toast.error("资源信息缺失，无法提交反馈");
            return;
        }

        feedbackForm.post(
            VideoDownloadFeedbackController.store.url({
                video: video.id,
                slug: video.slug,
                download: selectedFeedbackDownload.id,
            }),
            {
                preserveScroll: true,
                onSuccess: (response: any) => {
                    const serverMessage = response?.data?.message || response?.message || "纠错反馈提交成功！感谢您的指正与贡献，我们会尽快核查处理。";
                    toast.success(serverMessage, {
                        duration: 5000,
                    });
                    setFeedbackOpen(false);
                    feedbackForm.reset();
                },
                onError: (errors: any) => {
                    const firstError = Object.values(errors)[0] as string;
                    toast.error("提交失败", {
                        description: firstError || "服务器繁忙，请稍后再试。",
                    });
                },
            }
        );
    };

    return (
        <>
            <Button
                variant="secondary"
                className={`rounded-full px-3 sm:px-3.5 h-9 shadow-none gap-1.5 shrink-0 font-medium text-xs sm:text-sm transition-colors cursor-pointer ${
                    downloads.length > 0
                        ? "bg-emerald-500/10 text-emerald-600 hover:bg-emerald-500/20 dark:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-500/20"
                        : "bg-secondary text-secondary-foreground hover:bg-secondary/80"
                }`}
                onClick={handleOpenDialog}
            >
                <Download className="w-4 h-4" />
                <span>下载{downloads.length > 0 ? ` (${downloads.length})` : ''}</span>
            </Button>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent
                    className="w-[95vw] sm:max-w-3xl lg:max-w-4xl rounded-2xl p-5 sm:p-6 max-h-[90vh] flex flex-col"
                    onInteractOutside={(e) => { if (feedbackOpen) e.preventDefault(); }}
                    onEscapeKeyDown={(e) => { if (feedbackOpen) e.preventDefault(); }}
                >
                    <DialogHeader className="flex flex-row items-center justify-between border-b pb-4 min-w-0 w-full shrink-0">
                        <DialogTitle className="text-xl font-bold tracking-tight flex items-center gap-2 truncate">
                            {activeView === 'submit' && downloads.length > 0 && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 rounded-full mr-1 cursor-pointer shrink-0"
                                    onClick={() => setActiveView('list')}
                                >
                                    <ArrowLeft className="w-4 h-4" />
                                </Button>
                            )}
                            <span>{activeView === 'list' ? '资源获取' : '提交下载资源'}</span>
                        </DialogTitle>

                        {activeView === 'list' && (
                            <div className="mr-8 sm:mr-10 shrink-0">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="h-8 gap-1.5 text-xs font-semibold rounded-lg border-dashed cursor-pointer"
                                    onClick={() => setActiveView('submit')}
                                >
                                    <Plus className="w-3.5 h-3.5" />
                                    贡献/补充链接
                                </Button>
                            </div>
                        )}
                    </DialogHeader>

                    <div className="flex-1 min-w-0 w-full overflow-hidden flex flex-col mt-3">
                        {/* 视图 1：已有的下载资源列表 */}
                        {activeView === 'list' && (
                            <div className="space-y-3 overflow-y-auto overflow-x-hidden pr-1.5 flex-1 min-w-0 w-full max-h-[60vh]">
                                {downloads.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 p-3.5 sm:p-4 rounded-xl border border-border/80 bg-card hover:bg-muted/40 transition-all duration-200 min-w-0 w-full"
                                    >
                                        <div className="flex items-start gap-3.5 min-w-0 flex-1">
                                            <div className={`p-2.5 rounded-xl shrink-0 mt-0.5 ${item.type === 'magnet' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400'}`}>
                                                {item.type === 'magnet' ? <Magnet className="w-5 h-5 text-blue-600 dark:text-blue-400" /> : <ShoppingBag className="w-5 h-5 text-orange-600 dark:text-orange-400" />}
                                            </div>

                                            <div className="flex flex-col min-w-0 flex-1 justify-center gap-1.5">
                                                {/* 1. 状态与标签行 (清晰度、文件大小、类型) */}
                                                <div className="flex items-center gap-2 flex-wrap">
                                                    {getResolutionBadge(item.resolution)}

                                                    {formatFileSize(item.fileSize) && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-slate-600 dark:text-slate-300 bg-muted/60 dark:bg-muted/40 border-border/70 px-2 py-0.5 text-xs font-mono font-medium shrink-0 inline-flex items-center gap-1"
                                                        >
                                                            <HardDrive className="w-3 h-3 text-muted-foreground" />
                                                            <span>{formatFileSize(item.fileSize)}</span>
                                                        </Badge>
                                                    )}

                                                    {item.costType === 'free' ? (
                                                        <Badge variant="outline" className="text-emerald-600 bg-emerald-500/10 border-emerald-500/20 px-2 py-0.5 text-xs font-medium shrink-0">
                                                            免费
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="text-orange-600 bg-orange-500/10 border-orange-500/20 px-2 py-0.5 text-xs font-medium shrink-0">
                                                            付费
                                                        </Badge>
                                                    )}

                                                    <span className="text-[11px] text-muted-foreground font-mono">
                                                        {item.type === 'magnet' ? 'BT 磁力' : item.type === 'store' ? '官方商城' : '下载链接'}
                                                    </span>
                                                </div>

                                                {/* 2. 磁力文件名/标题行 (独立完整展现，不被省略号截断，支持双击选择) */}
                                                <div
                                                    className="font-medium text-foreground text-sm sm:text-[15px] tracking-tight leading-snug break-all select-all font-mono"
                                                    title={item.title}
                                                >
                                                    {item.title}
                                                </div>

                                                {item.description && (
                                                    <span className="text-xs text-muted-foreground line-clamp-2 break-all">{item.description}</span>
                                                )}
                                                {item.type === 'store' && item.price && (
                                                    <span className="text-sm font-bold text-orange-600 dark:text-orange-400">
                                                        {item.price}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-1.5 sm:gap-2 shrink-0 w-full sm:w-auto mt-2 sm:mt-0">
                                            {item.type === 'magnet' ? (
                                                <>
                                                    <Button
                                                        asChild
                                                        variant="default"
                                                        size="sm"
                                                        className="flex-1 sm:flex-none h-8 sm:h-9 px-3 font-medium text-xs sm:text-sm shadow-none cursor-pointer"
                                                    >
                                                        <a href={item.link}>
                                                            <Zap className="w-3.5 h-3.5 mr-1" />
                                                            直接下载
                                                        </a>
                                                    </Button>

                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="flex-1 sm:flex-none h-8 sm:h-9 px-3 font-medium text-xs sm:text-sm shadow-none cursor-pointer"
                                                        onClick={() => handleCopy(item.link)}
                                                    >
                                                        <Copy className="w-3.5 h-3.5 mr-1" />
                                                        复制
                                                    </Button>
                                                </>
                                            ) : (
                                                <Button
                                                    asChild
                                                    variant="default"
                                                    size="sm"
                                                    className="flex-1 sm:flex-none h-8 sm:h-9 px-4 font-medium text-xs sm:text-sm shadow-none cursor-pointer"
                                                >
                                                    <a href={item.link} target="_blank" rel="noopener noreferrer">
                                                        <ExternalLink className="w-3.5 h-3.5 mr-1.5" />
                                                        前往获取
                                                    </a>
                                                </Button>
                                            )}

                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="h-8 sm:h-9 px-2 sm:px-2.5 font-normal text-xs text-muted-foreground hover:text-amber-600 hover:bg-amber-500/10 dark:hover:text-amber-400 dark:hover:bg-amber-500/20 shrink-0 cursor-pointer"
                                                onClick={() => handleOpenFeedback(item)}
                                                title="资源纠错反馈"
                                            >
                                                <AlertCircle className="w-3.5 h-3.5 sm:mr-1" />
                                                <span>报错</span>
                                            </Button>
                                        </div>
                                    </div>
                                ))}

                                <div className="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 rounded-xl bg-muted/40 text-sm text-muted-foreground border border-dashed gap-2 min-w-0 w-full">
                                    <span className="font-normal text-foreground/80">没有找到你想要的清晰度或下载格式？</span>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        className="h-auto p-0 text-sm font-semibold text-primary hover:underline self-start sm:self-auto cursor-pointer"
                                        onClick={() => setActiveView('submit')}
                                    >
                                        提交新资源获奖励 &rarr;
                                    </Button>
                                </div>
                            </div>
                        )}

                        {/* 视图 2：提交新资源表单 */}
                        {activeView === 'submit' && (
                            <div className="flex flex-col p-1 gap-4">
                                {downloads.length === 0 && (
                                    <div className="text-center mb-1">
                                        <p className="text-lg font-bold text-foreground mb-1">暂无下载链接</p>
                                        <p className="text-sm text-muted-foreground">如果您拥有该资源的下载方式，欢迎提交分享给其他用户。</p>
                                    </div>
                                )}

                                <div className="w-full flex items-start gap-3 p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-800 dark:text-amber-300">
                                    <Gift className="w-5 h-5 shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" />
                                    <div className="text-sm leading-relaxed">
                                        <span className="font-bold">分享有赏：</span>
                                        提交下载信息审核通过后会给予您相应的奖励，感谢您为社区做出的贡献！
                                    </div>
                                </div>

                                <div className="w-full space-y-3.5">
                                    <div className="flex gap-2 p-1 bg-muted rounded-xl text-sm font-medium">
                                        <button
                                            type="button"
                                            className={`flex-1 py-2 rounded-lg transition-colors flex items-center justify-center gap-2 ${submitType === 'magnet' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground'}`}
                                            onClick={() => setSubmitType('magnet')}
                                        >
                                            <Magnet className="w-4 h-4" />
                                            磁力链接
                                        </button>
                                        <button
                                            type="button"
                                            className={`flex-1 py-2 rounded-lg transition-colors flex items-center justify-center gap-2 ${submitType === 'link' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground'}`}
                                            onClick={() => setSubmitType('link')}
                                        >
                                            <LinkIcon className="w-4 h-4" />
                                            第三方链接
                                        </button>
                                        <button
                                            type="button"
                                            className={`flex-1 py-2 rounded-lg transition-colors flex items-center justify-center gap-2 ${submitType === 'torrent' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground'}`}
                                            onClick={() => setSubmitType('torrent')}
                                        >
                                            <FileUp className="w-4 h-4" />
                                            上传种子文件
                                        </button>
                                    </div>

                                    {/* 1. 磁力链接 */}
                                    {submitType === 'magnet' && (
                                        <textarea
                                            autoFocus
                                            className="w-full min-h-[90px] p-3.5 rounded-xl border border-input bg-background text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-y leading-relaxed"
                                            placeholder="请在此粘贴磁力链接 (magnet:?xt=urn:btih:...)"
                                            value={submitContent}
                                            onChange={(e) => setSubmitContent(e.target.value)}
                                        />
                                    )}

                                    {/* 2. 第三方下载链接 */}
                                    {submitType === 'link' && (
                                        <Input
                                            autoFocus
                                            className="h-10 text-sm rounded-lg"
                                            placeholder="请输入网盘或第三方下载链接 (例: https://pan.baidu.com/s/...)"
                                            value={submitContent}
                                            onChange={(e) => setSubmitContent(e.target.value)}
                                        />
                                    )}

                                    {/* 3. 种子文件上传 */}
                                    {submitType === 'torrent' && (
                                        <div className="flex flex-col items-center justify-center border-2 border-dashed border-input rounded-xl p-4 bg-background hover:bg-muted/30 transition-colors">
                                            <Upload className="w-8 h-8 text-muted-foreground mb-2" />
                                            <label className="cursor-pointer text-sm font-semibold text-primary hover:underline">
                                                <span>{torrentFile ? torrentFile.name : "点击选择上传 .torrent 种子文件"}</span>
                                                <input
                                                    type="file"
                                                    accept=".torrent"
                                                    className="hidden"
                                                    onChange={(e) => {
                                                        if (e.target.files && e.target.files[0]) {
                                                            setTorrentFile(e.target.files[0]);
                                                        }
                                                    }}
                                                />
                                            </label>
                                        </div>
                                    )}

                                    {/* 提取码与解压密码分栏 */}
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                        <Input
                                            className="h-10 text-sm rounded-lg"
                                            placeholder="网盘提取码 (选填，如 a1b2)"
                                            value={extractionCode}
                                            onChange={(e) => setExtractionCode(e.target.value)}
                                        />
                                        <Input
                                            className="h-10 text-sm rounded-lg"
                                            placeholder="文件解压密码 (选填)"
                                            value={archivePassword}
                                            onChange={(e) => setArchivePassword(e.target.value)}
                                        />
                                    </div>

                                    {/* 备注说明 */}
                                    <Input
                                        className="h-10 text-sm rounded-lg"
                                        placeholder="备注说明 (选填，如：内含双语字幕、解压需用 7-Zip 等)"
                                        value={remark}
                                        onChange={(e) => setRemark(e.target.value)}
                                    />

                                    {/* 操作按钮 */}
                                    <div className="flex items-center justify-end gap-3 w-full pt-3 border-t">
                                        {downloads.length > 0 && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-9 px-4 text-sm"
                                                onClick={() => setActiveView('list')}
                                                disabled={processing}
                                            >
                                                返回列表
                                            </Button>
                                        )}
                                        <Button
                                            variant="default"
                                            size="sm"
                                            className="h-9 px-4 text-sm font-semibold"
                                            onClick={handleSubmit}
                                            disabled={processing}
                                        >
                                            {processing ? "提交中..." : "提交下载信息"}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* 纠错反馈弹窗 */}
            <Dialog open={feedbackOpen} onOpenChange={setFeedbackOpen}>
                <DialogContent
                    className="sm:max-w-md w-[95vw] rounded-2xl p-5 sm:p-6"
                    onPointerDown={(e) => e.stopPropagation()}
                    onClick={(e) => e.stopPropagation()}
                >
                    <DialogHeader className="space-y-1.5 pb-3 border-b">
                        <DialogTitle className="text-base sm:text-lg font-bold flex items-center gap-2">
                            <AlertCircle className="w-5 h-5 text-amber-500 shrink-0" />
                            <span>下载资源纠错反馈</span>
                        </DialogTitle>
                        <DialogDescription className="text-xs text-muted-foreground">
                            如发现链接失效、画质不符或资源错误，欢迎向我们反馈，我们会尽快核验修复。
                        </DialogDescription>
                    </DialogHeader>

                    {selectedFeedbackDownload && (
                        <div className="mt-3 p-3 rounded-xl bg-muted/50 border border-border/60 text-xs space-y-1.5">
                            <div className="flex items-center gap-2 flex-wrap">
                                {getResolutionBadge(selectedFeedbackDownload.resolution)}
                                <span className="font-mono text-muted-foreground">
                                    {selectedFeedbackDownload.type === 'magnet' ? 'BT 磁力' : selectedFeedbackDownload.type === 'store' ? '官方商城' : '下载链接'}
                                </span>
                                {formatFileSize(selectedFeedbackDownload.fileSize) && (
                                    <span className="font-mono text-muted-foreground">
                                        • {formatFileSize(selectedFeedbackDownload.fileSize)}
                                    </span>
                                )}
                            </div>
                            <div className="font-mono font-medium text-foreground text-xs line-clamp-2 break-all">
                                {selectedFeedbackDownload.title}
                            </div>
                        </div>
                    )}

                    <div className="space-y-3 mt-3">
                        <div>
                            <label className="text-xs font-semibold text-foreground mb-1.5 block">
                                问题类型 <span className="text-destructive">*</span>
                            </label>
                            <div className="grid grid-cols-2 gap-2">
                                {FEEDBACK_TYPES.map((typeOption) => {
                                    const isSelected = feedbackForm.data.type === typeOption.value;
                                    return (
                                        <button
                                            key={typeOption.value}
                                            type="button"
                                            onClick={() => {
                                                feedbackForm.setData('type', typeOption.value);
                                                feedbackForm.clearErrors('type');
                                            }}
                                            className={`text-left p-2.5 rounded-xl border transition-all text-xs cursor-pointer flex flex-col gap-0.5 ${
                                                isSelected
                                                    ? 'border-amber-500 bg-amber-500/10 text-amber-900 dark:text-amber-200 ring-1 ring-amber-500/50'
                                                    : 'border-border/70 hover:bg-muted/50 text-foreground/80'
                                            }`}
                                        >
                                            <span className="font-medium text-xs flex items-center justify-between">
                                                <span>{typeOption.label}</span>
                                                {isSelected && <span className="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" />}
                                            </span>
                                            <span className="text-[10px] text-muted-foreground line-clamp-1">
                                                {typeOption.desc}
                                            </span>
                                        </button>
                                    );
                                })}
                            </div>
                            {feedbackForm.errors.type && (
                                <p className="text-xs text-destructive mt-1">{feedbackForm.errors.type}</p>
                            )}
                        </div>

                        <div>
                            <div className="flex items-center justify-between mb-1.5">
                                <label className="text-xs font-semibold text-foreground">
                                    补充描述 <span className="text-muted-foreground font-normal">(选填)</span>
                                </label>
                                <span className="text-[11px] text-muted-foreground">
                                    {feedbackForm.data.content.length} / 1000
                                </span>
                            </div>
                            <Textarea
                                placeholder="如：哪一分钟花屏、迅雷提示什么错误、有效解压密码线索等..."
                                value={feedbackForm.data.content}
                                maxLength={1000}
                                onChange={(e) => {
                                    feedbackForm.setData('content', e.target.value);
                                    feedbackForm.clearErrors('content');
                                }}
                                className="min-h-[80px] max-h-[160px] text-xs resize-none rounded-xl"
                            />
                            {feedbackForm.errors.content && (
                                <p className="text-xs text-destructive mt-1">{feedbackForm.errors.content}</p>
                            )}
                        </div>
                    </div>

                    <DialogFooter className="flex sm:justify-end gap-2 mt-4 pt-3 border-t">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setFeedbackOpen(false)}
                            disabled={feedbackForm.processing}
                            className="cursor-pointer"
                        >
                            取消
                        </Button>
                        <Button
                            variant="default"
                            size="sm"
                            onClick={handleFeedbackSubmit}
                            disabled={feedbackForm.processing}
                            className="bg-amber-600 hover:bg-amber-700 text-white cursor-pointer"
                        >
                            {feedbackForm.processing ? (
                                <>
                                    <Loader2 className="w-3.5 h-3.5 mr-1.5 animate-spin" />
                                    提交中...
                                </>
                            ) : (
                                '提交纠错反馈'
                            )}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
