import React, { useState, useEffect } from "react";
import { Button } from "@/components/ui/button";
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from "@/components/ui/tooltip";
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Bell, BellRing, BellOff, UserX, Check, ChevronDown, Loader2 } from "lucide-react";
import { motion, AnimatePresence } from "framer-motion";
import { useHttp } from '@inertiajs/react';
import { toast } from 'sonner';

// 星星粒子组件
const Sparkles = () => {
    const particles = [
        { id: 1, x: -40, y: -30, delay: 0 },
        { id: 2, x: 40, y: -40, delay: 0.1 },
        { id: 3, x: -30, y: 30, delay: 0.05 },
        { id: 4, x: 50, y: 20, delay: 0.15 },
        { id: 5, x: 0, y: -50, delay: 0.08 },
    ];

    return (
        <div className="absolute inset-0 pointer-events-none flex items-center justify-center z-50">
            {particles.map((p) => (
                <motion.div
                    key={p.id}
                    initial={{ opacity: 0, scale: 0, x: 0, y: 0 }}
                    animate={{ opacity: [0, 1, 0], scale: [0, 1.5, 0], x: p.x, y: p.y }}
                    transition={{ duration: 0.6, delay: p.delay, ease: "easeOut" }}
                    className="absolute"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="#E11D48" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 0L14.59 9.41L24 12L14.59 14.59L12 24L9.41 14.59L0 12L9.41 9.41L12 0Z" />
                    </svg>
                </motion.div>
            ))}
        </div>
    );
};

const Spinner = () => <Loader2 className="w-4 h-4 animate-spin" />;

interface FollowBtnProps {
    actorId: number;
    slug: string;
    initisFollowed: boolean;
}

interface CollectResponse {
    message: string;
}

