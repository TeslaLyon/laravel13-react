import { Link } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Video, House, Users, Images, Clapperboard, HelpCircle, Search } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { home, videos } from '@/routes';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: '首页',
        href: home(),
        icon: House,
    },
    {
        title: '视频',
        href: videos(),
        icon: Video,
    },
    {
        title: '演员',
        href: home(),
        icon: Users,
    },
    {
        title: '图片',
        href: home(),
        icon: Images,
    },
    {
        title: '片商',
        href: home(),
        icon: Clapperboard,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: '帮助中心',
        href: home(),
        icon: HelpCircle,
    },
    {
        title: '搜索',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: Search,
    },
];

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={home()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
