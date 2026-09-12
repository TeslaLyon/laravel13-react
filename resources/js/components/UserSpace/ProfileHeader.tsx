import React, { useState } from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    Share2,
    ShieldCheck,
    Sparkles,
    Award,
    ChevronRight,
} from 'lucide-react';
import { ChannelProfile } from '@/types/profile';
import { SubscribeButton, NotificationType } from '@/components/subscribe-button';
import { userMedals } from '@/actions/App/Http/Controllers/MedalController';
import { AvatarWithDecoration, AvatarDecorationData } from '@/components/avatar-with-decoration';

export interface UserMedal {
    id: string | number;
    code?: string;
    title: string;
    description: string;
    iconUrl: string;
    rarity?: string;
    isWorn?: boolean;
    unlockedAt?: string;
}

interface ProfileHeaderProps {
    profile: ChannelProfile & {
        notificationType?: NotificationType;
        medals?: UserMedal[];
        // 🌟 挂件信息：未佩戴时为 null
        avatarDecoration?: AvatarDecorationData | null;
    };
}

export const ProfileHeader: React.FC<ProfileHeaderProps> = ({ profile }) => {
    const [subscribersCount, setSubscribersCount] = useState(profile.followersCount ?? 0);

    const expPercentage = Math.min(
        Math.round((profile.tier.currentExp / profile.tier.nextLevelExp) * 100),
        100
    );

    const medalsList: UserMedal[] = profile.medals || [];
    const userMedalsUrl = userMedals.url({ user: profile.name });


    return (
        <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-2">
            {/* 1. Banner 背景 */}
            {profile.bannerUrl && (
                <div className="relative w-full h-36 sm:h-52 md:h-64 lg:h-72 rounded-2xl overflow-hidden bg-muted shadow-inner">
                    <img
                        src={profile.bannerUrl}
                        alt="Channel Banner"
                        className="w-full h-full object-cover object-center"
                    />
                </div>
            )}

            {/* 2. 核心信息区域 */}
            <div className="flex flex-col md:flex-row items-start md:items-center gap-6 pt-6 pb-4">
                {/* 🌟 核心修改：使用 AvatarWithDecoration 渲染头像与动图挂件 */}
                <AvatarWithDecoration
                    avatarSrc={profile.avatar}
                    avatarFallback={profile.nickname}
                    decoration={profile.avatarDecoration}
                    sizeClassName="w-28 h-28 sm:w-36 sm:h-36 md:w-40 md:h-40"
                />

                <div className="flex-1 space-y-3 min-w-0">
                    {/* 昵称与身份/等级 */}
                    <div className="flex flex-wrap items-center gap-2.5">
                        <h1 className="text-2xl sm:text-3xl font-extrabold tracking-tight text-foreground truncate">
                            {profile.nickname}
                        </h1>

                        <Badge
                            variant="secondary"
                            className="flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full border border-primary/20 bg-primary/10 text-primary"
                        >
                            <ShieldCheck className="w-3.5 h-3.5" />
                            {profile.group.name}
                        </Badge>

                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Badge
                                        variant="outline"
                                        className="cursor-pointer flex items-center gap-1 px-2.5 py-0.5 text-xs font-semibold rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/30 hover:bg-amber-500/20 transition-colors"
                                    >
                                        <Sparkles className="w-3.5 h-3.5 text-amber-500" />
                                        Lv.{profile.tier.level} {profile.tier.title}
                                    </Badge>
                                </TooltipTrigger>
                                <TooltipContent className="p-3 w-56 space-y-2">
                                    <div className="flex justify-between text-xs font-medium">
                                        <span>等级进度 (Lv.{profile.tier.level})</span>
                                        <span>{expPercentage}%</span>
                                    </div>
                                    <Progress value={expPercentage} className="h-1.5" />
                                    <p className="text-[10px] text-muted-foreground text-right">
                                        {profile.tier.currentExp} / {profile.tier.nextLevelExp} EXP
                                    </p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>

                    {/* 统计指标 */}
                    <div className="flex flex-wrap items-center gap-x-2 text-sm text-muted-foreground">
                        <span className="font-semibold text-foreground">@{profile.name}</span>
                        <span>•</span>
                        <span>{subscribersCount.toLocaleString()} 位订阅者</span>
                        <span>•</span>
                        <span>{profile.worksCount.toLocaleString()} 篇内容</span>
                    </div>

                    {/* 个人简介 */}
                    {profile.bio && (
                        <p className="text-sm text-muted-foreground/90 line-clamp-2 max-w-3xl">
                            {profile.bio}
                        </p>
                    )}

                    {/* 3. 荣誉勋章展示区 */}
                    <div className="pt-1">
                        {medalsList.length > 0 ? (
                            <Link href={userMedalsUrl} className="block w-fit">
                                <div className="group/shelf flex items-center gap-1.5 p-1 px-2.5 rounded-2xl bg-muted/40 dark:bg-muted/20 border border-border/50 backdrop-blur-xs w-fit shadow-2xs hover:border-amber-500/40 hover:bg-muted/60 transition-all cursor-pointer">
                                    <span className="text-[11px] font-semibold text-muted-foreground flex items-center gap-1 pr-1.5 border-r border-border/60">
                                        <Award className="w-3.5 h-3.5 text-amber-500" />
                                        <span>勋章</span>
                                    </span>

                                    <div className="flex items-center gap-1">
                                        {medalsList.map((medal) => (
                                            <TooltipProvider key={medal.id} delayDuration={100}>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <div className="relative w-8 h-8 sm:w-9 sm:h-9 p-0.5 rounded-xl flex items-center justify-center transition-all duration-300 ease-out hover:scale-120 hover:-translate-y-1 hover:z-10">
                                                            <img
                                                                src={medal.iconUrl}
                                                                alt={medal.title}
                                                                className="w-full h-full object-contain filter drop-shadow-xs group-hover:drop-shadow-[0_6px_10px_rgba(245,158,11,0.4)] transition-all duration-300 select-none"
                                                            />
                                                        </div>
                                                    </TooltipTrigger>
                                                    <TooltipContent
                                                        side="top"
                                                        className="p-3 w-52 space-y-1.5 text-center bg-popover/95 backdrop-blur-md border border-border/70 shadow-xl rounded-xl"
                                                    >
                                                        <div className="flex items-center justify-center gap-1 font-bold text-xs text-foreground">
                                                            <Sparkles className="w-3 h-3 text-amber-500 fill-amber-500" />
                                                            <span>{medal.title}</span>
                                                        </div>
                                                        <p className="text-[11px] text-muted-foreground leading-snug">
                                                            {medal.description}
                                                        </p>
                                                        {medal.unlockedAt && (
                                                            <p className="text-[10px] text-muted-foreground/60 pt-1 border-t border-border/40 font-mono">
                                                                解锁时间: {medal.unlockedAt}
                                                            </p>
                                                        )}
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        ))}
                                    </div>

                                    <span className="text-[10px] text-muted-foreground/80 pl-1 group-hover/shelf:text-amber-500 flex items-center transition-colors">
                                        全部 <ChevronRight className="w-3 h-3" />
                                    </span>
                                </div>
                            </Link>
                        ) : (
                            <Link href={profile.isSelf ? '/medals' : userMedalsUrl} className="block w-fit">
                                <div className="group/empty flex items-center gap-2 p-1.5 px-3 rounded-2xl bg-amber-500/5 hover:bg-amber-500/10 border border-amber-500/20 hover:border-amber-500/40 text-amber-700 dark:text-amber-400 transition-all cursor-pointer shadow-2xs">
                                    <Award className="w-4 h-4 text-amber-500 animate-pulse" />
                                    <span className="text-xs font-medium">
                                        {profile.isSelf ? '尚未佩戴荣誉勋章，开启创作即可点亮！' : '暂未佩戴荣誉勋章'}
                                    </span>
                                    <span className="text-[11px] font-bold text-amber-600 dark:text-amber-300 flex items-center gap-0.5 group-hover/empty:translate-x-0.5 transition-transform">
                                        勋章馆 <ChevronRight className="w-3 h-3" />
                                    </span>
                                </div>
                            </Link>
                        )}
                    </div>

                    {/* 4. 操作按钮组 */}
                    <div className="flex items-center gap-2 pt-1.5">
                        {profile.isSelf ? (
                            <Button variant="outline" className="rounded-full font-medium px-5">
                                自定义频道
                            </Button>
                        ) : (
                            <SubscribeButton
                                type="user"
                                id={profile.id}
                                name={profile.nickname}
                                initialIsSubscribed={profile.isSubscribeing ?? false}
                                initialNotificationType={profile.notificationType || 'personalized'}
                                onSubscriptionChange={(_, delta) => setSubscribersCount(prev => Math.max(0, prev + delta))}
                            />
                        )}

                        <Button
                            variant="secondary"
                            size="icon"
                            className="rounded-full bg-secondary/60 hover:bg-secondary"
                            title="分享频道"
                        >
                            <Share2 className="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    );
};
