import React, { useRef } from 'react';
import { Head, Deferred, router } from '@inertiajs/react';
import { ProfileHeader } from '@/components/UserSpace/ProfileHeader';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { ChannelProfile } from '@/types/profile';
import { Video } from '@/types/video';
import { VideoCard } from '@/components/video/Card';
import { VideoPagination, PaginationLink } from '@/components/VideoPagination';
import { Lock, Film, Image as ImageIcon, Clock, ThumbsUp, MessageSquare, User as UserIcon } from 'lucide-react';

export interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    per_page?: number;
    links: PaginationLink[];
}

interface Props {
    activeTab?: string;
    profile: ChannelProfile;
    permissions: {
        canViewVideoCollections: boolean;
        canViewImageCollections: boolean;
        canViewLikedVideos: boolean;
        canViewWatchLater: boolean;
    };
    collectedVideos?: PaginatedData<Video> | null;
    collectedImages?: PaginatedData<any> | null;
    likedVideos?: PaginatedData<Video> | null;
    watchLaterVideos?: PaginatedData<Video> | null;
}

/**
 * 视频网格骨架屏（对齐 VideoCard 比例）
 */
function VideoGridSkeleton() {
    return (
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-6">
            {Array.from({ length: 8 }).map((_, index) => (
                <div key={index} className="flex flex-col gap-2 animate-pulse">
                    <div className="w-full aspect-video rounded-xl bg-muted/80" />
                    <div className="flex gap-3 px-1 mt-1">
                        <div className="h-9 w-9 rounded-full bg-muted/80 shrink-0" />
                        <div className="flex-1 space-y-2 py-1">
                            <div className="h-3.5 w-4/5 rounded bg-muted/80" />
                            <div className="h-3 w-1/2 rounded bg-muted/60" />
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

export default function Show({
    activeTab = 'home',
    profile,
    permissions,
    collectedVideos,
    collectedImages,
    likedVideos,
    watchLaterVideos,
}: Props) {
    const isNavigatingRef = useRef(false);

    // 选项卡路由切换逻辑与防重复点击锁
    const handleTabChange = (val: string) => {
        if (val === activeTab || isNavigatingRef.current) {
            return;
        }

        isNavigatingRef.current = true;

        const targetUrl = val === 'home'
            ? `/@${profile.name}`
            : `/@${profile.name}/${val}`;

        router.visit(targetUrl, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => {
                isNavigatingRef.current = false;
            },
        });
    };

    return (
        <div className="min-h-screen bg-background text-foreground">
            <Head title={`${profile.nickname} (@${profile.name}) - 个人主页`} />

            <ProfileHeader profile={profile} />

            <div className="w-full max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-6">
                <Tabs value={activeTab} onValueChange={handleTabChange} className="w-full space-y-6">

                    {/* 🌟 1. 常规标准 Shadcn UI 选项卡导航栏 */}
                    <div className="w-full border-b border-border/50 pb-3">
                        <div className="flex w-full overflow-x-auto scroll-smooth [&::-webkit-scrollbar]:hidden">
                            <TabsList className="inline-flex h-11 items-center justify-start rounded-xl bg-muted/70 p-1 text-muted-foreground gap-1 min-w-max">

                                <TabsTrigger
                                    value="home"
                                    className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                                >
                                    <span>首页</span>
                                </TabsTrigger>

                                <TabsTrigger
                                    value="video-collections"
                                    className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                                >
                                    <Film className="w-4 h-4" />
                                    <span>视频收藏</span>
                                </TabsTrigger>

                                <TabsTrigger
                                    value="image-collections"
                                    className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                                >
                                    <ImageIcon className="w-4 h-4" />
                                    <span>图片收藏</span>
                                </TabsTrigger>

                                {permissions.canViewWatchLater && (
                                    <TabsTrigger
                                        value="video-watch-later"
                                        className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                                    >
                                        <Clock className="w-4 h-4" />
                                        <span>稍后观看</span>
                                    </TabsTrigger>
                                )}

                                <TabsTrigger
                                    value="video-likes"
                                    className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                                >
                                    <ThumbsUp className="w-4 h-4" />
                                    <span>点赞</span>
                                </TabsTrigger>

                                <TabsTrigger
                                    value="community"
                                    className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                                >
                                    <MessageSquare className="w-4 h-4" />
                                    <span>社区</span>
                                </TabsTrigger>

                                <TabsTrigger
                                    value="about"
                                    className="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-sm font-medium transition-all data-[state=active]:bg-background data-[state=active]:text-foreground data-[state=active]:shadow-xs"
                                >
                                    <UserIcon className="w-4 h-4" />
                                    <span>关于</span>
                                </TabsTrigger>
                            </TabsList>
                        </div>
                    </div>

                    {/* 🌟 2. 选项卡面板内容区域 */}
                    <div>
                        {/* 首页 */}
                        <TabsContent value="home" className="mt-0 focus-visible:outline-none">
                            <div className="rounded-xl border border-dashed border-border p-12 text-center text-muted-foreground text-sm">
                                首页推荐内容与置顶作品区域
                            </div>
                        </TabsContent>

                        {/* 视频收藏面板 */}
                        <TabsContent value="video-collections" className="mt-0 focus-visible:outline-none">
                            {!permissions.canViewVideoCollections ? (
                                <div className="rounded-xl border border-dashed border-border bg-muted/20 p-12 text-center flex flex-col items-center justify-center">
                                    <div className="w-10 h-10 rounded-full bg-muted flex items-center justify-center text-muted-foreground mb-3">
                                        <Lock className="w-5 h-5 text-muted-foreground" />
                                    </div>
                                    <h4 className="text-sm font-semibold text-foreground">内容受隐私保护</h4>
                                    <p className="text-xs text-muted-foreground mt-1 max-w-sm">该作者设置了隐私权限，仅对特定关注者或自己公开。</p>
                                </div>
                            ) : (
                                <Deferred data="collectedVideos" fallback={<VideoGridSkeleton />}>
                                    {collectedVideos && collectedVideos.data.length > 0 ? (
                                        <div className="space-y-6">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-6">
                                                {collectedVideos.data.map((video) => (
                                                    <VideoCard key={video.id} video={video} />
                                                ))}
                                            </div>

                                            <div className="pt-4 pb-8 flex justify-center">
                                                <VideoPagination links={collectedVideos.links} />
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="rounded-xl border border-dashed border-border p-12 text-center text-muted-foreground text-sm">
                                            暂未收藏任何视频。
                                        </div>
                                    )}
                                </Deferred>
                            )}
                        </TabsContent>

                        {/* 图片收藏面板 */}
                        <TabsContent value="image-collections" className="mt-0 focus-visible:outline-none">
                            {!permissions.canViewImageCollections ? (
                                <div className="rounded-xl border border-dashed border-border bg-muted/20 p-12 text-center flex flex-col items-center justify-center">
                                    <div className="w-10 h-10 rounded-full bg-muted flex items-center justify-center text-muted-foreground mb-3">
                                        <Lock className="w-5 h-5 text-muted-foreground" />
                                    </div>
                                    <h4 className="text-sm font-semibold text-foreground">内容受隐私保护</h4>
                                    <p className="text-xs text-muted-foreground mt-1 max-w-sm">该作者设置了隐私权限，仅对特定关注者或自己公开。</p>
                                </div>
                            ) : (
                                <Deferred data="collectedImages" fallback={<VideoGridSkeleton />}>
                                    <div className="rounded-xl border border-dashed border-border p-12 text-center text-muted-foreground text-sm">
                                        暂未收藏任何图片。
                                    </div>
                                </Deferred>
                            )}
                        </TabsContent>

                        {/* 稍后观看面板 */}
                        <TabsContent value="video-watch-later" className="mt-0 focus-visible:outline-none">
                            {!permissions.canViewWatchLater ? (
                                <div className="rounded-xl border border-dashed border-border bg-muted/20 p-12 text-center flex flex-col items-center justify-center">
                                    <div className="w-10 h-10 rounded-full bg-muted flex items-center justify-center text-muted-foreground mb-3">
                                        <Lock className="w-5 h-5 text-muted-foreground" />
                                    </div>
                                    <h4 className="text-sm font-semibold text-foreground">私密内容</h4>
                                    <p className="text-xs text-muted-foreground mt-1 max-w-sm">稍后观看列表仅对自己可见。</p>
                                </div>
                            ) : (
                                <Deferred data="watchLaterVideos" fallback={<VideoGridSkeleton />}>
                                    {watchLaterVideos && watchLaterVideos.data.length > 0 ? (
                                        <div className="space-y-6">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-6">
                                                {watchLaterVideos.data.map((video) => (
                                                    <VideoCard key={video.id} video={video} />
                                                ))}
                                            </div>

                                            <div className="pt-4 pb-8 flex justify-center">
                                                <VideoPagination links={watchLaterVideos.links} />
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="rounded-xl border border-dashed border-border p-12 text-center text-muted-foreground text-sm">
                                            稍后观看列表为空。
                                        </div>
                                    )}
                                </Deferred>
                            )}
                        </TabsContent>

                        {/* 点赞视频面板 */}
                        <TabsContent value="video-likes" className="mt-0 focus-visible:outline-none">
                            {!permissions.canViewLikedVideos ? (
                                <div className="rounded-xl border border-dashed border-border bg-muted/20 p-12 text-center flex flex-col items-center justify-center">
                                    <div className="w-10 h-10 rounded-full bg-muted flex items-center justify-center text-muted-foreground mb-3">
                                        <Lock className="w-5 h-5 text-muted-foreground" />
                                    </div>
                                    <h4 className="text-sm font-semibold text-foreground">内容受隐私保护</h4>
                                    <p className="text-xs text-muted-foreground mt-1 max-w-sm">该作者设置了隐私权限，仅对特定关注者或自己公开。</p>
                                </div>
                            ) : (
                                <Deferred data="likedVideos" fallback={<VideoGridSkeleton />}>
                                    {likedVideos && likedVideos.data.length > 0 ? (
                                        <div className="space-y-6">
                                            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-6">
                                                {likedVideos.data.map((video) => (
                                                    <VideoCard key={video.id} video={video} />
                                                ))}
                                            </div>

                                            <div className="pt-4 pb-8 flex justify-center">
                                                <VideoPagination links={likedVideos.links} />
                                            </div>
                                        </div>
                                    ) : (
                                        <div className="rounded-xl border border-dashed border-border p-12 text-center text-muted-foreground text-sm">
                                            暂未点赞任何视频。
                                        </div>
                                    )}
                                </Deferred>
                            )}
                        </TabsContent>

                        {/* 社区 */}
                        <TabsContent value="community" className="mt-0 focus-visible:outline-none">
                            <div className="rounded-xl border border-dashed border-border p-12 text-center text-muted-foreground text-sm">
                                频道主与粉丝互动社区动态
                            </div>
                        </TabsContent>

                        {/* 关于 */}
                        <TabsContent value="about" className="mt-0 focus-visible:outline-none">
                            <div className="max-w-2xl rounded-xl border border-border bg-card p-6 space-y-3 shadow-xs">
                                <h3 className="text-base font-semibold text-foreground">详细介绍</h3>
                                <p className="text-sm text-muted-foreground leading-relaxed whitespace-pre-wrap">
                                    {profile.bio || '该用户尚未填写个人简介。'}
                                </p>
                            </div>
                        </TabsContent>
                    </div>
                </Tabs>
            </div>
        </div>
    );
}
