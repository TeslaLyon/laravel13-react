// types/store.ts
export type MediaType = 'video' | 'image';

export interface Product {
    id: number;
    title: string;
    author: string;
    avatar: string;
    price: number;
    originalPrice?: number;
    type: MediaType;
    thumbnail: string;
    duration?: string; // 仅视频有
    resolution?: string; // 仅图片有
    views: string;
    tags?: Tag[];
}

export interface Tag {
  /** * 标签文本内容
   * @example "4K", "独家", "LUTs"
   */
  label: string;

  /** * 标签的颜色类名 (Tailwind CSS 格式)
   * 包含背景色和文字颜色，建议支持暗黑模式 (dark:)
   * @example "bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400"
   */
  color?: string;
}
