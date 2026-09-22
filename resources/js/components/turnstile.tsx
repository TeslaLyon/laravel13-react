import { useEffect, useRef } from 'react';
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
};

export default function Turnstile({
    onVerify,
    onExpire,
    onError,
    theme = 'auto',
    className = '',
}: Props) {
    const containerRef = useRef<HTMLDivElement>(null);
    const widgetIdRef = useRef<string | null>(null);
    const { turnstileSiteKey } = usePage().props as { turnstileSiteKey?: string };

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
                    callback: (token: string) => {
                        const input = containerRef.current?.querySelector(
                            'input[name="cf-turnstile-response"]'
                        ) as HTMLInputElement | null;
                        if (input) {
                            input.value = token;
                        }
                        onVerify?.(token);
                    },
                    'expired-callback': () => {
                        const input = containerRef.current?.querySelector(
                            'input[name="cf-turnstile-response"]'
                        ) as HTMLInputElement | null;
                        if (input) {
                            input.value = '';
                        }
                        onExpire?.();
                    },
                    'error-callback': (err: string) => {
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
    }, [turnstileSiteKey, theme, onVerify, onExpire, onError]);

    if (!turnstileSiteKey) {
        return null;
    }

    return (
        <div className={`my-2 flex justify-center min-h-[65px] ${className}`}>
            <div ref={containerRef} className="w-full flex justify-center" />
            {/* 隐藏的 input 兜底，确保原生 HTML 表单或 Inertia Form 组件能自然抓取该值 */}
            <input type="hidden" name="cf-turnstile-response" defaultValue="" />
        </div>
    );
}

