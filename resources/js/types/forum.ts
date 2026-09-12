/**
 * 导航面包屑单项定义
 */
export interface BreadcrumbItem {
    title: string;              // 节点名称 (如: 首页、论坛大厅、版块名、帖子标题)
    href?: string | null;       // 跳转链接 (为 null 或 undefined 时不可点击)
}

/**
 * 通用 Laravel LengthAwarePaginator 分页数据容器
 */
export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    prev_page_url?: string | null;
    next_page_url?: string | null;
}

/**
 * 🎯 角色/身份横幅 Ribbon 定义 (支持多组横幅堆叠)
 */
export interface GroupBanner {
    name: string;               // 横幅文字 (如: Contributor, Advanced Leaker, Administrator)
    title: string;               // 横幅文字 (如: Contributor, Advanced Leaker, Administrator)
    bgColor?: string;           // 横幅背景色 (十六进制颜色值如: #15803d)
    textColor?: string;         // 横幅文字颜色 (默认 #ffffff)
    icon?: string | null;       // 横幅前置图标 (如: lucide:shield-alert, lucide:sparkles)
}

/**
 * 用户获得的勋章/徽章类型 (支持 Tooltip 描述与自定义图标)
 */
export interface UserBadge {
    id: number | string;
    name: string;               // 徽章名称 (如: 1 周年荣誉勋章、技术先锋)
    icon?: string | null;       // 徽章图标标识 (图片 URL 或 null 渲染拟物化金牌)
    color?: string;             // 徽章专属主题色 (如 #f59e0b)
    description?: string;       // 徽章详细获取条件或说明 (Tooltip 展示)
}

/**
 * 🎯 用户多维度活跃指标与综合总积分数据
 */
export interface AuthorStats {
    postCount: number;          // 发帖与回帖总数
    reactionScore: number;      // 获赞/表态总分
    totalCredits: number;       // 综合总积分 (按公式加权计算)
    prestigePoints?: number;    // 威望 (核心声望分)
    enthusiasmPoints?: number;  // 热心值 (互助采纳)
    bountyPoints?: number;      // 悬赏值
    contributionPoints?: number;// 贡献值
    digestThreadCount?: number; // 精华帖数
    violationCount?: number;    // 违规警告次数
}

/**
 * 基础轻量发帖作者信息
 * (由首页列表直接下发，仅包含基础展示要素，避免首屏大表联查)
 */
export interface BasicAuthor {
    id: number;                 // 用户 ID
    name: string;               // 昵称或显示名称
    avatar?: string | null;     // 头像图片 URL
    username?: string;          // 用户唯一句柄 (@username)
    coverUrl?: string | null;   // 顶部封面背景图 URL (用于 HoverCard 顶部 Banner)
}

/**
 * 🎯 发帖作者完整画像详情
 * (用于帖子楼层 Postbit 侧边栏及 HoverCard 悬浮资料卡)
 */
export interface PostAuthor extends BasicAuthor {
    usernameStyle?: string | null; // 用户名专属 CSS 渲染样式 (如: "color: #ef4444; font-weight: 800;")
    displayTitle?: string;         // 阶梯等级头衔或自定义头衔 (如: Grandmaster, Well-known member)
    banners?: GroupBanner[];       // 多重身份横幅 Ribbon 列表 (按显示优先级排序)
    stats?: AuthorStats;           // 活跃度与积分统计数据快照
    role?: string | null;          // 主用户组名称/身份描述 (如: 超级管理员、社区版主)
    joinedAt?: string;             // 注册加入时间 (如: 2024-03)
    threadCount?: number;          // 主题发帖总数 (兼容旧属性)
    likeCount?: number;            // 获得点赞总数 (兼容旧属性)
    badges?: UserBadge[];          // 佩戴的徽章列表
}

/**
 * 悬浮卡片数据接口别名 (与 PostAuthor 保持一致，提升语义化)
 */
export type UserHoverCardData = PostAuthor;

/**
 * 帖子彩色前缀分类标签 (如: [Verified], [Request], [OnlyFans])
 */
export interface ThreadPrefix {
    id: number | string;
    name: string;               // 标签文字
    bgColor?: string;           // 标签背景色 (十六进制如 #6f42c1 或 CSS 变量)
    textColor?: string;         // 标签文字颜色 (默认 #ffffff)
}

/**
 * 最新发帖/跟帖缓存信息
 */
