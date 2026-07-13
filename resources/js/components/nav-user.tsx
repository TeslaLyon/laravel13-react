import { usePage, Link } from '@inertiajs/react';
import { ChevronsUpDown, UserCircle, LogIn, UserPlus } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuGroup,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { useIsMobile } from '@/hooks/use-mobile';
import { login, register } from '@/routes';

export function NavUser() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    // === 修正后的未登录状态代码块 ===
    if (!auth.user) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                size="lg"
                                className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                data-test="sidebar-menu-button"
                                tooltip="登录 / 注册"
                            >
                                {/* 模拟一个标准头像容器，完美解决折叠不对齐问题 */}
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                    <UserCircle className="size-5" />
                                </div>

                                {/* 侧边栏折叠时，这部分文本会被原生组件自动隐藏 */}
                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">游客模式</span>
                                    <span className="truncate text-xs">点击登录或注册</span>
                                </div>
                                <ChevronsUpDown className="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                            align="end"
                            side={
                                isMobile
                                    ? 'bottom'
                                    : state === 'collapsed'
                                        ? 'left'
                                        : 'bottom'
                            }
                        >
                            <DropdownMenuGroup>
                                {/* 使用原生的 DropdownMenuItem 配合图标，样式更加清爽 */}
                                <DropdownMenuItem asChild>
                                    <Link href={login()} className="cursor-pointer flex items-center">
                                        <LogIn className="mr-2 size-4" />
                                        <span>登录账号</span>
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={register()} className="cursor-pointer flex items-center">
                                        <UserPlus className="mr-2 size-4" />
                                        <span>注册新账号</span>
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuGroup>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    // === 已登录用户的代码块（未更改） ===
    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent"
                            data-test="sidebar-menu-button"
                        >
                            <UserInfo user={auth.user} />
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="end"
                        side={
                            isMobile
                                ? 'bottom'
                                : state === 'collapsed'
                                    ? 'left'
                                    : 'bottom'
                        }
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
