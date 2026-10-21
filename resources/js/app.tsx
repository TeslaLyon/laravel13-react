import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
// import { AuthPromptProvider } from './Contexts/AuthPromptContext';

// 🌟 版本自愈保护：当生产环境发布新版本后，若客户端仍持有旧 Chunk 导致 404，自动静默刷新获取最新资源
if (typeof window !== 'undefined') {
    window.addEventListener('vite:preloadError', (event) => {
        event.preventDefault();
        const reloadKey = 'vite_chunk_reload_lock';
        if (!sessionStorage.getItem(reloadKey)) {
            sessionStorage.setItem(reloadKey, '1');
            window.location.reload();
        }
    });

    window.addEventListener('load', () => {
        sessionStorage.removeItem('vite_chunk_reload_lock');
    });
}

const appName = (typeof window !== 'undefined' && window.APP_NAME) || import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'welcome':
                return null;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            // <AuthPromptProvider>
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
            // </AuthPromptProvider>
        );
    },
    progress: {
        color: '#FF0000',
    },
});

// This will set light / dark mode on load...
initializeTheme();
