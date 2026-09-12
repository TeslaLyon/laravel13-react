import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editAvatar } from '@/routes/profile/avatar';
import { edit as editBanner } from '@/routes/profile/banner';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: '个人资料',
        href: edit(),
        icon: null,
    },
    {
        title: '修改头像',
        href: editAvatar(),
        icon: null,
    },
    {
        title: '修改横幅',
        href: editBanner(),
        icon: null,
    },
    {
        title: '账户安全',
        href: editSecurity(),
        icon: null,
    },
    {
        title: '外观',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        /* 1. 最外层容器：不使用 mx-auto，保持整体自然靠左对齐 */
        <div className="w-full px-4 py-6">
            <Heading
                title="设置"
                description="管理您的个人资料和帐户设置"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12 mt-6">
                {/* 2. 左侧导航栏：保持靠左固定宽度，增加 shrink-0 防止被右侧大尺寸组件挤压 */}
                <aside className="w-full max-w-xl lg:w-48 shrink-0">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                {/* 3. 🎯 右侧内容区：
                    - flex-1 w-full: 占据剩余全部可用空间，允许宽组件自由展开
                    - sm:min-w-[420px] lg:min-w-[520px]: 保证窄内容页面的视觉饱满度
                    - min-w-0: 防止 Flex 容器子元素意外溢出
                */}
                <div className="flex-1 w-full min-w-0 sm:min-w-[420px] lg:min-w-[520px]">
                    <section className="w-full space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
