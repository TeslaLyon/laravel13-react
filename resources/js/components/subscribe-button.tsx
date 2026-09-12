import React, { useState } from 'react';
import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import { useRequireAuth } from '@/components/require-auth-provider';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Check,
    Bell,
    BellRing,
    BellOff,
    ChevronDown,
    UserMinus,
    Loader2,
} from 'lucide-react';
import { subscribe, unsubscribe, updateNotification } from '@/actions/App/Http/Controllers/SubscriptionController';

export type NotificationType = 'all' | 'personalized' | 'none';

interface ActionResponse {
    message?: string;
    [key: string]: unknown;
}

export interface SubscribeButtonProps {
    type: 'user' | 'channel' | 'actor' | 'category' | 'tag' | string;
    id: number | string;
    name?: string;
    initialIsSubscribed?: boolean;
    initialNotificationType?: NotificationType;
    onSubscriptionChange?: (isSubscribed: boolean, countDelta: number) => void;
    subscribeText?: string;
    subscribedText?: string;
    className?: string;
}

export const SubscribeButton: React.FC<SubscribeButtonProps> = ({
    type,
    id,
    name,
    initialIsSubscribed = false,
    initialNotificationType = 'personalized',
    onSubscriptionChange,
    subscribeText = '订阅',
    subscribedText = '已订阅',
    className = '',
}) => {
    const [isSubscribed, setIsSubscribed] = useState(initialIsSubscribed);
    const [notificationType, setNotificationType] = useState<NotificationType>(initialNotificationType);
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [isConfirmDialogOpen, setIsConfirmDialogOpen] = useState(false);

    const { requireAuth } = useRequireAuth();

    // 1. 订阅与修改通知状态
    const {
        setData: setSubscribeData,
        post: postSubscribe,
        processing: isSubscribeProcessing,
    } = useHttp({
        notification_type: initialNotificationType,
    });

    // 2. 取消订阅状态
    const {
        post: postUnfollow,
        processing: isUnfollowProcessing,
    } = useHttp({});

    const isProcessing = isSubscribeProcessing || isUnfollowProcessing;

    // 🎯 操作 1：执行订阅（带加载态）
    const handleSubscribe = () => {
        requireAuth(() => {
            setSubscribeData('notification_type', 'personalized');

            postSubscribe(subscribe.url({ type, id }), {
                onSuccess: (response: unknown) => {
                    const typedResponse = response as ActionResponse;
                    setIsSubscribed(true);
                    setNotificationType('personalized');
                    setIsMenuOpen(true);
                    onSubscriptionChange?.(true, 1);
                    toast.success(typedResponse?.message || `${subscribeText}成功`);
                },
                onError: () => {
                    toast.error(`${subscribeText}失败，请刷新页面后重试`);
                },
            });
        });
    };

    // 🎯 操作 2：弹窗确认后执行取消订阅（带加载态与弹窗延迟关闭）
    const handleConfirmUnsubscribe = () => {
        requireAuth(() => {
            postUnfollow(unsubscribe.url({ type, id }), {
                onSuccess: (response: unknown) => {
                    const typedResponse = response as ActionResponse;
                    setIsSubscribed(false);
                    setNotificationType('personalized');
                    onSubscriptionChange?.(false, -1);
                    // 异步请求成功后关闭弹窗
                    setIsConfirmDialogOpen(false);
                    toast.success(typedResponse?.message || `已取消${subscribeText}`);
                },
                onError: () => {
                    toast.error(`取消${subscribeText}失败，请稍后重试`);
                },
            });
        });
    };

    // 🎯 操作 3：修改通知偏好级别
    const handleNotificationChange = (nextType: NotificationType) => {
        if (nextType === notificationType) {
            setIsMenuOpen(false);
            return;
        }

        requireAuth(() => {
            const previousType = notificationType;
            setNotificationType(nextType);
            setSubscribeData('notification_type', nextType);
            setIsMenuOpen(false);

            postSubscribe(updateNotification.url({ type, id }), {
                onSuccess: (response: unknown) => {
                    const typedResponse = response as ActionResponse;
                    toast.success(typedResponse?.message || '通知偏好已更新');
                },
                onError: () => {
                    setNotificationType(previousType);
                    setSubscribeData('notification_type', previousType);
                    toast.error('更新通知设置失败，请稍后重试');
                },
            });
        });
    };

    // 渲染当前通知级别对应的小图标
    const renderNotificationIcon = () => {
        switch (notificationType) {
            case 'all':
                return <BellRing className="w-4 h-4 text-foreground" />;
            case 'none':
                return <BellOff className="w-4 h-4 text-muted-foreground" />;
            case 'personalized':
            default:
                return <Bell className="w-4 h-4 text-foreground" />;
        }
    };

    return (
        <>
            {/* 未订阅状态：带 Loading 动画的按钮 */}
            {!isSubscribed ? (
                <Button
                    onClick={handleSubscribe}
                    disabled={isProcessing}
                    className={`rounded-full px-6 font-semibold bg-primary text-primary-foreground hover:bg-primary/90 shadow-xs transition-all cursor-pointer flex items-center justify-center gap-2 disabled:opacity-70 ${className}`}
                >
                    {isSubscribeProcessing && (
                        <Loader2 className="w-4 h-4 animate-spin text-primary-foreground" />
                    )}
                    <span>{isSubscribeProcessing ? '正在订阅...' : subscribeText}</span>
                </Button>
            ) : (
                /* 已订阅状态：带有通知选择的复合下拉菜单 */
                <DropdownMenu open={isMenuOpen} onOpenChange={setIsMenuOpen}>
                    <DropdownMenuTrigger asChild disabled={isProcessing}>
                        <Button
                            variant="secondary"
                            className={`rounded-full px-4 sm:px-5 font-semibold bg-secondary/80 text-secondary-foreground hover:bg-secondary flex items-center gap-2 shadow-xs transition-all cursor-pointer disabled:opacity-60 ${className}`}
                        >
                            {isProcessing ? (
                                <Loader2 className="w-4 h-4 animate-spin text-muted-foreground" />
                            ) : (
                                renderNotificationIcon()
                            )}
                            <span>{subscribedText}</span>
                            <ChevronDown className="w-3.5 h-3.5 opacity-60 ml-0.5" />
                        </Button>
                    </DropdownMenuTrigger>

                    <DropdownMenuContent
                        align="start"
                        className="w-56 p-1.5 rounded-2xl shadow-xl border border-border/70 backdrop-blur-md"
                    >
                        {/* 选项 1：全部通知 */}
                        <DropdownMenuItem
                            onClick={() => handleNotificationChange('all')}
                            className="flex items-center justify-between p-2.5 rounded-xl cursor-pointer"
                        >
                            <div className="flex items-center gap-2.5">
                                <BellRing className="w-4 h-4 text-foreground" />
                                <span className="text-sm font-medium">全部</span>
                            </div>
                            {notificationType === 'all' && (
                                <Check className="w-4 h-4 text-primary" />
                            )}
                        </DropdownMenuItem>

                        {/* 选项 2：个性化通知 */}
                        <DropdownMenuItem
                            onClick={() => handleNotificationChange('personalized')}
                            className="flex items-center justify-between p-2.5 rounded-xl cursor-pointer"
                        >
                            <div className="flex items-center gap-2.5">
                                <Bell className="w-4 h-4 text-foreground" />
                                <span className="text-sm font-medium">个性化</span>
                            </div>
                            {notificationType === 'personalized' && (
                                <Check className="w-4 h-4 text-primary" />
                            )}
                        </DropdownMenuItem>

                        {/* 选项 3：无通知 */}
                        <DropdownMenuItem
                            onClick={() => handleNotificationChange('none')}
                            className="flex items-center justify-between p-2.5 rounded-xl cursor-pointer"
                        >
                            <div className="flex items-center gap-2.5">
                                <BellOff className="w-4 h-4 text-muted-foreground" />
                                <span className="text-sm font-medium">无</span>
                            </div>
                            {notificationType === 'none' && (
                                <Check className="w-4 h-4 text-primary" />
                            )}
                        </DropdownMenuItem>

                        <DropdownMenuSeparator className="my-1" />

                        {/* 选项 4：取消订阅（触发二次确认弹窗） */}
                        <DropdownMenuItem
                            onClick={() => {
                                setIsMenuOpen(false);
                                setIsConfirmDialogOpen(true);
                            }}
                            className="flex items-center gap-2.5 p-2.5 rounded-xl text-destructive focus:bg-destructive/10 focus:text-destructive cursor-pointer"
                        >
                            <UserMinus className="w-4 h-4" />
                            <span className="text-sm font-medium">取消{subscribeText}</span>
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            )}

            {/* 🎯 取消订阅二次确认 Dialog 弹窗 */}
            <AlertDialog open={isConfirmDialogOpen} onOpenChange={(open) => !isUnfollowProcessing && setIsConfirmDialogOpen(open)}>
                <AlertDialogContent className="rounded-2xl max-w-md p-6">
                    <AlertDialogHeader className="space-y-2">
                        <AlertDialogTitle className="text-lg font-bold">
                            确认取消{subscribeText}？
                        </AlertDialogTitle>
                        <AlertDialogDescription className="text-muted-foreground text-sm leading-relaxed">
                            取消{subscribeText}后，你将不再接收来自 {name ? `“${name}”` : '该频道/实体'} 的新作品更新与动态通知。
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {/* 扩大按钮间距：gap-3 sm:gap-4 与顶边距 mt-6 */}
                    <AlertDialogFooter className="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end gap-3 sm:gap-4">
                        <AlertDialogCancel
                            disabled={isUnfollowProcessing}
                            className="rounded-full px-5 h-10 font-medium border-border/80 hover:bg-muted transition-colors cursor-pointer"
                        >
                            保留{subscribeText}
                        </AlertDialogCancel>

                        <AlertDialogAction
                            onClick={(e) => {
                                // 阻止弹窗默认被立即关闭，等待异步操作完成
                                e.preventDefault();
                                handleConfirmUnsubscribe();
                            }}
                            disabled={isUnfollowProcessing}
                            className="rounded-full px-5 h-10 font-medium bg-destructive text-destructive-foreground hover:bg-destructive/90 flex items-center justify-center gap-2 shadow-xs transition-all cursor-pointer disabled:opacity-70"
                        >
                            {isUnfollowProcessing && (
                                <Loader2 className="w-4 h-4 animate-spin text-destructive-foreground" />
                            )}
                            <span>{isUnfollowProcessing ? '正在取消...' : '确认取消'}</span>
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
};
