export type Channel = {
  id: number;
  name: string;
  slug: string;
  data_crawl_type: number;
  video_num: number;
  follow_num: number;
  avatar: string;
  logo: string;
  official_website_url: string;
  created_at: string;
  updated_at: string;
  deleted_at: string | null;
  love_reactant_id: number;
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
