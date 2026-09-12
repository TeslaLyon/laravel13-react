import React, { useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { toast } from 'sonner';
import { AlertTriangle, Mail, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

type EmailVerificationPageProps = {
    auth?: {
        user?: {
            email: string;
            email_verified_at: string | null;
        } | null;
    };
};

export const EmailVerificationBanner: React.FC = () => {
    const { auth } = usePage<EmailVerificationPageProps>().props;
    const { url } = usePage();
    const [isSending, setIsSending] = useState(false);

    const user = auth?.user;

    // 1. 用户未登录、已完成邮箱验证，或当前已在验证引导页时，不渲染警示条
    if (!user || user.email_verified_at !== null || url.startsWith('/verify-email')) {
        return null;
    }

    // 2. 触发重新发送验证邮件
    const handleResendEmail = () => {
        setIsSending(true);

        router.post(
            '/email/verification-notification',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    toast.success('新的验证链接已发送至您的注册邮箱，请查收！');
                },
                onError: () => {
                    toast.error('发送验证邮件失败，请稍后重试。');
                },
                onFinish: () => {
                    setIsSending(false);
                },
            }
        );
    };

    return (
        <aside
            aria-label="邮箱验证提醒"
            className="w-full bg-amber-500/10 dark:bg-amber-500/15 border-b border-amber-500/30 px-4 py-2.5 sm:px-6 transition-all"
        >
            <div className="max-w-7xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-3 text-xs sm:text-sm">
                {/* 警示文本 */}
                <div className="flex items-center gap-2 text-amber-700 dark:text-amber-300 font-medium text-center sm:text-left">
                    <AlertTriangle className="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
                    <span>
                        您的账号邮箱 (<strong>{user.email}</strong>) 尚未完成验证。为了保障账号安全及正常使用全部功能，请尽快完成验证。
                    </span>
                </div>

                {/* 引导操作按钮组 */}
                <div className="flex items-center gap-2 shrink-0">
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        onClick={handleResendEmail}
                        disabled={isSending}
                        className="h-8 rounded-full px-3.5 text-xs font-semibold border-amber-500/40 bg-background/80 hover:bg-amber-500/10 text-amber-800 dark:text-amber-200 cursor-pointer shadow-2xs"
                    >
                        {isSending ? (
                            <Loader2 className="w-3.5 h-3.5 animate-spin mr-1.5" />
                        ) : (
                            <Mail className="w-3.5 h-3.5 mr-1.5" />
                        )}
                        <span>{isSending ? '正在发送...' : '重新发送验证邮件'}</span>
                    </Button>
                </div>
            </div>
        </aside>
    );
};
