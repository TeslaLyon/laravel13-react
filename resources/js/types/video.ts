export type Video = {
    id: number;
    name: string;
    name_zh: string;
    slug: string;
    source_uuid: string;
    list_img: string; // 如果后端返回的是 JSON 字符串就先写 string
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
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    channel: Channel;
};

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

export type Channel = {
    id: number;
    name: string;
    slug: string;
    avatar: string;
    logo: string;
};
