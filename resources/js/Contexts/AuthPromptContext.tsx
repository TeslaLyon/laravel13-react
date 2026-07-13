import React, { createContext, useContext, useState } from 'react';
import { router } from '@inertiajs/react';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog'; // 请根据你的实际路径调整


// 定义 Context 的类型
interface AuthPromptContextType {
    showAuthPrompt: (customMessage?: string) => void;
}

// 创建 Context
const AuthPromptContext = createContext<AuthPromptContextType | undefined>(undefined);

// 创建 Provider 组件
export const AuthPromptProvider = ({ children }: { children: React.ReactNode }) => {
    const [isOpen, setIsOpen] = useState(false);
    const [message, setMessage] = useState('您需要登录后才能继续此操作。');

    // 触发弹窗的方法
    const showAuthPrompt = (customMessage?: string) => {
        if (customMessage) {
            setMessage(customMessage);
        } else {
            setMessage('您需要登录后才能继续此操作。'); // 默认提示
        }
        setIsOpen(true);
    };

    // 跳转到登录页的处理逻辑
    const handleLogin = () => {
        setIsOpen(false);
        // 假设你使用了 Ziggy 路由，跳转到 login 路由
        // 如果没有用 ziggy，可以直接写 router.visit('/login')
        router.visit('/login');
    };

    return (
        <AuthPromptContext.Provider value={{ showAuthPrompt }}>
            {children}

            {/* 全局共用的提示弹窗 */}
            <AlertDialog open={isOpen} onOpenChange={setIsOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>需要登录</AlertDialogTitle>
                        <AlertDialogDescription>
                            {message}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel onClick={() => setIsOpen(false)}>
                            取消
                        </AlertDialogCancel>
                        {/* Shadcn 的 AlertDialogAction 默认带有按钮样式 */}
                        <AlertDialogAction onClick={handleLogin}>
                            去登录
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AuthPromptContext.Provider>
    );
};

// 创建一个自定义 Hook 方便其他组件调用
export const useAuthPrompt = () => {
    const context = useContext(AuthPromptContext);
    if (!context) {
        throw new Error('useAuthPrompt 必须在 AuthPromptProvider 内部使用');
    }
    return context;
};
