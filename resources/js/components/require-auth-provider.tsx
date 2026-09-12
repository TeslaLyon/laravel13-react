import React, { createContext, useContext, useState, ReactNode } from 'react';
import { usePage, router } from '@inertiajs/react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { LogIn, MailCheck } from 'lucide-react';

interface User {
    id?: number | string;
    email?: string;
    email_verified_at?: string | null;
    [key: string]: any;
}

interface PageProps {
    auth?: {
        user?: User | null;
    };
    [key: string]: any;
}

type DialogType = 'login' | 'verify-email' | null;

interface RequireAuthContextType {
    requireAuth: (actionCallback: () => void) => void;
}

const RequireAuthContext = createContext<RequireAuthContextType | undefined>(undefined);

export function RequireAuthProvider({ children }: { children: ReactNode }) {
    const [dialogType, setDialogType] = useState<DialogType>(null);
    const { auth } = usePage<PageProps>().props;

    const requireAuth = (actionCallback: () => void) => {
        // 1. 未登录拦截
        if (!auth?.user) {
            setDialogType('login');
            return;
        }

        // 2. 已登录但未完成邮箱验证拦截 (email_verified_at 为 null 或 undefined)
        if (!auth.user.email_verified_at) {
            setDialogType('verify-email');
            return;
        }

        // 3. 校验通过，执行业务操作
        actionCallback();
    };

    // 确认按钮跳转逻辑
    const handleConfirmAction = () => {
        const currentType = dialogType;
        setDialogType(null);

        if (currentType === 'login') {
            const currentPath = window.location.pathname + window.location.search;
            router.get('/login', { redirect: currentPath });
        } else if (currentType === 'verify-email') {
            router.get('/email/verify');
        }
    };

    // 弹窗文案与图标配置字典
    const dialogConfig = {
        login: {
            icon: <LogIn className="h-5 w-5" />,
            title: '需要登录账号',
            description: '登录后即可解锁点赞、收藏、评论及互动下载等全量功能。',
            actionText: '去登录',
        },
        'verify-email': {
            icon: <MailCheck className="h-5 w-5" />,
            title: '需要验证邮箱',
            description: '为了保障账号安全并正常参与互动，请先完成邮箱验证。',
            actionText: '去验证',
        },
    };

    const currentConfig = dialogType ? dialogConfig[dialogType] : null;

    return (
        <RequireAuthContext.Provider value={{ requireAuth }}>
            {children}

            <AlertDialog open={dialogType !== null} onOpenChange={(open) => !open && setDialogType(null)}>
                <AlertDialogContent className="max-w-[400px] rounded-2xl p-6 border border-border/80 bg-background shadow-2xl sm:rounded-2xl">
                    <AlertDialogHeader className="flex flex-col items-center text-center space-y-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary ring-8 ring-primary/5">
                            {currentConfig?.icon}
                        </div>

                        <div className="space-y-1.5">
                            <AlertDialogTitle className="text-lg font-semibold tracking-tight text-foreground">
                                {currentConfig?.title}
                            </AlertDialogTitle>
                            <AlertDialogDescription className="text-sm leading-relaxed text-muted-foreground px-2">
                                {currentConfig?.description}
                            </AlertDialogDescription>
                        </div>
                    </AlertDialogHeader>

                    <AlertDialogFooter className="mt-5 flex flex-col-reverse gap-2.5 bg-transparent border-none p-0 sm:flex-row sm:justify-end sm:gap-3">
                        <AlertDialogCancel className="mt-0 h-10 flex-1 rounded-xl px-4 text-sm font-medium border-border/60 bg-transparent text-foreground hover:bg-accent hover:text-accent-foreground transition-all active:scale-[0.98]">
                            先逛逛
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleConfirmAction}
                            className="h-10 flex-1 rounded-xl bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90 transition-all active:scale-[0.98]"
                        >
                            {currentConfig?.actionText}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </RequireAuthContext.Provider>
    );
}

export function useRequireAuth() {
    const context = useContext(RequireAuthContext);
    if (!context) {
        throw new Error('useRequireAuth 必须在 <RequireAuthProvider> 内部使用');
    }
    return context;
}
