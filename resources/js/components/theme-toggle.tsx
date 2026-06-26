import { Moon, Sun } from 'lucide-react';
import type { ButtonHTMLAttributes } from 'react';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function ThemeToggle({
    className = '',
    ...props
}: ButtonHTMLAttributes<HTMLButtonElement>) {
    const { appearance, updateAppearance } = useAppearance();

    // 判断当前是否为暗色模式（如果 default 是 system，你可以根据实际需求调整初始逻辑）
    const isDark = appearance === 'dark';

    const toggleTheme = () => {
        updateAppearance(isDark ? 'light' : 'dark');
    };

    return (
        <div className="ml-auto flex items-center gap-2">
            <button
                onClick={toggleTheme}
                className={cn(
                    'relative inline-flex h-9 w-9 items-center justify-center rounded-md border border-neutral-200 bg-white hover:bg-neutral-100 hover:text-neutral-900 transition-colors dark:border-neutral-800 dark:bg-neutral-950 dark:hover:bg-neutral-800 dark:hover:text-neutral-50',
                    className,
                )}
                {...props}
            >
                {/* 浅色模式下的太阳，深色模式时缩小并旋转隐藏 */}
                <Sun className="h-4 w-4 transition-all dark:-rotate-90 dark:scale-0" />
                {/* 深色模式下的月亮，浅色模式时缩小并旋转隐藏 */}
                <Moon className="absolute h-4 w-4 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
                <span className="sr-only">切换主题</span>
            </button>
        </div>

    );
}
