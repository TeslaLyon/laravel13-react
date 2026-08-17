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

// 🌟 1. 引入鉴权拦截 Hook
import { useRequireAuth } from '@/components/require-auth-provider';

// 🌟 2. 导出支持的模块类型
export type ModuleType = 'actor' | 'channel' | 'category' | 'tag';

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

// 🌟 3. 扩充 Props 类型定义，支持可选参数与多模块类型
export interface FollowBtnProps {
    moduleType?: ModuleType;            // 模块类型，默认为 'actor'
    id?: number | string;               // 实体 ID
    actorId?: number | string;          // 兼容旧属性名 actorId
    slug?: string;                      // 允许 string 或 undefined
    initisFollowed?: boolean;           // 初始关注状态
}

interface CollectResponse {
    message: string;
}

/**
 * 辅助函数：根据模块类型返回 API 路径前缀
 */
function getModulePrefix(moduleType: ModuleType): string {
    switch (moduleType) {
        case 'category':
            return 'categories'; // 自动转换为复数 categories
        case 'actor':
            return 'actors';
        case 'channel':
            return 'channels';
        case 'tag':
            return 'tags';
        default:
            return `${moduleType}s`;
    }
}

export function FollowBtn({
    moduleType = 'actor',
    id,
    actorId,
    slug = '',
    initisFollowed = false
}: FollowBtnProps) {
    const [isSubscribed, setIsSubscribed] = useState(initisFollowed);
    const { post, processing } = useHttp();
    const [isAnimating, setIsAnimating] = useState(false);

    const [dropdownOpen, setDropdownOpen] = useState(false);
    const [alertOpen, setAlertOpen] = useState(false);
    const [notifyLevel, setNotifyLevel] = useState<'all' | 'personalized' | 'none'>('personalized');

    // 🌟 4. 初始化鉴权拦截器
    const { requireAuth } = useRequireAuth();

    // 优先取 id，没有则取兼容属性 actorId
    const targetId = id ?? actorId;

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

    // 🌟 5. 网络请求处理逻辑（使用 requireAuth 拦截）
    const handleToggle = () => {
        if (processing || isAnimating || !targetId) return;

        // 引入权限验证包围网络请求
        requireAuth(() => {
            const prefix = getModulePrefix(moduleType);
            const slugSegment = slug ? `${slug}/` : '';
            const followUrl = `/${prefix}/${targetId}/${slugSegment}follow`;

            post(followUrl, {
                onSuccess: (response: unknown) => {
                    const typedResponse = response as CollectResponse;
                    toast.success(typedResponse.message || '操作成功');

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
        <div className="relative inline-block w-full sm:w-auto">
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

                {/* 下拉菜单内容区 */}
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

            {/* 退订二次确认模态框 */}
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