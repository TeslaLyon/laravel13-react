/**
 * 文章分类接口
 */
export interface CategoryItem {
    id: number;
    name: string;
    slug: string;
}

/**
 * 文章标签接口
 */
export interface TagItem {
    id: number;
    name: string;
    slug: string;
}

/**
 * 作者信息接口
 */
export interface AuthorItem {
    id?: number;
    name: string;
    avatar?: string;
}

/**
 * 文章基础信息接口 (应用于文章列表卡片)
 */
export interface ArticleItem {
    id: number;
    title: string;
    slug: string;
    excerpt: string;
    cover_image: string;
    read_time: string;
    published_at?: string;
    date?: string; // 兼容备用日期
    views_count?: number;
    author?: AuthorItem;
    categories?: CategoryItem[];
    tags?: TagItem[];
}

/**
 * 文章详情扩展接口 (应用于文章详情页)
 */
export interface ArticleDetailData extends ArticleItem {
    detail?: {
        content: string;
        content_format?: string;
        seo_title?: string;
        seo_description?: string;
        seo_keywords?: string;
    };
    related_articles?: ArticleItem[];
}

/**
 * Laravel 后端返回的标准分页数据结构
 */
export interface PaginatedArticles {
    data: ArticleItem[];
    current_page: number;
    last_page: number;
    next_page_url: string | null;
    prev_page_url: string | null;
    total: number;
    per_page: number;
}

/**
 * 面包屑导航类型
 */
export interface BreadcrumbItem {
    title: string;
    href: string | null;
}
