import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';
import { EmailVerificationBanner } from '@/components/email-verification-banner';
import { FloatingEmailVerification } from '@/components/floating-email-verification';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <EmailVerificationBanner />
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <FloatingEmailVerification />
            </AppContent>
        </AppShell>
    );
}
