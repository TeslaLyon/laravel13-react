import React from 'react';
import { useForm } from '@inertiajs/react';
import { Send, Lock, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

interface QuickReplyEditorProps {
    threadId: number | string;
    canReply: boolean;
}

export default function QuickReplyEditor({ threadId, canReply }: QuickReplyEditorProps) {
    const { data, setData, post, processing, reset, errors } = useForm({
        message: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!data.message.trim()) {
            return;
        }

        post(`/forum/threads/${threadId}/posts`, {
            preserveScroll: true,
            onSuccess: () => reset('message'),
        });
    };

    if (!canReply) {
        return (
            <div className="rounded-2xl border border-border/80 bg-muted/40 p-6 text-center text-muted-foreground flex flex-col items-center justify-center gap-2">
                <Lock className="w-5 h-5 text-muted-foreground/60" />
                <p className="text-sm font-medium">该主题已被管理员锁定，当前不可继续发表回复。</p>
            </div>
        );
    }

    return (
        <form onSubmit={handleSubmit} className="rounded-2xl border border-border/80 bg-card p-5 shadow-xs space-y-4">
            <div className="flex items-center justify-between">
                <h3 className="font-bold text-sm flex items-center gap-1.5 text-foreground">
                    <Sparkles className="w-4 h-4 text-primary" /> 快捷发表回帖
                </h3>
                <span className="text-[11px] text-muted-foreground">请遵守社区友善交流公约</span>
            </div>

            <Textarea
                placeholder="撰写你的回复内容，与大家共同讨论..."
                value={data.message}
                onChange={(e) => setData('message', e.target.value)}
                rows={4}
                className="resize-none focus-visible:ring-primary"
            />

            {errors.message && <p className="text-xs text-destructive">{errors.message}</p>}

            <div className="flex items-center justify-end">
                <Button type="submit" disabled={processing || !data.message.trim()} className="gap-1.5 px-5">
                    <Send className="w-4 h-4" />
                    <span>{processing ? '发送中...' : '提交回复'}</span>
                </Button>
            </div>
        </form>
    );
}
