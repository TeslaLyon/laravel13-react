import React, { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Loader2 } from "lucide-react"; // 移除了 Flag 图标，因为内置按钮被删了
import { toast } from 'sonner';
import { useHttp } from '@inertiajs/react';
import FeedbackController from '@/actions/App/Http/Controllers/FeedbackController';
import { Textarea } from '@/components/ui/textarea';

const VIDEO_FEEDBACK_TYPES = [
    { id: 'screen_img_issue', label: '剧照有误/不匹配' },
    { id: 'cover_issue', label: '封面模糊/无法显示' },
    { id: 'playback_issue', label: '播放卡顿/无法播放' },
    { id: 'preview_issue', label: '悬停预览/动图异常' },
    { id: 'download_issue', label: '下载/磁力链接失效' },
    { id: 'info_issue', label: '信息有误 (片名/编号/日期)' },
    { id: 'metadata_issue', label: '演员/片商/系列错误' },
    { id: 'content_issue', label: '音画不同步/内容残缺' },
    { id: 'subtitle_issue', label: '字幕错误/缺失/不同步' },
    { id: 'other', label: '其他问题' },
];

const DEFAULT_FEEDBACK_TYPES = [
    { id: 'content_issue', label: '内容错误/失效' },
    { id: 'image_issue', label: '图片显示异常' },
    { id: 'playback_issue', label: '播放或资源失效' },
    { id: 'other', label: '其他问题' },
];

const getPlaceholder = (selectedType: string) => {
    switch (selectedType) {
        case 'screen_img_issue':
            return '例如：第几张剧照与本视频不符、剧照内容重复或无法加载等...';
        case 'cover_issue':
            return '例如：封面拉伸变形、模糊不清或无法显示等...';
        case 'playback_issue':
            return '例如：视频在几分几秒卡住、提示403或黑屏无法播放等...';
        case 'preview_issue':
            return '例如：鼠标悬停无预览动画、切片动图裂开等...';
        case 'download_issue':
            return '例如：磁力下载无速度/死链、提取码失效等...';
        case 'info_issue':
            return '例如：正确的番号/片名、实际时长或发行日期等...';
        case 'metadata_issue':
            return '例如：缺少某位演员、演员姓名有误、归属片商或系列不正确等...';
        case 'content_issue':
            return '例如：视频后半段缺失、音画不同步等...';
        case 'subtitle_issue':
            return '例如：中文字幕乱码、台词与声音慢了几秒、翻译错误等...';
        default:
            return '请提供更多细节，以便我们更快定位并修复问题...';
    }
};

interface FeedbackDialogProps {
    modelType: 'video' | 'image' | 'studio' | 'category' | 'shop' | 'forum';
    modelId?: number;
    // 1. 移除了 trigger 参数，并将 open 和 onOpenChange 改为必填项
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function FeedbackDialog({
    modelType,
    modelId,
    open,
    onOpenChange
}: FeedbackDialogProps) {
    const [selectedType, setSelectedType] = useState<string>('');
    const [content, setContent] = useState('');

    const feedbackTypes = modelType === 'video' ? VIDEO_FEEDBACK_TYPES : DEFAULT_FEEDBACK_TYPES;

    const { post, processing, transform } = useHttp({
        model_type: '' as string,
        model_id: 0 as number | string,
        data: {} as any,
    });

    // 2. 移除了 internalOpen, isControlled, requireAuth 和 handleTriggerClick

    // 3. 简化 handleOpenChange，直接调用父组件传入的方法
    const handleOpenChange = (newOpen: boolean) => {
        onOpenChange(newOpen);

        // 弹窗关闭后延迟清理表单数据
        if (!newOpen) {
            setTimeout(() => {
                setSelectedType('');
                setContent('');
            }, 200);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (!modelId) {
            toast.error("数据加载异常，请刷新页面后重试");
            return;
        }

        if (!selectedType) {
            toast.warning("请选择一个反馈类型");
            return;
        }

        transform((formData: any) => ({
            ...formData,
            model_type: modelType,
            model_id: modelId,
            data: {
                type: selectedType,
                content: content.trim(),
            },
        }));

        post(FeedbackController.store.url(), {
            onSuccess: () => {
                toast.success("反馈提交成功，感谢您的贡献！", {
                    duration: 5000,
                    description: "我们将尽快核实并处理您反馈的问题。",
                });
                handleOpenChange(false);
            },
            onError: () => {
                toast.error("提交失败，请检查网络或稍后重试。");
            }
        });
    };

    return (
        // 4. 直接使用 open 和 handleOpenChange，彻底去掉了内部所有的 trigger 按钮渲染逻辑
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="w-[95vw] sm:max-w-[500px] p-5 sm:p-6 rounded-2xl sm:rounded-xl">


                <form onSubmit={handleSubmit} className="flex flex-col gap-5">
                    <DialogHeader className="text-left">
                        <DialogTitle className="text-lg sm:text-xl font-bold">问题反馈</DialogTitle>
                        <DialogDescription className="text-xs sm:text-sm text-muted-foreground mt-1.5">
                            请告诉我们遇到了什么问题，我们将尽快修复。
                        </DialogDescription>
                    </DialogHeader>

                    <div className="flex flex-col gap-3">
                        <label className="text-sm font-semibold text-foreground">
                            问题类型 <span className="text-red-500">*</span>
                        </label>
                        <div className="flex flex-wrap gap-2">
                            {feedbackTypes.map((type) => (
                                <button
                                    key={type.id}
                                    type="button"
                                    onClick={() => setSelectedType(type.id)}
                                    className={`px-3 py-1.5 text-xs sm:text-sm rounded-lg border transition-all duration-200 ${selectedType === type.id
                                        ? 'bg-primary text-primary-foreground border-primary shadow-md dark:shadow-none font-medium'
                                        : 'bg-muted/50 hover:bg-accent text-muted-foreground hover:text-accent-foreground border-border/50 dark:bg-muted/20 dark:hover:bg-muted/60'
                                        }`}
                                >
                                    {type.label}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="flex flex-col gap-3">
                        <label className="text-sm font-semibold text-foreground">
                            补充说明 <span className="text-muted-foreground font-normal text-xs">(选填)</span>
                        </label>
                        <Textarea
                            value={content}
                            onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setContent(e.target.value)}
                            placeholder={getPlaceholder(selectedType)}
                            className="flex min-h-25 w-full rounded-xl border border-border/50 bg-muted/40 hover:bg-muted/60 px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 focus-visible:bg-background dark:bg-muted/20 dark:hover:bg-muted/40 transition-colors resize-none"
                        />
                    </div>

                    <DialogFooter className="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-2 mt-2 border-t border-border/40">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => handleOpenChange(false)}
                            disabled={processing}
                            className="w-full sm:w-auto h-11 sm:h-10"
                        >
                            取消
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !selectedType}
                            className="w-full sm:w-auto h-11 sm:h-10 disabled:opacity-50"
                        >
                            {processing ? (
                                <>
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                    提交中...
                                </>
                            ) : (
                                "提交反馈"
                            )}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
