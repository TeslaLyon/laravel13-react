export interface UserGroup {
    id: number;
    name: string;
    slug: string;
    color?: string; // 例如: 'bg-amber-500/10 text-amber-600 border-amber-500/20'
}

export interface UserTier {
    level: number;
    title: string;
    currentExp: number;
    nextLevelExp: number;
    badgeIconUrl?: string;
}

export interface ChannelProfile {
    id: number;
    name: string; // 唯一用户名/Handle，例如: @taylorotwell
    nickname: string; // 显示昵称，例如: Taylor Otwell
    avatar: string;
    bannerUrl?: string;
    bio?: string;
    followersCount: number;
    worksCount: number;
    isSubscribeing: boolean;
    isSelf: boolean;
    group: UserGroup;
    tier: UserTier;
}
