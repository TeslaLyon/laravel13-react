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
