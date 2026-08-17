import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/**
 * 将链接转换为URL字符串
 * @param url - 可以是字符串或包含url属性的对象
 * @returns 返回URL字符串
 */
export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    // 如果url是字符串类型，直接返回该字符串
    // 否则返回对象中的url属性
    return typeof url === 'string' ? url : url.url;
}

const hoverColorPool = [
    "bg-red-500/10 dark:bg-red-500/20",
    "bg-blue-500/10 dark:bg-blue-500/20",
    "bg-green-500/10 dark:bg-green-500/20",
    "bg-yellow-500/10 dark:bg-yellow-500/20",
    "bg-purple-500/10 dark:bg-purple-500/20",
    "bg-pink-500/10 dark:bg-pink-500/20",
    "bg-indigo-500/10 dark:bg-indigo-500/20",
    "bg-orange-500/10 dark:bg-orange-500/20",
    "bg-teal-500/10 dark:bg-teal-500/20",
    "bg-cyan-500/10 dark:bg-cyan-500/20",
    "bg-emerald-500/10 dark:bg-emerald-500/20",
    "bg-fuchsia-500/10 dark:bg-fuchsia-500/20",
    "bg-rose-500/10 dark:bg-rose-500/20",
    "bg-violet-500/10 dark:bg-violet-500/20",
    "bg-sky-500/10 dark:bg-sky-500/20",
    "bg-lime-500/10 dark:bg-lime-500/20",
    "bg-amber-500/10 dark:bg-amber-500/20",
];

/**
 * 根据纯数字 ID 获取对应的卡片 hover 背景色
 * @param {number} id - 卡片的数字类型标识符
 * @returns {string} 返回 Tailwind CSS class 字符串
 */
export const getCardHoverColor = (id: number) => {
    // 容错处理：确保传入的是一个有效的数字，防止 NaN 或 undefined 导致报错
    if (typeof id !== 'number' || isNaN(id)) {
        return hoverColorPool[0];
    }

    // 直接使用数学取余，性能极高
    // 使用 Math.abs 是为了防止万一出现负数 id 导致数组索引变成负数而取不到值
    const colorIndex = Math.abs(id) % hoverColorPool.length;

    return hoverColorPool[colorIndex];
};

/**
 * 1. 特性检测：一次性检测当前浏览器是否支持 ES2023 的 roundingMode: 'trunc'
 * 结果会被缓存到 supportsModernIntl 变量中，避免重复性能消耗。
 */
const supportsModernIntl = (() => {
    try {
        // TypeScript 可能会对最新的 'trunc' 报错，使用 as any 绕过类型检查
        const formatter = new Intl.NumberFormat('zh-CN', { roundingMode: 'trunc' } as any);
        // 如果浏览器支持，解析后的选项中会保留 'trunc'；如果不支持，通常会被忽略或报错
        return formatter.resolvedOptions().roundingMode === 'trunc';
    } catch (e) {
        return false;
    }
})();

/**
 * 内部高精度降级辅助函数 (上一版我们编写的绝对安全计算方案)
 */
const fallbackExactFloor = (originalNum: number, unitBase: number, decimals: number) => {
    const pow = Math.pow(10, decimals);
    const floored = Math.floor(originalNum / (unitBase / pow));
    return floored / pow;
};

/**
 * 将大数字格式化为带有中文单位（万、亿）的字符串
 * 智能路由：优先使用浏览器原生 ES2023 Intl API，不支持则优雅降级为原生数学计算。
 *
 * @param value 需要格式化的数字或字符串
 * @returns 格式化后的字符串，例如 "1.14万"、"15.6万"
 */
export function formatChineseUnit(value: number | string | undefined | null): string {
    // 数据清洗与安全防护
    if (value === undefined || value === null || value === '') return '0';
    const num = Number(value);
    if (Number.isNaN(num) || !Number.isFinite(num)) return '0';

    // 小于 1 万，直接返回原数字
    if (num < 10000) {
        return num.toString();
    }

    // 智能动态小数位逻辑：(1万~10万保留2位，10万以上保留1位，1亿以上保留2位)
    let fractionDigits = 2;
    if (num >= 100000 && num < 100000000) {
        fractionDigits = 1;
    }

    // ==========================================
    // 方案 A：现代浏览器原生 API 处理 (高性能、自带国际化)
    // ==========================================
    if (supportsModernIntl) {
        const formatter = new Intl.NumberFormat('zh-CN', {
            notation: "compact", // 自动转化为"万"或"亿"
            maximumFractionDigits: fractionDigits, // 限制小数位
            roundingMode: "trunc", // 核心：向下截断，不进位
        } as any);

        return formatter.format(num);
    }

    // ==========================================
    // 方案 B：老旧浏览器优雅降级处理 (绝对精度的整数算法)
    // ==========================================
    if (num < 100000000) {
        const resultWan = fallbackExactFloor(num, 10000, fractionDigits);
        return resultWan + ' 万';
    }

    const resultYi = fallbackExactFloor(num, 100000000, fractionDigits);
    return resultYi + ' 亿';
}
