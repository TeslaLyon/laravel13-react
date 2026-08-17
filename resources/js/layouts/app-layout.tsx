import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
// 1. 导入上一步创建的全局登录拦截 Provider 组件
import { RequireAuthProvider } from '@/components/require-auth-provider';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        // 2. 在最外层包裹 RequireAuthProvider
        <RequireAuthProvider>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
        </RequireAuthProvider>
    );
}