export function FollowBtn({ actorId, slug, initisFollowed }: FollowBtnProps) {
    const [isSubscribed, setIsSubscribed] = useState(initisFollowed);
    const { post, processing } = useHttp();
    const [isAnimating, setIsAnimating] = useState(false);

    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [alertOpen, setAlertOpen] = useState(false);
    const [notifyLevel, setNotifyLevel] = useState<'all' | 'personalized' | 'none'>('personalized');

    // 【核心控制】：只有在播放关注动画，或者已关注状态下，才启用布局和颜色过渡
    const enableAnim = isAnimating || isSubscribed;

    // 动画结束切换状态
    useEffect(() => {
        if (isAnimating) {
            const timer = setTimeout(() => {
                setIsAnimating(false);
                setIsSubscribed(true);
            }, 1000);
            return () => clearTimeout(timer);
        }
    }, [isAnimating]);

    // 网络请求处理逻辑
    const handleToggle = () => {
        if (processing || isAnimating) return;

        post(`/actors/${actorId}/${slug}/follow`, {
            onSuccess: (response: unknown) => {
                const typedResponse = response as CollectResponse;
                toast.success(typedResponse.message);

                if (isSubscribed) {
                    setIsSubscribed(false);
                    setNotifyLevel('personalized');
                } else {
                    setIsAnimating(true);
                }
            },
            onError: () => {
                toast.error('操作失败，请刷新页面后重试');
            }
        });
    };

    let label = "关注";
    if (processing) label = "处理中...";
    else if (isAnimating || isSubscribed) label = "已关注";

    const getDynamicStyles = () => {
        if (isAnimating) return "bg-pink-500 text-white hover:bg-pink-600 border-transparent";
        if (isSubscribed) return "outline outline-2 outline-offset-2 outline-blue-500";
        return "";
    };

    const getCurrentBellIcon = () => {
        switch (notifyLevel) {
            case 'all': return <BellRing className="w-4 h-4" />;
            case 'none': return <BellOff className="w-4 h-4" />;
            default: return <Bell className="w-4 h-4" />;
        }
    };

    return (
        <div className="relative inline-block w-full sm:w-auto mt-3">
            <DropdownMenu
                open={dropdownOpen}
                onOpenChange={(open) => {
                    if (isSubscribed) setDropdownOpen(open);
                }}
            >
                <TooltipProvider>
                    <Tooltip open={isSubscribed ? false : undefined}>
                        <TooltipTrigger asChild>
                            <DropdownMenuTrigger asChild>
                                {/* 动态绑定 layout */}
                                <motion.div layout={enableAnim} className="w-full sm:w-auto">
                                    <Button
                                        onClick={(e) => {
                                            if (!isSubscribed) {
                                                e.preventDefault();
                                                handleToggle();
                                            }
                                        }}
                                        disabled={processing}

                                        className={`w-full sm:w-auto rounded-full px-5 text-sm font-semibold ${enableAnim ? "transition-colors duration-300" : ""
                                            } ${getDynamicStyles()}`}
                                    >
                                        <motion.div layout={enableAnim} className="flex items-center justify-center gap-2 overflow-hidden">
                                            {processing && <Spinner />}

                                            {/* 【优化】：完全移除了 AnimatePresence 和 exit，退订时图标瞬间消失 */}
                                            {((isAnimating || isSubscribed) && !processing) && (
                                                <motion.div
                                                    layout={enableAnim}
                                                    initial={{ opacity: 0, scale: 0, width: 0 }}
                                                    animate={{ opacity: 1, scale: 1, width: "auto" }}
                                                    transition={{ type: "spring", stiffness: 400, damping: 25 }}
                                                >
                                                    {getCurrentBellIcon()}
                                                </motion.div>
                                            )}

                                            <motion.span layout={enableAnim} className="text-sm font-medium whitespace-nowrap">
                                                {label}
                                            </motion.span>

                                            {/* 【优化】：完全移除了 AnimatePresence 和 exit，退订时箭头瞬间消失 */}
                                            {((isAnimating || isSubscribed) && !processing) && (
                                                <motion.div
                                                    layout={enableAnim}
                                                    initial={{ opacity: 0, x: -10, width: 0 }}
                                                    animate={{ opacity: 1, x: 0, width: "auto" }}
                                                    transition={{ duration: 0.2 }}
                                                >
                                                    <ChevronDown className="w-4 h-4" />
                                                </motion.div>
                                            )}
                                        </motion.div>
                                    </Button>
                                </motion.div>
                            </DropdownMenuTrigger>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            <p>关注</p>
                        </TooltipContent>
                    </Tooltip>
                </TooltipProvider>

                {/* 下拉菜单内容区保持不变 */}
                <DropdownMenuContent align="end" className="w-48 p-2 rounded-xl">
                    <DropdownMenuItem onClick={() => setNotifyLevel('all')} className="flex items-center justify-between cursor-pointer p-3 text-base rounded-lg">
                        <div className="flex items-center gap-3">
                            <BellRing className="w-5 h-5 text-foreground" />
                            <span>全部</span>
                        </div>
                        {notifyLevel === 'all' && <Check className="w-5 h-5 text-foreground" />}
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setNotifyLevel('personalized')} className="flex items-center justify-between cursor-pointer p-3 text-base rounded-lg">
                        <div className="flex items-center gap-3">
                            <Bell className="w-5 h-5 text-foreground" />
                            <span>个性化</span>
                        </div>
                        {notifyLevel === 'personalized' && <Check className="w-5 h-5 text-foreground" />}
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => setNotifyLevel('none')} className="flex items-center justify-between cursor-pointer p-3 text-base rounded-lg">
                        <div className="flex items-center gap-3">
                            <BellOff className="w-5 h-5 text-foreground" />
                            <span>无</span>
                        </div>
                        {notifyLevel === 'none' && <Check className="w-5 h-5 text-foreground" />}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator className="my-2" />
                    <DropdownMenuItem
                        onSelect={(e) => {
                            e.preventDefault();
                            setDropdownOpen(false);
                            setTimeout(() => {
                                setAlertOpen(true);
                            }, 150);
                        }}
                        className="flex items-center gap-3 cursor-pointer p-3 text-base rounded-lg"
                    >
                        <UserX className="w-5 h-5 text-foreground" />
                        <span>退订</span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <AlertDialog open={alertOpen} onOpenChange={setAlertOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>退订“{slug || '该创作者'}”?</AlertDialogTitle>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>取消</AlertDialogCancel>
                        <AlertDialogAction onClick={handleToggle}>退订</AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            <AnimatePresence>
                {isAnimating && <Sparkles />}
            </AnimatePresence>
        </div>
    );
}
