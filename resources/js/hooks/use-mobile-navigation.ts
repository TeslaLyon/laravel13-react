import { useCallback } from 'react';
import { useSidebar } from '@/components/ui/sidebar';

export type CleanupFn = () => void;

export function useMobileNavigation(): CleanupFn {
    const { setOpenMobile, isMobile } = useSidebar();

    return useCallback(() => {
        // 移动端点击导航跳转后自动收起抽屉侧边栏
        if (isMobile) {
            setOpenMobile(false);
        }
        // Remove pointer-events style from body...
        document.body.style.removeProperty('pointer-events');
    }, [isMobile, setOpenMobile]);
}

