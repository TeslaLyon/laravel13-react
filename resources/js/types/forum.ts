/**
 * 最新发帖/跟帖缓存信息
 */
export interface LastPostInfo {
    threadId?: number | string;
    threadTitle?: string;
    authorName: string;
    createdAt: string;
}

/**
 * 子版块轻量类型
 */
export interface SubForumNode {
    id: number | string;
    name: string;
    slug: string;
}

/**
 * 节点项类型 (对应 nodes + forums)
 */
export interface ForumNode {
    id: number | string;
    name: string;
    slug: string;
    nodeType: 'category' | 'forum' | 'link' | 'page';
    description?: string;
    linkUrl?: string; // 外链节点专属 URL
    threadCount: number;
    messageCount: number;
    subForums?: SubForumNode[];
    lastPost?: LastPostInfo;
}

/**
 * 分类大块类型
 */
export interface ForumCategory {
    id: number | string;
    name: string;
    nodes: ForumNode[];
}

/**
 * 主题帖类型 (对应 threads)
 */
export interface ThreadItem {
    id: number | string;
    nodeId: number | string;
    title: string;
    slug: string;
    prefix?: string;          // 主题前缀 (如: [公告], [分享])
    authorName: string;
    authorAvatar?: string;
    isSticky: boolean;        // 是否置顶
    isLocked: boolean;        // 是否锁定
    viewCount: number;        // 浏览数
    replyCount: number;       // 回复数
    createdAt: string;        // 发布时间
    lastPost?: LastPostInfo;  // 最新回复动态
}
