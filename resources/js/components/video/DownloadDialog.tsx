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
    ArrowLeft
} from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { Input } from "@/components/ui/input";

// 1. 引入 Inertia 与 toast
import { useHttp } from "@inertiajs/react";
import { toast } from "sonner";
import { Video } from "@/types/video";
import VideoDownloadSubmissionController from '@/actions/App/Http/Controllers/VideoDownloadSubmissionController';

// 2. 引入全局登录拦截 Hook
import { useRequireAuth } from '@/components/require-auth-provider';

export interface DownloadItem {
    id: number;
    type: 'magnet' | 'store' | 'link';
    costType: 'free' | 'paid';
    title: string;
    description: string;
    resolution?: '4K' | '1080P' | '720P';
    price?: string;
    link: string;
}

interface DownloadDialogProps {
    video?: Video;
}

export function DownloadDialog({ video }: DownloadDialogProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [activeView, setActiveView] = useState<'list' | 'submit'>('list');

    const [submitType, setSubmitType] = useState<'magnet' | 'link' | 'torrent'>('magnet');
    const [submitContent, setSubmitContent] = useState('');
    const [extractionCode, setExtractionCode] = useState('');   // 提取码
    const [archivePassword, setArchivePassword] = useState(''); // 解压密码
    const [remark, setRemark] = useState('');                   // 备注说明
    const [torrentFile, setTorrentFile] = useState<File | null>(null);

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

    const downloads = useMemo<DownloadItem[]>(() => {
        const rawInfo = video?.video_detail?.download_info;
        if (Array.isArray(rawInfo)) {
            return rawInfo as unknown as DownloadItem[];
        }
        return [];
    }, [video?.video_detail?.download_info]);

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
        switch (resolution) {
            case '4K':
                return (
                    <Badge variant="outline" className="text-purple-600 bg-purple-500/10 border-purple-500/30 px-2.5 py-0.5 text-xs font-bold">
                        4K UHD
                    </Badge>
                );
            case '1080P':
                return (
                    <Badge variant="outline" className="text-blue-600 bg-blue-500/10 border-blue-500/30 px-2.5 py-0.5 text-xs font-bold">
                        1080P HD
                    </Badge>
                );
            case '720P':
                return (
                    <Badge variant="outline" className="text-slate-600 bg-slate-500/10 border-slate-500/30 px-2.5 py-0.5 text-xs font-medium">
                        720P
                    </Badge>
                );
            default:
                return null;
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

    const handleDirectDownload = (link: string) => {
        window.open(link, '_blank');
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

    return (
        <>
            <Button
                variant="secondary"
                className="rounded-full px-4 h-9 shadow-none hover:bg-muted-foreground/10 gap-2 shrink-0 font-medium text-sm"
                onClick={handleOpenDialog}
            >
                <Download className="w-4 h-4" />
                <span>下载</span>
            </Button>

            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                <DialogContent className="sm:max-w-2xl rounded-2xl p-6">
                    <DialogHeader className="flex flex-row items-center justify-between pr-6 border-b pb-4">
                        <DialogTitle className="text-xl font-bold tracking-tight flex items-center gap-2">
                            {activeView === 'submit' && downloads.length > 0 && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 rounded-full mr-1"
                                    onClick={() => setActiveView('list')}
                                >
                                    <ArrowLeft className="w-4 h-4" />
                                </Button>
                            )}
                            {activeView === 'list' ? '资源获取' : '提交下载资源'}
                        </DialogTitle>

                        {activeView === 'list' && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 gap-1.5 text-xs font-semibold rounded-lg border-dashed"
                                onClick={() => setActiveView('submit')}
                            >
                                <Plus className="w-3.5 h-3.5" />
                                贡献/补充链接
                            </Button>
                        )}
                    </DialogHeader>

                    <div className="flex flex-col gap-4 mt-3">
                        {/* 视图 1：已有的下载资源列表 */}
                        {activeView === 'list' && (
                            <div className="space-y-3.5">
                                {downloads.map((item) => (
                                    <div
                                        key={item.id}
                                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 p-4 rounded-xl border border-border/80 bg-card hover:bg-muted/40 transition-all duration-200"
                                    >
                                        <div className="flex items-start gap-3.5 w-full">
                                            <div className={`p-2.5 rounded-xl shrink-0 mt-0.5 ${item.type === 'magnet' ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400' : 'bg-orange-500/10 text-orange-600 dark:text-orange-400'}`}>
                                                {item.type === 'magnet' ? <Magnet className="w-6 h-6" /> : <ShoppingBag className="w-6 h-6" />}
                                            </div>

                                            <div className="flex flex-col min-w-0 flex-1 justify-center">
                                                <div className="flex items-center gap-2 flex-wrap mb-1">
                                                    <span className="font-semibold text-foreground text-base tracking-tight truncate">{item.title}</span>

                                                    {getResolutionBadge(item.resolution)}

                                                    {item.costType === 'free' ? (
                                                        <Badge variant="outline" className="text-green-600 bg-green-500/10 border-green-500/20 px-2 py-0.5 text-xs font-medium">
                                                            免费
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline" className="text-orange-600 bg-orange-500/10 border-orange-500/20 px-2 py-0.5 text-xs font-medium">
                                                            付费
                                                        </Badge>
                                                    )}
                                                </div>
                                                <span className="text-sm text-muted-foreground">{item.description}</span>
                                                {item.type === 'store' && item.price && (
                                                    <span className="text-sm font-bold text-orange-600 dark:text-orange-400 mt-1">
                                                        {item.price}
                                                    </span>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-2.5 shrink-0 w-full sm:w-auto mt-2 sm:mt-0">
                                            {item.type === 'magnet' ? (
                                                <>
                                                    <Button
                                                        variant="default"
                                                        size="sm"
                                                        className="flex-1 sm:flex-none h-9 px-4 font-medium text-sm"
                                                        onClick={() => handleDirectDownload(item.link)}
                                                    >
                                                        <Zap className="w-4 h-4 mr-1.5" />
                                                        直接下载
                                                    </Button>

                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="flex-1 sm:flex-none h-9 px-4 font-medium text-sm"
                                                        onClick={() => handleCopy(item.link)}
                                                    >
                                                        <Copy className="w-4 h-4 mr-1.5" />
                                                        复制
                                                    </Button>
                                                </>
                                            ) : (
                                                <Button
                                                    variant="default"
                                                    size="sm"
                                                    className="w-full sm:w-auto h-9 px-4 font-medium text-sm"
                                                    onClick={() => handleDirectDownload(item.link)}
                                                >
                                                    <ExternalLink className="w-4 h-4 mr-1.5" />
                                                    前往获取
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))}

                                <div className="flex flex-col sm:flex-row sm:items-center justify-between p-3.5 rounded-xl bg-muted/40 text-sm text-muted-foreground border border-dashed gap-2">
                                    <span className="font-normal text-foreground/80">没有找到你想要的清晰度或下载格式？</span>
                                    <Button
                                        variant="link"
                                        size="sm"
                                        className="h-auto p-0 text-sm font-semibold text-primary hover:underline self-start sm:self-auto"
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
                                            className="w-full min-h-[90px] p-3.5 rounded-xl border border-input bg-background text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring resize-y leading-relaxed"
                                            placeholder="请在此粘贴磁力链接 (magnet:?xt=urn:btih:...)"
                                            value={submitContent}
                                            onChange={(e) => setSubmitContent(e.target.value)}
                                        />
                                    )}

                                    {/* 2. 第三方下载链接 */}
                                    {submitType === 'link' && (
                                        <Input
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
        </>
    );
}
