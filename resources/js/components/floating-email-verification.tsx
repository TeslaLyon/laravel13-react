import React, { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { MailWarning, Mail, Loader2, ShieldAlert } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { send } from '@/routes/verification';
import { useHttp } from '@inertiajs/react';

type EmailVerificationPageProps = {
    auth?: {
        user?: {
            email: string;
            email_verified_at: string | null;
        } | null;
    };
};

export const FloatingEmailVerification: React.FC = () => {
    const { auth } = usePage<EmailVerificationPageProps>().props;
    const { url } = usePage();

    const { post, processing } = useHttp();

    const user = auth?.user;

    // 1. 未登录、已验证邮箱，或已在验证引导页时不渲染
    if (!user || user.email_verified_at !== null || url.startsWith('/verify-email')) {
        return null;
    }

    // 2. 触发重新发送验证邮件
    const handleResendEmail = () => {

        post(send.url(), {
            onSuccess: (response: any) => {
                toast.success('新的验证邮件已发送，请检查收件箱（含垃圾箱）！');
            },
            onError: () => {
                toast.error('发送失败，请稍后重试。');
            },
            onNetworkError: () => {
                toast.error('网络错误，请检查网络连接并重试');
            }
        });
    };

    return (
        <div
            role="alert"
            aria-live="polite"
            className="fixed bottom-5 left-4 right-4 sm:left-auto sm:right-6 sm:bottom-6 z-50 sm:max-w-md w-auto animate-in fade-in slide-in-from-bottom-6 duration-300 pointer-events-auto"
        >
            <div className="relative rounded-2xl border border-amber-500/40 bg-background/90 dark:bg-card/90 backdrop-blur-xl p-4 sm:p-5 shadow-2xl shadow-amber-500/10 ring-1 ring-amber-500/20">
                {/* 顶部指示标与标题 */}
                <div className="flex items-start gap-3">
                    <div className="p-2 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">
                        <MailWarning className="w-5 h-5" />
                    </div>

                    <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-1.5 text-foreground font-semibold text-sm">
                            <span>账号邮箱尚未验证</span>
                            <span className="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-medium bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                限制部分权限
                            </span>
                        </div>

                        <p className="text-xs text-muted-foreground mt-1 leading-relaxed">
                            系统已向 <span className="font-medium text-foreground underline decoration-amber-500/40 underline-offset-2">{user.email}</span> 发送验证邮件。完成验证后方可使用全部频道功能。
                        </p>

                        {/* 底部引导操作栏 */}
                        <div className="mt-3.5 flex items-center justify-between gap-3 pt-2 border-t border-border/50">
                            <div className="flex items-center gap-1 text-[11px] text-muted-foreground/80">
                                <ShieldAlert className="w-3.5 h-3.5 text-amber-500/80" />
                                <span>无法关闭此提示</span>
                            </div>

                            <Button
                                type="button"
                                size="sm"
                                onClick={handleResendEmail}
                                disabled={processing}
                                className="h-8 rounded-full px-4 text-xs font-semibold bg-amber-500 hover:bg-amber-600 text-amber-950 dark:text-amber-950 shadow-xs cursor-pointer transition-all disabled:opacity-70"
                            >
                                {processing ? (
                                    <Loader2 className="w-3.5 h-3.5 animate-spin mr-1.5" />
                                ) : (
                                    <Mail className="w-3.5 h-3.5 mr-1.5" />
                                )}
                                <span>{processing ? '正在发送...' : '重发验证邮件'}</span>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};
