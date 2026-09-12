import React, { useMemo } from 'react';
import {
    ThumbsUp,
    MessageSquareQuote,
    Share2,
    ShieldCheck,
    Edit3,
    Sparkles,
    Crown,
    Medal,
} from 'lucide-react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import UserHoverCard from './UserHoverCard';
import { PostItem, GroupBanner } from '@/types/forum';

/**
 * 🎯 1. 纯前端横幅 GIF 动效映射字典
 * 为需要流光横幅的用户组指定对应的动效图片
 */
const GROUP_EFFECT_CONFIG: Record<string, { gifUrl: string }> = {
    'administrator': {
        gifUrl: '/storage/images/effects/flame.gif',
    },
    'administrative': {
        gifUrl: '/storage/images/effects/flame.gif',
    },
    'advanced leaker': {
        gifUrl: '/storage/images/effects/raining.gif',
    },
    'advanced_leaker': {
        gifUrl: '/storage/images/effects/ss6.webp',
    },
    'contributor': {
        gifUrl: '/storage/images/effects/ss6.webp',
    },
};

/**
 * 🎯 2. 安全样式解析器：仅解析安全颜色与字重
 */
function parseUsernameStyle(cssString?: string | null): React.CSSProperties {
    if (!cssString || typeof cssString !== 'string') return {};

    const style: React.CSSProperties = {};

    cssString.split(';').forEach((rule) => {
        const colonIndex = rule.indexOf(':');
        if (colonIndex === -1) return;

        const property = rule.substring(0, colonIndex).trim().toLowerCase();
        const value = rule.substring(colonIndex + 1).trim();

        if (!property || !value) return;

        if (property === 'color' && value !== 'transparent') {
            style.color = value;
        } else if (property === 'font-weight') {
            style.fontWeight = value as any;
        }
    });

    return style;
}

/**
 * 角色横幅图标映射
 */
function RenderBannerIcon({ iconName }: { iconName?: string | null }) {
    if (!iconName) return null;
    if (iconName.includes('alert') || iconName.includes('shield')) {
        return <ShieldCheck className="w-3.5 h-3.5 shrink-0" />;
    }
    if (iconName.includes('sparkle') || iconName.includes('star')) {
        return <Sparkles className="w-3.5 h-3.5 shrink-0" />;
    }
    if (iconName.includes('crown')) {
        return <Crown className="w-3.5 h-3.5 shrink-0" />;
    }
    return <Medal className="w-3.5 h-3.5 shrink-0" />;
}

/**
 * 🎯 3. 纯净 GIF 横幅组件 (无实体背景色、无 3D 凸起渐变、GIF 满幅清晰呈现)
 */
function GroupBannerRibbon({ banner }: { banner: GroupBanner }) {
    const normalizedName = (banner.name || '').toLowerCase().trim();
    // 优先匹配组名指定的动效，未单独配置时默认使用标准星光 GIF
    const effect = GROUP_EFFECT_CONFIG[normalizedName] || {
        gifUrl: '/storage/images/effects/flame.gif',
    };

    return (
        <div
            className="w-full h-7.5 px-2.5 rounded-md text-xs font-black tracking-wide flex items-center justify-center gap-1.5 select-none relative overflow-hidden border border-white/20 transition-transform hover:scale-[1.01]"
            style={{
                // 🎯 核心：直接将 GIF 作为背景，平铺充满长方形容器
                backgroundImage: `url('${effect.gifUrl}')`,
                backgroundSize: 'cover',
                backgroundPosition: 'center',
                backgroundRepeat: 'no-repeat',
            }}
        >
            {/* 🎯 文字与图标层：纯白粗体 + 细微黑色外描边，确保在任何动图背景下都清晰可见 */}
            <div
                className="relative z-10 flex items-center justify-center gap-1.5 leading-none text-white truncate"
                style={{
                    textShadow: '0 0 3px rgba(0, 0, 0, 0.9), 0 1px 2px rgba(0, 0, 0, 0.9), 0 0 1px rgba(0, 0, 0, 1)',
                }}
            >
                <RenderBannerIcon iconName={banner.icon} />
                <span className="truncate">{banner.name}</span>
            </div>
        </div>
    );
}

