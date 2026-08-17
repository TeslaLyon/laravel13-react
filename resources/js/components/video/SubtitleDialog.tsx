import React, { useState, useRef } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Captions, Download, MessageSquare, User, Plus, FileUp, Sparkles, Upload, FileText, X, ExternalLink, Loader2 } from "lucide-react";
import { toast } from 'sonner';
import { useHttp } from '@inertiajs/react';
import VideoController from '@/actions/App/Http/Controllers/VideoController';
import VideoSubtitleFeedbackController from '@/actions/App/Http/Controllers/VideoSubtitleFeedbackController';
import { Textarea } from '@/components/ui/textarea';
import { useRequireAuth } from '@/components/require-auth-provider';

interface SubtitleDialogProps {
    video: any;
}

export function SubtitleDialog({ video }: SubtitleDialogProps) {
    const [open, setOpen] = useState(false);
    const [uploadMethod, setUploadMethod] = useState<'file' | 'url'>('file');

    const [downloadingId, setDownloadingId] = useState<number | string | null>(null);

    // 控制在有字幕列表时是否展开上传/补充表单
    const [showUploadForm, setShowUploadForm] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    // 反馈弹窗的状态控制
    const [feedbackOpen, setFeedbackOpen] = useState(false);
    const [selectedSubtitle, setSelectedSubtitle] = useState<any>(null);

    // 1. 获取全局登录拦截方法
    const { requireAuth } = useRequireAuth();

    // 2. 封装打开字幕弹窗前的登录检测逻辑
    const handleOpenDialog = () => {
        requireAuth(() => {
            setOpen(true);
        });
    };

    const supportedFormats = ['.srt', '.ass', '.ssa', '.vtt', '.sub', '.idx', '.sbv', '.stl'];
    const MAX_FILE_SIZE = 5 * 1024 * 1024;

    // 上传表单 HTTP Hook
    const uploadForm = useHttp({
        upload_type: 'file' as 'file' | 'url',
        title: '',
        file: null as File | null,
        url: '',
    });

    const requestForm = useHttp({});

    // 反馈表单 HTTP Hook
    const feedbackForm = useHttp({
        content: '',
    });

    // 下载/获取链接请求专用 HTTP Hook
    const downloadHttp = useHttp({});

    // 字节大小转换显示 (不足 1MB 显示 KB，超过显示 MB)
    const formatFileSize = (bytes: number) => {
        if (!bytes || bytes === 0) return '0 KB';
        const megabyte = 1024 * 1024;
        const kilobyte = 1024;

        if (bytes >= megabyte) {
            return (bytes / megabyte).toFixed(2) + ' MB';
        }
        return (bytes / kilobyte).toFixed(1) + ' KB';
    };

    // 语种映射字典
    const languageMap: Record<string, string> = {
        'zh_CN': '简体中文',
        'zh_TW': '繁体中文',
        'en_US': 'English',
    };

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        if (e.target.files && e.target.files.length > 0) {
            const selectedFile = e.target.files[0];
            if (selectedFile.size > MAX_FILE_SIZE) {
                toast.error("文件过大", { description: "字幕文件大小不能超过 5MB", duration: 10000 });
                return;
            }
            const fileExtension = selectedFile.name.substring(selectedFile.name.lastIndexOf('.')).toLowerCase();
            if (!supportedFormats.includes(fileExtension)) {
                toast.error("格式不支持", { description: `仅支持：${supportedFormats.join(', ')}` });
                return;
            }
            uploadForm.setData('file', selectedFile);
        }
    };

    const clearSelectedFile = () => {
        uploadForm.setData('file', null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleMethodSwitch = (method: 'file' | 'url') => {
        setUploadMethod(method);
        uploadForm.setData('upload_type', method);
        uploadForm.clearErrors();
    };

    const handleUpload = () => {
        if (!video) {
            toast.error("视频信息加载失败，请刷新页面重试");
            return;
        }
        if (uploadMethod === 'file' && !uploadForm.data.file) {
            toast.error("请先选择一个字幕文件");
            return;
        }
        if (uploadMethod === 'url' && !uploadForm.data.url) {
            toast.error("请填写有效的字幕下载链接");
            return;
        }

        uploadForm.transform((data) => {
            if (data.upload_type === 'file') {
                return { upload_type: data.upload_type, title: data.title, file: data.file };
            }
            return { upload_type: data.upload_type, title: data.title, url: data.url };
        });

        uploadForm.post(VideoController.subtitleUpload.url({ video: video.id, slug: video.slug }), {
            onSuccess: () => {
                toast.success("字幕信息提交成功，已进入审核！", {
                    description: "感谢您的贡献，审核通过后我们将发放对应奖励。",
                });
                uploadForm.reset();
                setShowUploadForm(false);
                setOpen(false);
            },
            onError: (errors: Record<string, string | string[]>) => {
                const errorMessage = errors.file || errors.url || errors.title || "提交失败，请检查输入的信息。";
                toast.error(errorMessage as string);
            },
        });
    };

    const handleApply = () => {
        if (!video) {
            toast.error("视频信息加载失败，请刷新页面重试");
            return;
        }
        requestForm.post(VideoController.subtitleRequest.url({ video: video.id, slug: video.slug }), {
            onSuccess: () => {
                toast.success("字幕翻译申请已提交！", {
                    description: "当有中文字幕可供下载时，我们将通过站内消息通知您。",
                    duration: 8000,
                });
                setOpen(false);
            },
            onError: () => {
                toast.error("提交申请失败，可能您已经提交过申请。");
            },
        });
    };

    // 处理字幕下载请求
    const handleDownloadSubtitle = (subtitle: any) => {
        setDownloadingId(subtitle.id);

        downloadHttp.post(
            VideoController.subtitleDownload.url({
                video: video.id,
                slug: video.slug,
                subtitle: subtitle.id,
            }),
            {
                onSuccess: (page: any) => {
                    const responseData = page?.props?.downloadData || page;
                    const url = responseData?.url || page?.url;

                    if (!url) {
                        toast.error("未获取到有效的字幕下载地址");
                        return;
                    }

                    if (responseData?.type === 'url' || subtitle.is_external) {
                        window.open(url, '_blank', 'noopener,noreferrer');
                        return;
                    }

                    const extMatch = url.match(/\.([a-zA-Z0-9]+)(\?.*)?$/);
                    const extension = extMatch ? `.${extMatch[1]}` : (subtitle.format ? `.${subtitle.format}` : '.srt');
                    const downloadFileName = `${video.title || 'video'}_${subtitle.title || languageMap[subtitle.language] || '字幕'}${extension}`;

                    const link = document.createElement('a');
                    link.href = url;
                    link.download = downloadFileName;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                },
                onError: (errors: any) => {
                    const errorMessage = typeof errors === 'string' ? errors : (errors?.message || "获取字幕下载链接失败，请稍后重试");
                    toast.error(errorMessage);
                },
                onFinish: () => {
                    setDownloadingId(null);
                },
            }
        );
    };

    // 打开反馈弹窗
    const openFeedbackDialog = (subtitle: any) => {
        setSelectedSubtitle(subtitle);
        setFeedbackOpen(true);
    };

    // 处理反馈提交
    const handleFeedbackSubmit = () => {
        if (!feedbackForm.data.content.trim()) {
            toast.error("请填写反馈内容");
            return;
        }

        feedbackForm.post(VideoSubtitleFeedbackController.store.url({ video: video.id, slug: video.slug, subtitle: selectedSubtitle.id }), {
            onSuccess: () => {
                toast.success("反馈已提交！", { description: "感谢您的反馈，我们会尽快核实处理。" });
                feedbackForm.reset();
                setFeedbackOpen(false);
            },
            onError: (errors) => {
                const errorMessage = errors.content || "提交失败，请重试。";
                toast.error(errorMessage);
            }
        });
    };

    const hasApprovedSubtitles = video?.approved_subtitles && video.approved_subtitles.length > 0;

    return (
        <>
            <Dialog open={open} onOpenChange={setOpen}>
                {/* 3. 修改处：移除 DialogTrigger，改成带有 onClick={handleOpenDialog} 的普通按钮 */}
                <Button
                    variant="secondary"
                    className="gap-1.5 h-9 px-3 sm:px-4 rounded-full text-xs sm:text-sm font-medium"
                    onClick={handleOpenDialog}
                >
                    <Captions className="w-4 h-4" />
                    字幕
                </Button>

                <DialogContent
                    className="sm:max-w-md max-h-[85vh] flex flex-col"
                    onInteractOutside={(e) => { if (feedbackOpen) e.preventDefault(); }}
                    onEscapeKeyDown={(e) => { if (feedbackOpen) e.preventDefault(); }}
                >
                    <DialogHeader>
                        <DialogTitle>视频字幕</DialogTitle>
                        <DialogDescription>获取当前视频的中文字幕，或帮助社区贡献一份力量。</DialogDescription>
                    </DialogHeader>

                    <div className="flex-1 overflow-y-auto pr-1 py-2 flex flex-col gap-4">
                        {/* 1. 字幕列表显示区域 */}
                        {hasApprovedSubtitles && (
                            <div className="flex flex-col gap-3">
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-muted-foreground">已可用的字幕 ({video.approved_subtitles.length})</span>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        className="h-7 text-xs text-primary hover:text-primary/80 gap-1 px-2"
                                        onClick={() => setShowUploadForm(!showUploadForm)}
                                    >
                                        <Plus className="w-3.5 h-3.5" />
                                        {showUploadForm ? "收起补充提交" : "补充新字幕"}
                                    </Button>
                                </div>

                                {video.approved_subtitles.map((subtitle: any) => {
                                    const isThisDownloading = downloadingId === subtitle.id;

                                    return (
                                        <div
                                            key={subtitle.id}
                                            className="p-3.5 bg-muted/20 hover:bg-muted/40 rounded-xl border border-border/60 transition-all flex flex-col gap-3 group"
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="flex items-center gap-3 min-w-0">
                                                    <div className="w-9 h-9 rounded-full bg-secondary flex-shrink-0 flex items-center justify-center overflow-hidden border border-border/80">
                                                        {subtitle.user?.avatar ? (
                                                            <img src={subtitle.user.avatar} alt={subtitle.user.nickname} className="w-full h-full object-cover" />
                                                        ) : (
                                                            <User className="w-4.5 h-4.5 text-muted-foreground" />
                                                        )}
                                                    </div>

                                                    <div className="flex flex-col min-w-0">
                                                        <div className="flex items-center gap-1.5 flex-wrap">
                                                            <span className="font-semibold text-sm text-foreground truncate">
                                                                {subtitle.title || languageMap[subtitle.language] || subtitle.language || '未命名字幕'}
                                                            </span>
                                                            {subtitle.title && (
                                                                <span className="text-[10px] px-1.5 py-0.2 rounded bg-primary/10 text-primary border border-primary/20">
                                                                    {languageMap[subtitle.language] || subtitle.language}
                                                                </span>
                                                            )}
                                                            {subtitle.format && (
                                                                <span className="text-[10px] font-mono px-1.5 py-0.2 rounded bg-muted text-muted-foreground uppercase border border-border/50">
                                                                    {subtitle.format}
                                                                </span>
                                                            )}
                                                            {subtitle.is_external && (
                                                                <span className="text-[10px] px-1.5 py-0.2 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20">
                                                                    外链
                                                                </span>
                                                            )}
                                                        </div>
                                                        <span className="text-xs text-muted-foreground mt-0.5 truncate">
                                                            贡献者: <span className="font-medium text-foreground/80">{subtitle.user?.nickname || '匿名用户'}</span>
                                                        </span>
                                                    </div>
                                                </div>

                                                <div className="text-right flex-shrink-0">
                                                    <span className="inline-block text-xs font-mono font-medium px-2 py-0.5 rounded-full bg-muted text-muted-foreground">
                                                        {formatFileSize(subtitle.file_size)}
                                                    </span>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-end gap-2 pt-2.5 border-t border-border/40">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-8 text-xs text-muted-foreground hover:text-foreground hover:bg-muted/60"
                                                    onClick={() => openFeedbackDialog(subtitle)}
                                                >
                                                    <MessageSquare className="w-3.5 h-3.5 mr-1" />
                                                    反馈错误
                                                </Button>

                                                <Button
                                                    size="sm"
                                                    className="h-8 gap-1 text-xs px-3 shadow-none"
                                                    onClick={() => handleDownloadSubtitle(subtitle)}
                                                    disabled={downloadingId !== null}
                                                >
                                                    {isThisDownloading ? (
                                                        <Loader2 className="w-3.5 h-3.5 mr-0.5 animate-spin" />
                                                    ) : subtitle.is_external ? (
                                                        <ExternalLink className="w-3.5 h-3.5 mr-0.5" />
                                                    ) : (
                                                        <Download className="w-3.5 h-3.5 mr-0.5" />
                                                    )}
                                                    {isThisDownloading ? "请求中..." : (subtitle.is_external ? "打开外链" : "下载字幕")}
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}

                        {/* 2. 字幕上传/补充提交表单 */}
                        {(!hasApprovedSubtitles || showUploadForm) && (
                            <div className={`flex flex-col gap-3 ${hasApprovedSubtitles ? 'pt-3 border-t border-dashed border-border' : ''}`}>
                                <div className="flex items-center justify-between">
                                    <span className="text-xs font-semibold text-foreground flex items-center gap-1">
                                        <FileUp className="w-3.5 h-3.5" />
                                        {hasApprovedSubtitles ? "补充提交新字幕" : "上传/提供字幕"}
                                    </span>
                                </div>

                                <div className="flex bg-muted/50 p-1 rounded-lg border border-border/60">
                                    <button
                                        type="button"
                                        className={`flex-1 text-xs py-1.5 rounded-md font-medium transition-all ${uploadMethod === 'file' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground'}`}
                                        onClick={() => handleMethodSwitch('file')}
                                    >
                                        本地上传
                                    </button>
                                    <button
                                        type="button"
                                        className={`flex-1 text-xs py-1.5 rounded-md font-medium transition-all ${uploadMethod === 'url' ? 'bg-background shadow-sm text-foreground' : 'text-muted-foreground'}`}
                                        onClick={() => handleMethodSwitch('url')}
                                    >
                                        网络链接
                                    </button>
                                </div>

                                <div className="p-0.5">
                                    <input
                                        type="text"
                                        placeholder="字幕说明/标题（可选，如：双语精译版、纯英字幕等）"
                                        value={uploadForm.data.title}
                                        onChange={(e) => uploadForm.setData('title', e.target.value)}
                                        className="w-full px-3 py-1.5 text-xs rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-primary/80 focus:border-primary transition-all focus:relative focus:z-10"
                                    />
                                </div>

                                {uploadMethod === 'file' ? (
                                    <div className="flex flex-col gap-2">
                                        <input
                                            ref={fileInputRef}
                                            type="file"
                                            id="subtitle-file-input"
                                            accept={supportedFormats.join(',')}
                                            onChange={handleFileChange}
                                            className="hidden"
                                        />

                                        {!uploadForm.data.file ? (
                                            <label
                                                htmlFor="subtitle-file-input"
                                                className="flex flex-col items-center justify-center p-5 border-2 border-dashed border-border hover:border-primary/50 bg-muted/20 hover:bg-muted/40 rounded-xl cursor-pointer transition-all gap-2 group"
                                            >
                                                <div className="w-10 h-10 rounded-full bg-background flex items-center justify-center shadow-sm border border-border/60 group-hover:scale-105 transition-transform">
                                                    <Upload className="w-5 h-5 text-muted-foreground group-hover:text-primary transition-colors" />
                                                </div>
                                                <div className="flex flex-col items-center text-center">
                                                    <span className="text-xs font-medium text-foreground">
                                                        点击选择 或 将文件拖拽至此处
                                                    </span>
                                                    <span className="text-[11px] text-muted-foreground mt-0.5">
                                                        支持 {supportedFormats.join(', ')}（单个最大 5MB）
                                                    </span>
                                                </div>
                                            </label>
                                        ) : (
                                            <div className="flex items-center justify-between p-3 border border-primary/30 bg-primary/5 rounded-xl">
                                                <div className="flex items-center gap-2.5 min-w-0">
                                                    <div className="w-8 h-8 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                                                        <FileText className="w-4 h-4 text-primary" />
                                                    </div>
                                                    <div className="flex flex-col min-w-0">
                                                        <span className="text-xs font-medium text-foreground truncate">
                                                            {uploadForm.data.file.name}
                                                        </span>
                                                        <span className="text-[10px] text-muted-foreground">
                                                            {formatFileSize(uploadForm.data.file.size)}
                                                        </span>
                                                    </div>
                                                </div>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-7 w-7 text-muted-foreground hover:text-foreground rounded-full flex-shrink-0"
                                                    onClick={clearSelectedFile}
                                                >
                                                    <X className="w-3.5 h-3.5" />
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex flex-col gap-2 p-0.5">
                                        <input
                                            type="url"
                                            placeholder="请输入字幕文件的下载/网盘链接..."
                                            value={uploadForm.data.url}
                                            onChange={(e) => uploadForm.setData('url', e.target.value)}
                                            className="w-full px-3 py-2 text-xs rounded-md border border-input bg-background focus:outline-none focus:ring-2 focus:ring-primary/80 focus:border-primary transition-all focus:relative focus:z-10"
                                        />
                                    </div>
                                )}

                                <Button size="sm" onClick={handleUpload} disabled={uploadForm.processing} className="w-full h-8 text-xs">
                                    {uploadForm.processing ? "提交中..." : "确认提交字幕"}
                                </Button>
                            </div>
                        )}

                        {/* 3. 无字幕时的申请求字幕区 */}
                        {!hasApprovedSubtitles && !showUploadForm && (
                            <div className="flex flex-col items-center justify-center p-6 bg-muted/20 border border-dashed rounded-xl gap-3 text-center">
                                <Sparkles className="w-8 h-8 text-muted-foreground/60" />
                                <div className="flex flex-col gap-1">
                                    <span className="text-sm font-medium">暂无可用字幕</span>
                                    <span className="text-xs text-muted-foreground">您可以申请翻译，或直接上传字幕帮助社区。</span>
                                </div>
                                <Button variant="outline" size="sm" onClick={handleApply} disabled={requestForm.processing} className="h-8 text-xs mt-1">
                                    求字幕 / 申请翻译
                                </Button>
                            </div>
                        )}
                    </div>
                </DialogContent>
            </Dialog>

            {/* 反馈信息填写弹窗 */}
            <Dialog open={feedbackOpen} onOpenChange={setFeedbackOpen}>
                <DialogContent
                    className="sm:max-w-sm"
                    onPointerDown={(e) => e.stopPropagation()}
                    onClick={(e) => e.stopPropagation()}
                >
                    <DialogHeader>
                        <DialogTitle className="text-base">字幕反馈</DialogTitle>
                        <DialogDescription className="text-xs">
                            如果字幕存在时间轴未对齐、翻译错误或无法解压等问题，请告诉我们。
                        </DialogDescription>
                    </DialogHeader>

                    <div className="py-2">
                        <Textarea
                            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 min-h-[100px] resize-none"
                            placeholder="请简要描述您遇到的问题..."
                            value={feedbackForm.data.content}
                            onChange={(e) => {
                                feedbackForm.setData('content', e.target.value);
                                feedbackForm.clearErrors('content');
                            }}
                        />
                        {feedbackForm.errors.content && (
                            <p className="text-xs text-red-500 mt-1.5">{feedbackForm.errors.content}</p>
                        )}
                    </div>

                    <DialogFooter className="sm:justify-end sm:space-x-2 gap-2 mt-4">
                        <Button
                            variant="outline"
                            onClick={(e) => {
                                e.preventDefault();
                                e.stopPropagation();
                                setFeedbackOpen(false);
                            }}
                            disabled={feedbackForm.processing}
                        >
                            取消
                        </Button>
                        <Button
                            onClick={handleFeedbackSubmit}
                            disabled={feedbackForm.processing || !feedbackForm.data.content.trim()}
                        >
                            {feedbackForm.processing ? "提交中..." : "确认提交"}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
