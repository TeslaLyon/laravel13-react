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
import { LogIn } from 'lucide-react';

interface PageProps {
    auth?: {
        user?: Record<string, any> | null;
    };
    [key: string]: any;
}

interface RequireAuthContextType {
    requireAuth: (actionCallback: () => void) => void;
}

const RequireAuthContext = createContext<RequireAuthContextType | undefined>(undefined);

export function RequireAuthProvider({ children }: { children: ReactNode }) {
    const [isOpen, setIsOpen] = useState(false);
    const { auth } = usePage<PageProps>().props;

    const requireAuth = (actionCallback: () => void) => {
        if (auth?.user) {
            actionCallback();
        } else {
            setIsOpen(true);
        }
    };

    const handleGoToLogin = () => {
        setIsOpen(false);
        const currentPath = window.location.pathname + window.location.search;
        router.get('/login', { redirect: currentPath });
    };

    return (
        <RequireAuthContext.Provider value={{ requireAuth }}>
            {children}

            <AlertDialog open={isOpen} onOpenChange={setIsOpen}>
                {/* 1. 确保弹窗主体使用统一的 bg-background 背景色 */}
                <AlertDialogContent className="max-w-[400px] rounded-2xl p-6 border border-border/80 bg-background shadow-2xl sm:rounded-2xl">
                    <AlertDialogHeader className="flex flex-col items-center text-center space-y-3">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary ring-8 ring-primary/5">
                            <LogIn className="h-5 w-5" />
                        </div>

                        <div className="space-y-1.5">
                            <AlertDialogTitle className="text-lg font-semibold tracking-tight text-foreground">
                                需要登录账号
                            </AlertDialogTitle>
                            <AlertDialogDescription className="text-sm leading-relaxed text-muted-foreground px-2">
                                登录后即可解锁点赞、收藏、评论及互动下载等全量功能。
                            </AlertDialogDescription>
                        </div>
                    </AlertDialogHeader>

                    {/* 2. 显式覆盖 bg-transparent，消除底部的独立背景色与边框 */}
                    <AlertDialogFooter className="mt-5 flex flex-col-reverse gap-2.5 bg-transparent border-none p-0 sm:flex-row sm:justify-end sm:gap-3">
                        {/* 3. 调整 Cancel 按钮背景为透明 (bg-transparent)，悬停时才加灰色背景 */}
                        <AlertDialogCancel className="mt-0 h-10 flex-1 rounded-xl px-4 text-sm font-medium border-border/60 bg-transparent text-foreground hover:bg-accent hover:text-accent-foreground transition-all active:scale-[0.98]">
                            先逛逛
                        </AlertDialogCancel>
                        <AlertDialogAction
                            onClick={handleGoToLogin}
                            className="h-10 flex-1 rounded-xl bg-primary px-4 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90 transition-all active:scale-[0.98]"
                        >
                            去登录
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
