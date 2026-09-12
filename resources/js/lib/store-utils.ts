/**
 * 浏览量千/万级格式化：34200 -> 3.4万, 850 -> 850
 */
export function formatViews(views: number): string {
    if (!views) return '0';
    if (views >= 10000) return `${(views / 10000).toFixed(1).replace(/\.0$/, '')}万`;
    if (views >= 1000) return `${(views / 1000).toFixed(1).replace(/\.0$/, '')}千`;
    return views.toString();
}

/**
 * 纯业务 Tag 映射到 Tailwind 语义色彩 (替代原先在 DB 存 CSS 类名的做法)
 */
export function getTagPresetClass(preset: string): string {
    const map: Record<string, string> = {
        amber: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300',
        blue: 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300',
        rose: 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300',
        emerald: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300',
        purple: 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300',
        zinc: 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
    return map[preset] || 'bg-muted text-muted-foreground';
}

/**
 * 根据标签 ID 或名称计算并返回柔和的彩色微光背景与文字样式
 */
export function getTagColorClass(identifier: number | string): string {
    const colorPresets = [
        // 1. 经典天蓝 (科技/工程/教程)
        'bg-blue-500/10 text-blue-700 border-blue-500/20 dark:bg-blue-500/15 dark:text-blue-300 dark:border-blue-500/30',
        // 2. 翡翠薄荷绿 (商用/授权/实战)
        'bg-emerald-500/10 text-emerald-700 border-emerald-500/20 dark:bg-emerald-500/15 dark:text-emerald-300 dark:border-emerald-500/30',
        // 3. 琥珀金黄 (独家/首发/大师)
        'bg-amber-500/10 text-amber-800 border-amber-500/25 dark:bg-amber-500/15 dark:text-amber-300 dark:border-amber-500/30',
        // 4. 梦幻紫罗兰 (RAW/3D/色彩)
        'bg-purple-500/10 text-purple-700 border-purple-500/20 dark:bg-purple-500/15 dark:text-purple-300 dark:border-purple-500/30',
        // 5. 珊瑚玫瑰粉 (特惠/UI/Figma)
        'bg-rose-500/10 text-rose-700 border-rose-500/20 dark:bg-rose-500/15 dark:text-rose-300 dark:border-rose-500/30',
        // 6. 靛青墨蓝 (专业/深度/系统)
        'bg-indigo-500/10 text-indigo-700 border-indigo-500/20 dark:bg-indigo-500/15 dark:text-indigo-300 dark:border-indigo-500/30',
        // 7. 青绿山湖 (航拍/风光/摄影)
        'bg-teal-500/10 text-teal-700 border-teal-500/20 dark:bg-teal-500/15 dark:text-teal-300 dark:border-teal-500/30',
    ];

    // 若传入数字 ID 直接取模；若传入字符串则通过字符累加哈希取模
    let index = 0;
    if (typeof identifier === 'number') {
        index = Math.abs(identifier) % colorPresets.length;
    } else if (typeof identifier === 'string') {
        const hash = Array.from(identifier).reduce((acc, char) => acc + char.charCodeAt(0), 0);
        index = hash % colorPresets.length;
    }

    return colorPresets[index];
}
