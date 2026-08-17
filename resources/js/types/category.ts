/**
 * 分类/频道数据接口
 */
export interface CategoryItem {
    id: number;
    name: string;
    name_zh?: string;
    slug: string;
    description: string;
    coverImage: string;
    itemCount: string | number;
    followCount?: number;
}

/**
 * 面包屑导航接口
 */
export interface BreadcrumbItem {
    title: string;
    href: string | null;
}

/**
 * Laravel 后端返回的标准分页响应接口
 */
export interface PaginatedCategories {
    data: CategoryItem[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}
