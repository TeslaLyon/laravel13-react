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
