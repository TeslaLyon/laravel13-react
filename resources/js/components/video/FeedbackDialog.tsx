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

const FEEDBACK_TYPES = [
    { id: 'playback_issue', label: '播放卡顿/无法播放' },
    { id: 'content_issue', label: '内容不完整/音画不同步' },
    { id: 'subtitle_issue', label: '字幕错误/缺失' },
    { id: 'inappropriate', label: '违规/不当内容' },
    { id: 'other', label: '其他问题' },
    { id: 'head_img_lost', label: '头图无法显示' },
];

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
                            {FEEDBACK_TYPES.map((type) => (
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
                            placeholder="请提供更多细节，例如问题发生的时间点等，以便我们更快定位问题..."
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