export default function PostFloorItem({ post }: { post: PostItem }) {
    const isMainFloor = post.isFirstPost || post.position === 0;
    const author = post.author;
    const authorInitial = (author.name || author.username || 'U').charAt(0).toUpperCase();

    // 解析用户名专属样式
    const usernameCustomStyle = useMemo(
        () => parseUsernameStyle(author.usernameStyle),
        [author.usernameStyle]
    );

    const displayFloorNumber = post.floorNumber ?? post.position + 1;

    // 安全提取指标数值
    const prestige = author.stats?.prestigePoints ?? 0;
    const totalCredits = author.stats?.totalCredits ?? 0;
    const postCount = author.stats?.postCount ?? 0;
    const reactionScore = author.stats?.reactionScore ?? 0;

    return (
        <article
            id={`post-${post.id}`}
            className={`rounded-2xl border border-border/80 bg-card overflow-hidden shadow-xs transition-shadow hover:shadow-md ${isMainFloor ? 'ring-1 ring-primary/25 border-primary/30' : ''
                }`}
        >
            <div className="flex flex-col md:flex-row">
                {/* 1. 左侧：发帖人画像信息栏 (宽度 md:w-60) */}
                <div className="md:w-60 p-4 md:p-5 bg-muted/20 md:border-r border-b md:border-b-0 border-border/60 flex md:flex-col items-center md:items-center justify-between md:justify-start gap-3 shrink-0">
                    {/* 头像 */}
                    <UserHoverCard author={author}>
                        <div className="cursor-pointer group/avatar relative p-1">
                            <Avatar className="w-14 h-14 md:w-16 md:h-16 border-2 border-background shadow-md transition-transform group-hover/avatar:scale-105 relative z-10">
                                <AvatarImage src={author.avatar || ''} alt={author.name} className="object-cover" />
                                <AvatarFallback className="text-base font-bold bg-primary/10 text-primary">
                                    {authorInitial}
                                </AvatarFallback>
                            </Avatar>
                        </div>
                    </UserHoverCard>

                    {/* 用户名、头衔与横幅区域 */}
                    <div className="text-left md:text-center flex-1 md:flex-initial min-w-0 w-full space-y-1.5">
                        {/* A. 用户名 */}
                        <UserHoverCard author={author}>
                            <span
                                style={usernameCustomStyle}
                                className="font-bold text-sm md:text-base text-foreground hover:opacity-85 cursor-pointer block truncate tracking-tight transition-opacity"
                            >
                                {author.name || author.username || `用户_${author.id}`}
                            </span>
                        </UserHoverCard>

                        {/* B. 阶梯等级头衔 */}
                        {author.displayTitle && (
                            <div className="text-xs font-semibold text-muted-foreground truncate leading-tight">
                                {author.displayTitle}
                            </div>
                        )}

                        {/* 🎯 C. 纯净 GIF 长方形横幅列表 (无底色、无凸起高光) */}
                        {author.banners && author.banners.length > 0 && (
                            <div className="flex flex-col gap-1.5 w-full pt-1">
                                {author.banners.map((banner, index) => (
                                    <GroupBannerRibbon key={`banner-${index}`} banner={banner} />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* 2. 核心指标统计网格 */}
                    <div className="hidden md:flex flex-col gap-1.5 w-full pt-3 mt-1 border-t border-border/50 text-xs text-muted-foreground">
                        <div className="flex justify-between items-center py-0.5">
                            <span className="font-medium text-foreground/70">总积分:</span>
                            <span className="text-sm font-bold text-primary font-mono tracking-tight">
                                {totalCredits}
                            </span>
                        </div>

                        <div className="flex justify-between items-center py-0.5">
                            <span className="text-muted-foreground">发帖:</span>
                            <span className="font-semibold text-foreground/90 font-mono">
                                {postCount}
                            </span>
                        </div>

                        <div className="flex justify-between items-center py-0.5">
                            <span className="text-muted-foreground">获赞:</span>
                            <span className="font-semibold text-foreground/90 font-mono">
                                {reactionScore}
                            </span>
                        </div>

                        {prestige > 0 && (
                            <div className="flex justify-between items-center py-0.5">
                                <span className="text-muted-foreground">威望:</span>
                                <span className="font-semibold text-amber-500 font-mono">
                                    +{prestige}
                                </span>
                            </div>
                        )}

                        <div className="flex justify-between items-center py-1 border-t border-border/30 mt-0.5 text-[11px]">
                            <span className="text-muted-foreground/80">注册:</span>
                            <span className="text-muted-foreground font-mono font-medium">{author.joinedAt}</span>
                        </div>
                    </div>
                </div>

                {/* 3. 右侧：楼层正文与操作栏 */}
                <div className="flex-1 p-5 md:p-6 flex flex-col justify-between min-w-0">
                    <div>
                        <div className="flex items-center justify-between pb-3 mb-4 border-b border-border/40 text-xs text-muted-foreground">
                            <span>发布于 {post.createdAt}</span>
                            <Badge
                                variant={isMainFloor ? 'default' : 'outline'}
                                className={`text-[11px] px-2.5 py-0.5 font-semibold ${isMainFloor
                                        ? 'bg-primary text-primary-foreground'
                                        : 'text-muted-foreground'
                                    }`}
                            >
                                {isMainFloor ? '楼主 #1' : `#${displayFloorNumber}`}
                            </Badge>
                        </div>

                        <div className="prose prose-sm dark:prose-invert max-w-none text-foreground/90 leading-relaxed break-words py-2 whitespace-pre-wrap">
                            {post.message}
                        </div>

                        {post.editCount && post.editCount > 0 ? (
                            <div className="mt-4 flex items-center gap-1.5 text-xs text-muted-foreground/70 italic border-t border-border/30 pt-2">
                                <Edit3 className="w-3.5 h-3.5" />
                                <span>
                                    最后由 {post.editorName || '用户'} 于 {post.editedAt} 编辑
                                </span>
                            </div>
                        ) : null}
                    </div>

                    <div className="flex items-center justify-between pt-4 mt-6 border-t border-border/40 text-xs">
                        <div className="flex items-center gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 px-2.5 gap-1.5 text-muted-foreground hover:text-primary"
                            >
                                <ThumbsUp className="w-3.5 h-3.5" />
                                <span>表态 ({post.reactionScore})</span>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-8 px-2.5 gap-1.5 text-muted-foreground hover:text-primary"
                            >
                                <MessageSquareQuote className="w-3.5 h-3.5" />
                                <span>引用</span>
                            </Button>
                        </div>
                        <Button variant="ghost" size="sm" className="h-8 px-2 text-muted-foreground">
                            <Share2 className="w-3.5 h-3.5" />
                        </Button>
                    </div>
                </div>
            </div>
        </article>
    );
}