export interface LastPostInfo {
    threadId: number;
    threadTitle?: string;
    slug: string;
    createdAt: string;                  // 格式化时间 (如: Today at 9:25 AM 或 10分钟前)
    authorName?: string;                // 兜底纯文本作者名 (兼容旧字段)
    author?: BasicAuthor | PostAuthor;  // 作者基础信息或完整画像信息
    prefixes?: ThreadPrefix[];          // 帖子绑定的彩色前缀标签列表
}

/**
 * 子版块轻量类型
 */
export interface SubForumNode {
    id: number | string;
    title: string;
    slug: string;
}

/**
 * 节点项类型 (对应 nodes + forums 表)
 */
export interface ForumNode {
    id: number | string;
    title: string;                      // 节点名称
    slug: string;
    nodeType: 'category' | 'forum' | 'link' | 'page';
    description?: string;
    iconUrl?: string | null;            // 自定义图片图标 URL (为 null 时渲染系统矢量图标)
    linkUrl?: string | null;            // 外链节点专属跳转 URL
    threadCount: number;                // 主题数
    messageCount: number;               // 帖子/回复数
    subForums?: SubForumNode[];         // 二级/三级子版块列表
    lastPost?: LastPostInfo | null;     // 最新发布的帖子信息
}

/**
 * 分类大块类型 (顶级卡片容器)
 */
export interface ForumCategory {
    id: number | string;
    title: string;
    nodes: ForumNode[];
}

/**
 * 主题帖类型 (对应 threads 列表项)
 */
export interface ThreadItem {
    id: number;
    nodeId: number | string;
    title: string;
    slug: string;
    prefixes?: ThreadPrefix[];          // 主题前缀标签列表
    prefix?: string;                    // 单前缀纯文本 (兼容旧字段)
    authorName: string;
    authorAvatar?: string | null;       // 头像 URL (支持 null)
    author?: BasicAuthor | PostAuthor;  // 主题作者信息
    isSticky: boolean;                  // 是否置顶
    isLocked: boolean;                  // 是否锁定
    viewCount: number;                  // 浏览数
    replyCount: number;                 // 回复数
    createdAt: string;                  // 发布时间
    lastPost?: LastPostInfo | null;     // 最新回复动态
}

/**
 * 🎯 楼层帖子单项类型 (对应 posts 表物理结构)
 */
export interface PostItem {
    id: number;
    position: number;                   // 0-indexed 楼层序号 (0 代表 1 楼/主帖)
    floorNumber: number;                // 人类可读楼层号 (1, 2, 3...)
    isFirstPost: boolean;               // 是否为 1 楼首帖正文
    message: string;                    // 正文内容 (支持 Markdown / 富文本)
    reactionScore: number;              // 净表态得分
    createdAt: string;                  // 格式化发布时间
    editCount?: number;                 // 编辑次数
    editedAt?: string | null;           // 最后编辑时间
    editorName?: string | null;         // 最后编辑人名称
    author: PostAuthor;                 // 发帖作者画像 (支持多重横幅与活跃指标)
}

/**
 * 🎯 主题详情对象 (专供 ThreadShow 标头渲染)
 */
export interface ThreadDetail {
    id: number | string;
    nodeId: number | string;
    title: string;
    slug?: string;
    isSticky: boolean;                  // 是否置顶
    isLocked: boolean;                  // 是否锁定回复
    viewCount: number;                  // 浏览总数
    replyCount: number;                 // 回帖总数
    createdAt: string;                  // 发布时间
    prefixes?: ThreadPrefix[];          // 彩色分类标签
    author: BasicAuthor | PostAuthor;   // 主题发起人
}

/**
 * 🎯 帖子详情页面 Props (ThreadShow.tsx 接收的 Inertia 数据契约)
 */
export interface ThreadShowPageProps {
    breadcrumbs: BreadcrumbItem[];
    thread: ThreadDetail;
    posts: PaginatedData<PostItem>;
    canReply: boolean;
    seo?: ThreadSeoData;
    auth?: {
        user?: BasicAuthor | null;
    };
}

/**
 * 🎯 版块/全站公告通知类型
 */
export interface ForumNoticeItem {
    id: number;
    title: string;                      // 公告标题
    content?: string | null;            // 公告详情描述
    type: 'info' | 'warning' | 'danger' | 'success'; // 样式类型
    linkUrl?: string | null;            // 跳转目标 URL
    linkText?: string;                  // 按钮文案
    isDismissible: boolean;             // 是否允许用户关闭
}

/**
 * 🎯 帖子详情页 SEO 结构化数据类型
 */
export interface ThreadSeoData {
    canonicalUrl: string;               // 规范链接 (Canonical URL)
    prevPageUrl?: string | null;        // 上一页 URL
    nextPageUrl?: string | null;        // 下一页 URL
}
