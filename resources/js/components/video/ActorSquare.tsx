import React, { useState, useEffect } from 'react';
import { Link, usePage, useHttp } from '@inertiajs/react';
import { toast } from 'sonner';
import { Actor } from '@/types/video';
import { menuStatus } from '@/actions/App/Http/Controllers/ActorController';

// 引入 shadcn 及图标组件
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { MoreHorizontal, Loader2 } from 'lucide-react';
import ActorController from '@/actions/App/Http/Controllers/ActorController';
import { useRequireAuth } from '@/components/require-auth-provider';

interface ActorSquareProps {
    actors: Actor[];
}

// --------------------------------------------------------
// 子组件：菜单组件
// --------------------------------------------------------
function ActorMenu({ actor }: { actor: Actor }) {
    const { props } = usePage<any>();
    const user = props.auth?.user;
    const { get, post, processing } = useHttp();
    const { requireAuth } = useRequireAuth();

    const [isOpen, setIsOpen] = useState(false);
    const [isFollowing, setIsFollowing] = useState<boolean>(actor.is_followed || false);

    // 状态：控制骨架屏
    const [isCheckingStatus, setIsCheckingStatus] = useState(true);
    // 状态：记录是否已获取过数据（本地缓存）
    const [hasFetched, setHasFetched] = useState(false);

    interface SubscribeResponse {
        status: boolean;
    }

    // --- 新增功能：监听窗口焦点变化，智能清除缓存 ---
    useEffect(() => {
        const handleFocus = () => {
            // 当用户从其他标签页或应用切回当前页面时，清除缓存标志
            // 这样下次打开菜单时，就会重新拉取最新数据
            setHasFetched(false);
        };

        window.addEventListener('focus', handleFocus);

        // 组件卸载时清理事件监听器
        return () => {
            window.removeEventListener('focus', handleFocus);
        };
    }, []);
    // ---------------------------------------------

    // 核心逻辑：获取状态
    useEffect(() => {
        let isMounted = true;

        // 只有当菜单打开、用户已登录，且【尚未获取过数据】时，才去请求
        if (isOpen && user && !hasFetched) {
            setIsCheckingStatus(true);

            get(menuStatus.url({ actor: actor.id, slug: actor.slug }), {
                onSuccess: (response: unknown) => {
                    if (!isMounted) return;
                    const subscribeResponse = response as SubscribeResponse;
                    setIsFollowing(subscribeResponse.status);

                    // 请求成功后，标记为已获取，利用缓存避免短时间内重复请求
                    setHasFetched(true);
                },
                onError: () => {
                    if (!isMounted) return;
                    toast.error('获取状态失败，请重试');
                },
                onFinish: () => {
                    if (isMounted) setIsCheckingStatus(false);
                }
            });
        } else if (isOpen && (!user || hasFetched)) {
            // 如果未登录，或者已经有缓存了，直接关闭骨架屏，显示实际按钮
            setIsCheckingStatus(false);
        }

        return () => {
            isMounted = false;
        };
    }, [isOpen, actor.id, user, hasFetched, get]);

    // 点击关注/取消关注的操作逻辑
    const handleFollowAction = (e: Event) => {
        e.preventDefault();

        // 率先关闭下拉菜单，保证界面干净
        setIsOpen(false);

        // 使用 requireAuth 进行登录拦截
        requireAuth(() => {
            if (processing) return;

            post(ActorController.follow.url({ actor: actor.id, slug: actor.slug }), {
                onSuccess: () => {
                    const newStatus = !isFollowing;
                    setIsFollowing(newStatus);
                    toast.success(newStatus ? '关注成功' : '已取消关注');
                },
                onError: () => {
                    toast.error('操作失败，请稍后重试');
                }
            });
        });
    };

    return (
        <DropdownMenu open={isOpen} onOpenChange={setIsOpen}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 rounded-full text-muted-foreground hover:text-foreground"
                >
                    <MoreHorizontal className="h-5 w-5" />
                    <span className="sr-only">更多操作</span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="w-32">
                {isCheckingStatus ? (
                    <div className="px-2 py-2 flex items-center justify-between">
                        <Skeleton className="h-4 w-16" />
                    </div>
                ) : (
                    <DropdownMenuItem
                        onSelect={handleFollowAction}
                        disabled={processing}
                        className="flex items-center justify-between cursor-pointer py-2"
                    >
                        <span>{isFollowing ? '取消关注' : '关注演员'}</span>
                        {processing && (
                            <Loader2 className="h-4 w-4 animate-spin text-primary ml-2" />
                        )}
                    </DropdownMenuItem>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

// --------------------------------------------------------
// 主列表组件
// --------------------------------------------------------
export function ActorSquare({ actors }: ActorSquareProps) {
    if (!actors || actors.length === 0) return null;

    return (
        <div className="w-full pt-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4">
                {actors.map((actor) => (
                    <div
                        key={actor.id}
                        className="flex items-center gap-4 p-2.5 rounded-2xl hover:bg-secondary/40 transition-colors duration-200 group"
                    >
                        <Link
                            href={ActorController.show.url({ actor: actor.id, slug: actor.slug })}
                            className="shrink-0 relative w-16 h-16 sm:w-[96px] sm:h-[96px] rounded-full overflow-hidden shadow-sm"
                        >
                            <div className="w-full h-full bg-muted">
                                <img
                                    src={actor.avatar}
                                    alt={actor.name}
                                    className="w-full h-full object-cover transition-transform duration-500 ease-in-out group-hover:scale-110"
                                />
                            </div>
                        </Link>

                        <Link
                            href={ActorController.show.url({ actor: actor.id, slug: actor.slug })}
                            className="flex-1 min-w-0 flex flex-col justify-center py-1"
                        >
                            <span className="block text-base sm:text-lg font-semibold text-foreground truncate group-hover:text-primary transition-colors leading-tight">
                                {actor.name}
                            </span>
                            <span className="block text-xs sm:text-sm text-muted-foreground truncate mt-0.5 font-medium">
                                别名
                            </span>
                        </Link>

                        <div className="shrink-0">
                            <ActorMenu actor={actor} />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}
