import { usePage, Link, router } from '@inertiajs/react';
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
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { register } from '@/routes';

export function NavUser() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();
    const cleanup = useMobileNavigation();

    // 🌟 登录处理：获取当前完整路径并携带 redirect 参数跳转
    const handleLogin = (e: Event) => {
        e.preventDefault();
        cleanup();
        const currentPath = window.location.pathname + window.location.search;
        router.get('/login', { redirect: currentPath });
    };

    // === 游客状态（未登录） ===
    if (!auth?.user) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu modal={false}>
                        <DropdownMenuTrigger asChild>
                            <SidebarMenuButton
                                size="lg"
                                className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                                data-test="sidebar-menu-button"
                                tooltip="登录 / 注册"
                            >
                                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-muted text-muted-foreground">
                                    <UserCircle className="size-5" />
                                </div>

                                <div className="grid flex-1 text-left text-sm leading-tight">
                                    <span className="truncate font-semibold">游客模式</span>
                                    <span className="truncate text-xs">点击登录或注册</span>
                                </div>
                                <ChevronsUpDown className="ml-auto size-4" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>

                        <DropdownMenuContent
                            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl p-1.5 shadow-lg"
                            align="end"
                            side={
                                isMobile
                                    ? 'top'
                                    : state === 'collapsed'
                                        ? 'left'
                                        : 'bottom'
                            }
                            sideOffset={8}
                            onCloseAutoFocus={(e) => {
                                // 移动端关闭时避免焦点跳跃触发其他组件的失焦/关闭逻辑
                                if (isMobile) {
                                    e.preventDefault();
                                }
                            }}
                        >
                            <DropdownMenuGroup className="space-y-1">
                                {/* 🌟 绑定带参登录逻辑 */}
                                <DropdownMenuItem
                                    className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                                    onSelect={handleLogin}
                                >
                                    <LogIn className="mr-2.5 size-4.5 text-primary" />
                                    <span>登录账号</span>
                                </DropdownMenuItem>

                                <DropdownMenuItem asChild>
                                    <Link
                                        href={register()}
                                        onClick={cleanup}
                                        className="cursor-pointer flex items-center h-10 px-3 rounded-lg text-sm font-medium transition-colors hover:bg-accent hover:text-accent-foreground active:scale-[0.99]"
                                    >
                                        <UserPlus className="mr-2.5 size-4.5 text-primary" />
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

    // === 已登录用户 ===
    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="group text-sidebar-accent-foreground data-[state=open]:bg-sidebar-accent group-data-[collapsible=icon]:overflow-visible"
                            data-test="sidebar-menu-button"
                        >
                            <UserInfo user={auth.user} />
                            <ChevronsUpDown className="ml-auto size-4 group-data-[collapsible=icon]:hidden" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-xl p-1.5 shadow-lg"
                        align="end"
                        side={
                            isMobile
                                ? 'top'
                                : state === 'collapsed'
                                    ? 'left'
                                    : 'bottom'
                        }
                        sideOffset={8}
                        onCloseAutoFocus={(e) => {
                            if (isMobile) {
                                e.preventDefault();
                            }
                        }}
                    >
                        <UserMenuContent user={auth.user} />
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
