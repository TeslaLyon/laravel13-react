import React from 'react';
import { Link, router } from '@inertiajs/react';
import {
    LogOut,
    Settings,
    House,
    Wallet,
    ChartNoAxesCombined,
    CalendarCheck2,
    Sparkles,
} from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { show as UserSpaceShow } from '@/actions/App/Http/Controllers/UserSpaceController';
import { index as WalletIndex } from '@/actions/App/Http/Controllers/WalletController';
import { index as checkIn } from '@/actions/App/Http/Controllers/CheckInController';
import { index as GrowthIndex } from '@/actions/App/Http/Controllers/GrowthController';
import { index as AvatarDecorationIndex } from '@/actions/App/Http/Controllers/AvatarDecorationController';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();

    // 🌟 退出登录：局部更新 auth 属性，保持页面无刷新、无白屏
    const handleLogout = (e: Event) => {
        e.preventDefault();
        cleanup();

        router.post(logout(), {}, {
            only: ['auth'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onError: (errors) => {
                console.error('退出登录异常:', errors);
            },
        });
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2.5 px-3 py-2 text-left text-sm rounded-lg bg-muted/40">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>

            <DropdownMenuSeparator className="-mx-1.5 my-1.5" />
            <DropdownMenuGroup className="space-y-1">
                <DropdownMenuItem asChild>
                    <Link
                        className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                        href={AvatarDecorationIndex()}
                        // prefetch
                        onClick={cleanup}
                    >
                        <Sparkles className="mr-2.5 size-4.5 text-primary" />
                        <span>头像装饰</span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                        href={GrowthIndex()}
                        // prefetch
                        onClick={cleanup}
                    >
                        <ChartNoAxesCombined className="mr-2.5 size-4.5 text-primary" />
                        <span>成长等级中心</span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                        href={checkIn()}
                        // prefetch
                        onClick={cleanup}
                    >
                        <CalendarCheck2 className="mr-2.5 size-4.5 text-primary" />
                        <span>每日签到</span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                        href={WalletIndex()}
                        // prefetch
                        onClick={cleanup}
                    >
                        <Wallet className="mr-2.5 size-4.5 text-primary" />
                        <span>钱包</span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                        href={UserSpaceShow({ user: user.name })}
                        // prefetch
                        onClick={cleanup}
                    >
                        <House className="mr-2.5 size-4.5 text-primary" />
                        <span>个人主页</span>
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                    <Link
                        className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                        href={edit()}
                        // prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2.5 size-4.5 text-primary" />
                        <span>设置</span>
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>

            <DropdownMenuSeparator />

            {/* 🌟 退出登录项 */}
            <DropdownMenuItem
                className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors text-destructive hover:bg-destructive/10 hover:text-destructive focus:bg-destructive/10 focus:text-destructive active:scale-[0.99]"
                onSelect={handleLogout}
                data-test="logout-button"
            >
                <LogOut className="mr-2.5 size-4.5 text-destructive" />
                <span>退出</span>
            </DropdownMenuItem>
        </>
    );
}
