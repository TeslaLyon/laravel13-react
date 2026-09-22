import { useEffect, useRef, useState } from 'react';
import { usePage } from '@inertiajs/react';

declare global {
    interface Window {
        turnstile?: {
            render: (
                container: string | HTMLElement,
                options: {
                    sitekey: string;
                    theme?: 'light' | 'dark' | 'auto';
                    size?: 'normal' | 'compact' | 'flexible';
                    'response-field'?: boolean;
                    'response-field-name'?: string;
                    callback?: (token: string) => void;
                    'expired-callback'?: () => void;
                    'error-callback'?: (errorCode: string) => void;
                }
            ) => string;
            reset: (widgetId: string) => void;
            remove: (widgetId: string) => void;
        };
    }
}

type Props = {
    onVerify?: (token: string) => void;
    onExpire?: () => void;
    onError?: (error: string) => void;
    theme?: 'light' | 'dark' | 'auto';
    className?: string;
    resetTrigger?: unknown;
};

export default function Turnstile({
    onVerify,
    onExpire,
    onError,
    theme = 'auto',
    className = '',
    resetTrigger,
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const [token, setToken] = useState<string>('');
    const { turnstileSiteKey } = usePage().props as { turnstileSiteKey?: string };

    const resetWidget = () => {
        if (widgetIdRef.current && window.turnstile) {
            try {
                window.turnstile.reset(widgetIdRef.current);
            } catch {
                // ignore
            }
        }
        setToken('');
        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    // 当外层表单发生验证错误或传入 resetTrigger 时，自动重置 Turnstile
    useEffect(() => {
        if (resetTrigger && (typeof resetTrigger !== 'object' || Object.keys(resetTrigger as object).length > 0)) {
            resetWidget();
        }
    }, [resetTrigger]);

    useEffect(() => {
        if (!turnstileSiteKey || !containerRef.current) {
            return;
        }

        let isMounted = true;

        const renderWidget = () => {
            if (!isMounted || !containerRef.current || !window.turnstile) {
                return;
            }

            // 如果已有组件实例，先重置/移除，防止快速重渲染导致的重复渲染
            if (widgetIdRef.current) {
                try {
                    window.turnstile.remove(widgetIdRef.current);
                } catch {
                    // ignore
                }
                widgetIdRef.current = null;
            }

            try {
                widgetIdRef.current = window.turnstile.render(containerRef.current, {
                    sitekey: turnstileSiteKey,
                    theme,
                    size: 'flexible',
                    'response-field': false, // 由 React 统一控制 input，避免 DOM 出现重复字段
                    callback: (t: string) => {
                        if (!isMounted) return;
                        setToken(t);
                        if (inputRef.current) {
                            inputRef.current.value = t;
                        }
                        onVerify?.(t);
                    },
                    'expired-callback': () => {
                        if (!isMounted) return;
                        setToken('');
                        if (inputRef.current) {
                            inputRef.current.value = '';
                        }
                        onExpire?.();
                    },
                    'error-callback': (err: string) => {
                        if (!isMounted) return;
                        setToken('');
                        if (inputRef.current) {
                            inputRef.current.value = '';
                        }
                        onError?.(err);
                    },
                });
            } catch (e) {
                console.error('Turnstile render failed:', e);
            }
        };

        // 如果脚本还在加载中，轮询检查挂载
        if (!window.turnstile) {
            const interval = setInterval(() => {
                if (window.turnstile) {
                    clearInterval(interval);
                    renderWidget();
                }
            }, 100);
            return () => {
                isMounted = false;
                clearInterval(interval);
                if (widgetIdRef.current && window.turnstile) {
                    try {
                        window.turnstile.remove(widgetIdRef.current);
                    } catch {
                        // ignore
                    }
                }
            };
        }

        renderWidget();

        return () => {
            isMounted = false;
            if (widgetIdRef.current && window.turnstile) {
                try {
                    window.turnstile.remove(widgetIdRef.current);
                } catch {
                    // ignore
                }
            }
        };
    }, [turnstileSiteKey, theme]);

    if (!turnstileSiteKey) {
        return null;
    }

    return (
        <div className={`my-2 flex flex-col items-center min-h-[65px] ${className}`}>
            <div ref={containerRef} className="w-full flex justify-center" />
            {/* 唯一由 React 管理的隐藏 input，保证 Inertia Form 收集的数据准确无误 */}
            <input
                ref={inputRef}
                type="hidden"
                name="cf-turnstile-response"
                value={token}
                readOnly
            />
        </div>
    );
}
