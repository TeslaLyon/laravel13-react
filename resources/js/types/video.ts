// -----------------------------------------------------------------------------
// 基础数据模型定义
// -----------------------------------------------------------------------------

export type Channel = {
    id: number;
    name: string;
    slug: string;
    avatar: string;
    logo?: string; // 数据样例中未出现，设为可选
    data_crawl_type?: number;
};

export type Actor = {
    id: number;
    name: string;
    slug: string;
    avatar: string;
    love_reactant_id: number;
    is_followed: boolean;
    pivot?: {
        video_id: number;
        actor_id: number;
    };
};

export type Tag = {
    id: number;
    name: string;
    name_zh: string;
    slug: string;
    pivot?: {
        video_id: number;
        tag_id: number;
    };
};

export type Category = {
    id: number;
    name: string;
    name_zh: string;
    slug: string;
    pivot?: {
        video_id: number;
        category_id: number;
    };
};

// -----------------------------------------------------------------------------
// 视频相关类型定义
// -----------------------------------------------------------------------------

export type Video = {
    id: number;
    name: string;
    name_zh: string;
    has_zh_subtitles: boolean;
    slug: string;
    source_uuid: string;
    list_img: ImageItem[]; // 后端返回的是 JSON 字符串
    preview: string;
    release_at: string;
    video_code: string;
    channel_id: number;
    is_4k: boolean;
    is_vr: boolean;
    sexual_orientation: number;
    is_trans_model: boolean;
    max_quality: string;
    status: number;
    likes_count: number;
    favorites_count: number;
    love_reactant_id: number | null; // 根据样例新增
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    country: string | null; // 新增国家字段，可能为 null

    // 关联模型
    channel: Channel;
    video_detail: VideoDetail;
    actors: Actor[];       // 新增演员数组
    tags: Tag[];           // 新增标签数组
    categories: Category[]; // 新增分类数组
};

export type ImageItem = {
    src: string;
    src_source?: string;
    width: number;
    height: number;
    placeholder?: string;
    placeholder_source?: string;
    highdpi?: {
        double?: string;
        double_source?: string;
    };
    webp?: {
        src: string;
        src_source?: string;
        placeholder?: string;
        placeholder_source?: string;
        highdpi?: {
            double?: string;
            double_source?: string;
        };
    };
}

/**
 * 视频详情完整类型定义
 */
export type VideoDetail = {
    id: number;
    video_id: number;
    video_urls: string;

    /**
     * 原始数据为 JSON 字符串，解析后为 ScreenImageMeta 数组
     * 示例: "[{\"screen_img_full_url\": \"...\", ...}]"
     */
    screen_img: string;

    /**
     * 原始数据为 JSON 字符串，解析后为 ListImageMeta 数组
     * 示例: "[{\"src\": \"...\", \"webp\": {\"src\": \"...\"}}]"
     */
    list_img_large_meta: [];

    /** 下载信息，可为 null */
    download_info: string | null;

    /** 视频长度，根据真实数据结构修改为 number */
    movie_length: number;

    /** 视频描述 */
    description: string;

    /** 创建时间 (ISO 8601 格式字符串) */
    created_at: string;

    /** 更新时间 (ISO 8601 格式字符串) */
    updated_at: string;
};

// -----------------------------------------------------------------------------
// 图片解析元数据类型 (用于 JSON.parse 后的断言)
// -----------------------------------------------------------------------------

export type ScreenImageMeta = {
    screen_img_full_url: string;
    screen_img_full_width: number;
    screen_img_full_height?: number;
    screen_img_default_url?: string;
    screen_img_default_width?: number;
    screen_img_default_height?: number;
    screen_img_full_source_url?: string;
    screen_img_full_source_width?: number;
    screen_img_full_source_height?: number;
    screen_img_def_url?: string; // 兼容老数据
};

/**
 * 列表大图元数据结构 (对应 list_img_large_meta 解析后的单项)
 */
export type ListImageMeta = {
    src: string;
    width?: number;
    height?: number;
    media?: string;
    src_source?: string;
    placeholder?: string;
    webp?: {
        src: string;
        src_source?: string;
        placeholder?: string;
        highdpi?: {
            double: string;
            double_source: string;
        };
    };
    highdpi?: {
        double: string;
        double_source: string;
    };
};

// -----------------------------------------------------------------------------
// 分页响应模型
// -----------------------------------------------------------------------------

export type PaginatedResponse<T> = {
    current_page: number;
    data: T[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
};
