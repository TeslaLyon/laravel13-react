import { Link } from '@inertiajs/react';
import { Store, LayoutGrid, Video, House, Users, Images, Clapperboard, HelpCircle, Search, FileText, Crown } from 'lucide-react';
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
import { help, home, search } from '@/routes';
import { index as videosIndex } from '@/routes/videos';
import { index as actorsIndex } from '@/routes/actors';
import { index as pictureIndex } from '@/routes/pictures';
import { index as channelIndex } from '@/routes/channels';
import { index as categoryIndex } from '@/routes/categories';
import { index as articlesIndex } from '@/routes/articles';
import { index as storeIndex } from '@/routes/store';
import { index as vipIndex } from '@/routes/vip';
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
        href: actorsIndex(),
        icon: Users,
    },
    {
        title: '图片',
        href: pictureIndex(),
        icon: Images,
    },
    {
        title: '片商',
        href: channelIndex(),
        icon: Clapperboard,
    },
    {
        title: '文章',
        href: articlesIndex(),
        icon: FileText,
    },
    {
        title: '分类',
        href: categoryIndex(),
        icon: LayoutGrid,
    },
    {
        title: '商城',
        href: storeIndex(),
        icon: Store,
    },
    {
        title: 'VIP',
        href: vipIndex(),
        icon: Crown,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: '帮助中心',
        href: help(),
        icon: HelpCircle,
    },
    {
        title: '搜索',
        href: search(),
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
