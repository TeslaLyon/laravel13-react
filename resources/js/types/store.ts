export interface BreadcrumbItem {
    title: string;
    href: string | null;
}

export interface StoreCategory {
    label: string;
    type: string | null;
}

export interface ProductTag {
    id: number;
    name: string;
    name_zh: string;
    style_preset: string;
}

export interface SpecBadge {
    label: string;
    color?: string;
}

export interface Product {
    id: number;
    title: string;
    slug: string;
    type: 'video' | 'image' | 'source_code' | 'asset';
    thumbnail: string;
    preview_type: 'video' | 'carousel' | 'gif' | 'image';
    preview_data?: Record<string, any> | null;
    duration?: string;
    resolution?: string | null;
    spec_badge?: SpecBadge | null;
    price: number;
    original_price?: number | null;
    has_discount: boolean;
    views_count: number;
    sales_count: number;
    created_at_human: string;
    author: string;
    avatar?: string;
    tags: ProductTag[];
}

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
}
export interface ProductShowData {
    id: number;
    title: string;
    slug?: string;
    type: 'video' | 'image' | 'source_code' | 'asset';
    thumbnail: string;
    preview_type: 'video' | 'carousel' | 'gif' | 'image';
    preview_data?: {
        video_url?: string;
        gif_url?: string;
        images?: string[];
        [key: string]: any;
    } | null;
    duration?: string;
    resolution?: string | null;
    spec_badge?: { label: string; color?: string } | null;
    price: number;
    original_price?: number | null;
    has_discount: boolean;
    views_count: number;
    sales_count: number;
    created_at_human: string;
    publish_date: string;
    has_purchased: boolean; // 购买授权状态
    author: {
        name: string;
        avatar?: string;
        title: string;
        bio: string;
    };
    tags: Array<{ id: number; name: string, name_zh: string }>;
    detail?: {
        specs: Record<string, string>;
        content: string;
        delivery_type: 'netdisk' | 'card_key' | 'download' | 'online_view';
        delivery_summary: string;
        delivery_content?: {
            pan_url?: string;
            pan_code?: string;
            unzip_password?: string;
            license_key?: string;
            [key: string]: any;
        } | null;
    } | null;
}
