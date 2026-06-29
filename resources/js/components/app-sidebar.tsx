import { Link } from '@inertiajs/react';
import { BookOpen, FolderGit2, LayoutGrid, Video, House, Users, Images, Clapperboard, HelpCircle, Search, FileText } from 'lucide-react';
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
import { home } from '@/routes';
import { index as videosIndex } from '@/routes/videos';
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: '首页',
        href: home(),
        icon: House,
    },
    {
        title: '视频',
        href: videosIndex(),
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
    {
        title: '文章',
        href: home(),
        icon: FileText,
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
        href: home(),
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
