import { useEffect, useRef } from 'react';
import { router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import type { Auth, UnreadNotification } from '@/types';

function getXsrfToken(): string | null {
    if (typeof document === 'undefined') return null;
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

/**
 * 全局站内未读通知监听器
 * 无需长连接：依托 Inertia 全局共享 Props，在用户打开、刷新或任意页面跳转时自动唤醒充值到账等通知弹窗
 */
export function GlobalNotificationListener() {
    const page = usePage<{ auth?: Auth }>();
    const unreadNotifications = page.props.auth?.unreadNotifications;
    const shownIdsRef = useRef<Set<string>>(new Set());

    useEffect(() => {
        if (!Array.isArray(unreadNotifications) || unreadNotifications.length === 0) {
            return;
        }

        let hasApprovedWalletNotice = false;

        unreadNotifications.forEach((notice: UnreadNotification) => {
            if (!notice?.id || shownIdsRef.current.has(notice.id)) {
                return;
            }
            shownIdsRef.current.add(notice.id);

            const data = notice.data || {};
            const title = data.title || '系统通知';
            const message = data.message || '';
            const actionUrl = data.action_url || '/wallet';

            if (data.result === 'rejected') {
                toast.error(title, {
                    description: message,
                    duration: 8000,
                    action: {
                        label: '查看钱包',
                        onClick: () => router.visit(actionUrl),
                    },
                });
            } else {
                if (data.category === 'wallet_recharge') {
                    hasApprovedWalletNotice = true;
                }
                toast.success(title, {
                    description: message,
                    duration: 8000,
                    action: {
                        label: '前往钱包',
                        onClick: () => router.visit(actionUrl),
                    },
                });
            }

            // 静默标记为已读，避免重复弹窗
            const xsrfToken = getXsrfToken();
            fetch(`/notifications/${encodeURIComponent(notice.id)}/read`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(xsrfToken ? { 'X-XSRF-TOKEN': xsrfToken } : {}),
                },
            }).catch(() => {
                // 忽略网络偶发错误
            });
        });

        // 若用户当前恰好处于钱包页面且收到到账通知，自动局部刷新余额和流水
        if (hasApprovedWalletNotice && typeof window !== 'undefined' && window.location.pathname.startsWith('/wallet')) {
            router.reload({ only: ['wallet', 'transactions'] });
        }
    }, [unreadNotifications]);

    return null;
}
