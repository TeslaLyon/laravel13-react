// 1. 定义高清图 (HighDPI) 的结构
export type HighDpi = {
  double: string;
};

// 2. 定义 WebP 格式图片的结构
export type WebpImage = {
  src: string;
  highdpi: HighDpi;
  placeholder: string;
};

// 3. 定义完整的单张图片信息结构 (booty_img 数组中的每一项)
export type BootyImage = {
  src: string;
  webp: WebpImage;
  width: number;
  height: number;
  highdpi: HighDpi;
  placeholder: string;
};

// 4. 定义你要的核心 Actor 类型
export type Actor = {
  id: number;                // 修改为 number
  name: string;
  slug: string;
  original_id: number;       // 根据返回数据补充
  avatar: string;            // 根据返回数据补充
  booty_img: BootyImage[];   // 修改为对应的图片对象数组
  banner: string;            // 根据返回数据补充
  gender: number;
  is_trans_model: boolean;
  follow_num: number;
  view_num: number;

  // 时间相关的字段可能为 null，所以使用联合类型
  last_add_at: string | null;
  deleted_at: string | null;
  created_at: string | null;
  updated_at: string | null;
};

export type PaginationLink = {
  url: string | null;
  label: string;
  active: boolean;
  page: number | null;
};

// 通用的 Laravel 分页响应结构 (使用泛型 T 以便复用)
export type PaginatedResponse<T> = {
  current_page: number;
  data: T[];                 // 这里会存放我们的 Actor[]
  first_page_url: string;
  from: number | null;
  last_page: number;
  last_page_url: string;
  links: PaginationLink[];
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
};
